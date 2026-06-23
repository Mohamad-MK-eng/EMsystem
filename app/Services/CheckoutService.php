<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CacheService;



class CheckoutService
{

    public function __construct(private CacheService $cacheService)
    {

    }
    // =========================================================================
    // MAIN ENTRY POINT
    // NFR #8: entire flow wrapped in a DB transaction (when flag is on)
    // =========================================================================

    public function checkout(User $user): array
    {
        if (config('performance.use_transactions')) {
            // NFR #8 — ACID Guarantee:
            // كل العمليات (إنشاء طلب، خصم مخزون، خصم محفظة) تنجح معاً
            // أو تُلغى كلها. لا توجد حالة وسطى.
            return DB::transaction(function () use ($user) {
                return $this->processCheckout($user);
            });
        }

        // ⚠️ Flag OFF — بدون Transaction:
        // مثال للفشل الجزئي: يُنشأ الطلب، يُخصم المخزون،
        // ثم يفشل الدفع → الطلب موجود والمخزون منقوص بدون دفع.
        return $this->processCheckout($user);
    }

    // =========================================================================
    // PIPELINE
    // =========================================================================

    private function processCheckout(User $user): array
    {
        $cart    = $this->validateCart($user);       // Step 1
        $total   = $this->calculateTotal($cart);      // Step 2
        $wallet  = $this->validatePayment($user, $total); // Step 3
        $this->modifyInventory($cart);                // Step 4 ← NFR #1 #7
        $order   = $this->createOrder($user, $cart, $total); // Step 5
        $this->clearCartItems($cart);                 // Step 6
        $payment = $this->processPayment($wallet, $order);   // Step 7
       $this->dispatchJobs($order);                  // Step 8 ← NFR #3 #4

        return [
            'order_id'        => $order->id,
            'total'           => $total,
            'payment_status'  => $payment->status,
            'transaction_ref' => $payment->transaction_ref,
            'message'         => 'Checkout completed successfully.',
        ];
    }

    // =========================================================================
    // STEP 1 — VALIDATE CART
    // NFR #2: Eager Loading يمنع مشكلة N+1 queries
    // =========================================================================

    private function validateCart(User $user): Cart
    {
        $query = $user->cart()->where('status', 'active');

        if (config('performance.use_eager_loading')) {
            // NFR #2 — مع الـ flag:
            // query واحدة تجلب السلة + المنتجات + المخزون دفعة واحدة
            //
            // ⚠️ بدون الـ flag (use_eager_loading=false):
            // لكل عنصر في السلة → query منفصلة للمنتج + query للمخزون
            // 10 عناصر = 21 query بدلاً من 1
            $query->with(['items.product.inventory']);
        }

        $cart = $query->first();

        if (!$cart || $cart->items->isEmpty()) {
            throw new \Exception('Cart is empty or does not exist.', 422);
        }


        foreach ($cart->items as $item) {
            if (!$item->product || $item->product->status !== 'active') {
                throw new \Exception(
                    "Product [{$item->product?->name}] is no longer available.", 422
                );
            }

            $inventory = $item->product->inventory;
            $available = $inventory->stock_quantity - $inventory->reserved_quantity;

            if ($item->quantity > $available) {
                throw new \Exception(
                    "Insufficient stock for [{$item->product->name}]. "
                    . "Requested: {$item->quantity}, Available: {$available}.", 422
                );
            }
        }

        return $cart;
    }

    // =========================================================================
    // STEP 2 — CALCULATE TOTAL
    // =========================================================================

    private function calculateTotal(Cart $cart) : float
    {

        return round( $cart->items->sum(
            fn($item) => $item->quantity * $item->product->price
        ), 2 );
    }

    // =========================================================================
    // STEP 3 — VALIDATE PAYMENT
    // =========================================================================

    private function validatePayment(User $user, float $total): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
        // ↑ lockForUpdate() يحجز الصف أثناء الـ Transaction
        //   يمنع قراءة نفس الرصيد من طلبين متزامنين

        if (!$wallet) {
            throw new \Exception('Wallet not found for this user.', 422);
        }

        if (!$wallet->canAfford($total)) {
            throw new \Exception(
                "Insufficient wallet balance. "
                . "Required: {$total}, Available: {$wallet->availableBalance()}.", 422
            );
        }

        return $wallet;
    }

    // =========================================================================
    // STEP 4 — MODIFY INVENTORY
    // NFR #1: Race Condition protection
    // NFR #7: Optimistic Locking
    // =========================================================================

    private function modifyInventory(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            if (config('performance.use_optimistic_locking')) {
                $this->decrementWithOptimisticLock($item->product_id, $item->quantity);
            } else {
                // ⚠️ Flag OFF — RACE CONDITION DEMO:
                // طلبان يقرآن نفس stock_quantity=1
                // كلاهما يخصمان → النتيجة stock_quantity=-1
                // هذا هو بالضبط ما سيظهر في JMeter تحت الضغط
                Inventory::where('product_id', $item->product_id)
                    ->decrement('stock_quantity', $item->quantity);

                $this->cacheService->invalidateProduct($item->product_id);
            }
        }
    }

    /**
     * Optimistic Locking — نقطة تزامن حرجة (Critical Synchronization Point)
     *
     * المبدأ: لا نحجز قفل على قاعدة البيانات (Non-blocking).
     * بدلاً من ذلك، نتحقق أن الـ version لم يتغير منذ قرأناه.
     * إذا تغيّر → تعارض → نعيد المحاولة بقراءة جديدة.
     *
     * مقارنة مع Pessimistic Locking:
     * - Pessimistic: SELECT ... FOR UPDATE → يحجب كل القرّاء (بطيء تحت الضغط)
     * - Optimistic:  لا حجب، فقط retry عند التعارض (أسرع في معظم الحالات)
     */
    private function decrementWithOptimisticLock(int $productId, int $quantity, int $maxRetries = 3): void
    {
        $attempts = 0;

        while ($attempts < $maxRetries) {
            // قراءة الحالة الحالية مع الـ version
            $inventory = Inventory::where('product_id', $productId)->first();

            if (!$inventory || $inventory->stock_quantity < $quantity) {
                throw new \Exception(
                    "Stock unavailable for product ID {$productId}.", 422
                );
            }

            // محاولة التحديث الذري:
            // WHERE version = $currentVersion → يضمن أن لا أحد عدّل الصف بيننا
            $updated = Inventory::where('product_id', $productId)
                ->where('version', $inventory->version)           // ← شرط الـ version
                ->where('stock_quantity', '>=', $quantity)        // ← حماية إضافية
                ->update([
                    'stock_quantity' => $inventory->stock_quantity - $quantity,
                    'version'        => $inventory->version + 1,  // ← رفع الـ version
                ]);

            if ($updated === 1) {
                // نجح التحديث → الـ version كان مطابقاً → لا تعارض
                // NFR #6: إبطال Cache المخزون بعد التحديث الناجح
                // Synchronization note: نُبطل بعد الكتابة في DB — ليس قبلها
                $this->cacheService->invalidateProduct($productId);
                return;
            }

            // فشل التحديث → طلب آخر سبقنا وغيّر الـ version
            // نعيد المحاولة بقراءة جديدة من قاعدة البيانات
            $attempts++;
            Log::warning(
                "[OptimisticLock] Conflict on product {$productId}. "
                . "Retry {$attempts}/{$maxRetries}"
            );

            // تأخير صغير عشوائي لتقليل احتمال التعارض مجدداً
            usleep(rand(10, 50) * 1000);
        }

        throw new \Exception(
            "High contention: could not update inventory for product {$productId} "
            . "after {$maxRetries} retries.", 409
        );
    }

    // =========================================================================
    // STEP 5 — CREATE ORDER & ORDER ITEMS
    // =========================================================================

    private function createOrder(User $user, Cart $cart, float $total): Order
    {
        $order = Order::create([
            'user_id'     => $user->id,
            'total_price' => $total,
            'status'      => 'pending',
        ]);

        foreach ($cart->items as $item) {
            OrderItem::create([
                'order_id'          => $order->id,
                'product_id'        => $item->product_id,
                'quantity'          => $item->quantity,
                'price_at_purchase' => $item->product->price,
            ]);
        }

        return $order;
    }

    // =========================================================================
    // STEP 6 — CLEAR CART ITEMS
    // =========================================================================

    private function clearCartItems(Cart $cart): void
    {
        $cart->items()->delete();
    }

    // =========================================================================
    // STEP 7 — PROCESS PAYMENT
    // =========================================================================

    private function processPayment(Wallet $wallet, Order $order): Payment
    {
        $transactionRef = 'TXN-' . strtoupper(Str::random(12));

        $payment = Payment::create([
            'order_id'        => $order->id,
            'amount'          => $order->total_price,
            'status'          => 'pending',
            'transaction_ref' => $transactionRef,
        ]);

        // محاكاة بوابة الدفع

            $min = config('performance.payment_simulation.latency_min_ms');
            $max = config('performance.payment_simulation.latency_max_ms');
            usleep(rand($min, $max) * 10000);
            // mininal sleep [1s] maximum sleep[5s]
            $failureRate = config('performance.payment_simulation.failure_rate');
            if ((mt_rand() / mt_getrandmax()) < $failureRate) {
                $payment->update(['status' => 'failed']);
                $order->update(['status' => 'failed']);
                throw new \Exception('Payment gateway rejected the transaction.', 402);
            }


        // خصم من المحفظة
        $wallet->decrement('balance', $order->total_price);

        $payment->update(['status' => 'success']);
        $order->update(['status' => 'paid']);

        return $payment->fresh();
    }

    // =========================================================================
    // STEP 8 — DISPATCH ASYNC JOBS  ← مكتمل الآن
    // NFR #3: GenerateInvoiceJob    — فاتورة خارج الـ request thread
    // NFR #4: UpdateDailySalesReportJob — batch processing للتقرير
    // =========================================================================

    private function dispatchJobs(Order $order): void
    {
        if (!config('performance.use_async_jobs')) {
            // ⚠️ Flag OFF — تشغيل متزامن (Synchronous):
            // الـ response ينتظر حتى تنتهي الفاتورة والتقرير
            // سيظهر هذا كـ response time أعلى بكثير في JMeter

            Log::info("[Sync Mode] Running jobs synchronously for Order #{$order->id}");

            // تشغيل مباشر بدون queue — يثبت الفرق في الأداء
            (new GenerateInvoiceJob($order))->handle();
           // (new UpdateDailySalesReportJob(today()->toDateString()))->handle();

            return;
        }

        // ✅ Flag ON — تشغيل غير متزامن (Asynchronous):
        // الـ response يرجع فوراً، الـ Jobs تُنفَّذ في الخلفية بواسطة Queue Worker
        GenerateInvoiceJob::dispatch($order);
        //UpdateDailySalesReportJob::dispatch(today()->toDateString());

        Log::info(
            "[Async Mode] Jobs dispatched to queue for Order #{$order->id}. "
            . "Response returned immediately."
        );
    }
}
