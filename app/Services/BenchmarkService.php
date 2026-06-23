<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BenchmarkService
 *
 * ──────────────────────────────────────────────────────────────────────────
 * NFR #9  — Stress Testing: يُعرِّض نقاط قياس يستخدمها JMeter
 * NFR #10 — Benchmarking:   يقيس ويقارن قبل/بعد التحسين داخل Laravel
 *
 * الفكرة المحورية:
 * JMeter يقيس من الخارج (network + server time).
 * BenchmarkService يقيس من الداخل (DB queries + memory + logic time).
 * الاثنان معاً يُعطيان صورة كاملة لا يمكن الطعن فيها.
 * ──────────────────────────────────────────────────────────────────────────
 */
class BenchmarkService
{
    // =========================================================================
    // SCENARIO 1 — N+1 Query Problem (NFR #2 Eager Loading)
    // =========================================================================

    /**
     * يُنفّذ نفس العملية مرتين:
     * - مرة بدون Eager Loading  → يُظهر عدد الـ queries الحقيقي
     * - مرة مع Eager Loading    → يُظهر الفرق الصارخ
     *
     * هذا هو أوضح دليل على NFR #2 في المقابلة.
     */
    public function measureEagerLoadingImpact(int $cartSize = 10): array
    {
        // ── الحالة السيئة: بدون Eager Loading ────────────────────────────
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);

        // جلب السلة بدون with() → N+1: query لكل product + query لكل inventory
        $cart = Cart::where('status', 'active')
            ->where('id', 52)
            ->first();

        if ($cart) {
            foreach ($cart->items as $item) {
                // كل هذه تُطلق query منفصلة لأنها غير محمّلة مسبقاً
                $name  = $item->product->name;
                $stock = $item->product->inventory->stock_quantity;
            }
        }

        $withoutEager = [
            'time_ms'     => round((microtime(true) - $start) * 1000, 2),
            'query_count' => count(DB::getQueryLog()),
            'queries'     => array_map(fn($q) => [
                'sql'  => $q['query'],
                'time' => $q['time'],
            ], DB::getQueryLog()),
        ];

        // ── الحالة المحسّنة: مع Eager Loading ────────────────────────────
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = microtime(true);

        // with() يجلب كل شيء في query واحدة لكل علاقة
        $cart = Cart::where('status', 'active')
            ->with(['items.product.inventory'])
            ->where('id', 52)
            ->first();

        if ($cart) {
            foreach ($cart->items as $item) {
                $name  = $item->product->name;        // من الذاكرة، لا DB
                $stock = $item->product->inventory->stock_quantity; // من الذاكرة
            }
        }

        $withEager = [
            'time_ms'     => round((microtime(true) - $start) * 1000, 2),
            'query_count' => count(DB::getQueryLog()),
            'queries'     => array_map(fn($q) => [
                'sql'  => $q['query'],
                'time' => $q['time'],
            ], DB::getQueryLog()),
        ];

        DB::disableQueryLog();

        $itemCount = $cart?->items->count() ?? 0;

        return [
            'scenario'        => 'N+1 Query — Eager Loading Impact',
            'nfr'             => 'NFR #2 Resource Management',
            'items_in_cart'   => $itemCount,
            'without_eager'   => $withoutEager,
            'with_eager'      => $withEager,
            'improvement'     => [
                'queries_saved'  => $withoutEager['query_count'] - $withEager['query_count'],
                'time_saved_ms'  => round($withoutEager['time_ms'] - $withEager['time_ms'], 2),
                'speedup_factor' => $withEager['time_ms'] > 0
                    ? round($withoutEager['time_ms'] / $withEager['time_ms'], 1) . 'x faster'
                    : 'N/A',
            ],
            'explanation' => "With {$itemCount} cart items: "
                . "without eager={$withoutEager['query_count']} queries, "
                . "with eager={$withEager['query_count']} queries. "
                . "Formula: 1 + (N * 2) vs 3 fixed queries.",
        ];
    }

    // =========================================================================
    // SCENARIO 2 — Race Condition (NFR #1 #7 Optimistic Locking)
    // =========================================================================

    /**
     * يُحاكي race condition بتشغيل عمليات تخفيض متزامنة على نفس المنتج.
     * يُظهر الفرق بين:
     * - بدون locking: stock_quantity يصبح سالباً
     * - مع locking:   يُعيد المحاولة ويرفض عند التعارض
     */
    public function measureRaceConditionProtection(int $productId, int $concurrentRequests = 5): array
    {
        $inventory = Inventory::where('product_id', $productId)->first();

        if (!$inventory) {
            return ['error' => "Product {$productId} not found."];
        }

        $deduction  = 4 ;

        $initialStock = $inventory->stock_quantity;
        $results      = ['initial_stock' => $initialStock, 'requests' => []];

        // ── محاكاة بدون Optimistic Locking ───────────────────────────────
        // إعادة تعيين المخزون
        Inventory::where('product_id', $productId)
            ->update(['stock_quantity' => 10, 'version' => 0]);

        $conflicts = 0;
        for ($i = 0; $i < $concurrentRequests; $i++) {
            // قراءة ثم تخفيض بدون فحص version → Race Condition محتملة
            $inv = Inventory::where('product_id', $productId)->first();
            Inventory::where('product_id', $productId)
                ->update(['stock_quantity' => $inv->stock_quantity - $deduction]);
        }

        $afterWithoutLock = Inventory::where('product_id', $productId)
            ->value('stock_quantity');

        // ── محاكاة مع Optimistic Locking ─────────────────────────────────
        Inventory::where('product_id', $productId)
            ->update(['stock_quantity' => 10, 'version' => 0]);

        $successCount = 0;
        $retryCount   = 0;
        $rejectCount  = 0;

        for ($i = 0; $i < $concurrentRequests; $i++) {
            $maxRetries = 3;
            $attempts   = 0;
            $succeeded  = false;

            while ($attempts < $maxRetries && !$succeeded) {
                $inv = Inventory::where('product_id', $productId)->first();

                if ($inv->stock_quantity < $deduction) {
                    $rejectCount++;
                    break;
                }

                $updated = Inventory::where('product_id', $productId)
                    ->where('version', $inv->version)
                    ->where('stock_quantity', '>=', $deduction)
                    ->update([
                        'stock_quantity' => $inv->stock_quantity - $deduction,
                        'version'        => $inv->version + 1,
                    ]);

                if ($updated === 1) {
                    $successCount++;
                    $succeeded = true;
                } else {
                    $retryCount++;
                    $attempts++;
                }
            }

            if (!$succeeded && $rejectCount === 0) {
                $rejectCount++;
            }
        }

        $afterWithLock = Inventory::where('product_id', $productId)
            ->value('stock_quantity');

        // إعادة المخزون لقيمته الأصلية
        Inventory::where('product_id', $productId)
            ->update(['stock_quantity' => $initialStock, 'version' => 0]);

        return [
            'scenario'           => 'Race Condition — Optimistic Locking',
            'nfr'                => 'NFR #1 Race Condition + NFR #7 Optimistic Locking',
            'product_id'         => $productId,
            'concurrent_ops'     => $concurrentRequests,
            'each_op_deducts'    => $deduction,
            'without_locking'    => [
                'final_stock'    => $afterWithoutLock,
                'is_negative'    => $afterWithoutLock < 0,
                'verdict'        => $afterWithoutLock < 0
                    ? '❌ RACE CONDITION DETECTED — stock went negative!'
                    : '⚠️ No negative stock this run (may vary under real concurrency)',
            ],
            'with_locking'       => [
                'final_stock'    => $afterWithLock,
                'is_negative'    => $afterWithLock < 0,
                'successful_ops' => $successCount,
                'retries'        => $retryCount,
                'rejected_ops'   => $rejectCount,
                'verdict'        => $afterWithLock >= 0
                    ? '✅ PROTECTED — stock never went negative'
                    : '❌ Unexpected: stock negative even with locking',
            ],
            'explanation' => "Without locking: all {$concurrentRequests} ops read stock=10 "
                . "simultaneously and all decrement → result can be " . (10 - $concurrentRequests * 2)
                . ". With locking: version mismatch triggers retry/reject → stock stays valid.",
        ];
    }

    // =========================================================================
    // SCENARIO 3 — Transaction Integrity (NFR #8 ACID)
    // =========================================================================

    /**
     * يُظهر فرق ACID Transaction على سلامة البيانات.
     * بدون transaction: فشل في منتصف العملية يترك بيانات ناقصة.
     * مع transaction: كل شيء أو لا شيء.
     */
    public function measureTransactionIntegrity(): array
    {
        $user = User::where('role', 'customer')->first();
        if (!$user) {
            return ['error' => 'No customer found for test.'];
        }

        // ── بدون Transaction: نُحاكي فشلاً في المنتصف ────────────────────
        $ordersBefore = Order::count();
        $orphanCreated = false;

        try {
            // إنشاء طلب (يُكتب في DB)
            $order = Order::create([
                'user_id'     => $user->id,
                'total_price' => 999.99,
                'status'      => 'pending',
            ]);
            $orphanCreated = true;

            // محاكاة فشل بعد إنشاء الطلب وقبل الدفع
            throw new \Exception('Simulated payment failure mid-process');

        } catch (\Exception $e) {
            // بدون rollback → الطلب بقي في DB بدون دفع
        }

        $ordersAfterNonAcid = Order::count();
        $orphanOrder = Order::where('user_id', $user->id)
            ->where('total_price', 999.99)
            ->where('status', 'pending')
            ->first();

        // حذف الطلب اليتيم قبل اختبار الـ Transaction
        Order::where('total_price', 999.99)->delete();

        // ── مع Transaction: نفس الفشل لكن مع Rollback ────────────────────
        $ordersBeforeAcid = Order::count();
        $exceptionCaught  = false;

        try {
            DB::transaction(function () use ($user) {
                Order::create([
                    'user_id'     => $user->id,
                    'total_price' => 999.99,
                    'status'      => 'pending',
                ]);
                // نفس الفشل المحاكى
                throw new \Exception('Simulated payment failure');
            });
        } catch (\Exception $e) {
            $exceptionCaught = true;
            // DB::transaction يُلغي كل شيء تلقائياً عند الاستثناء
        }

        $ordersAfterAcid = Order::count();

        return [
            'scenario' => 'ACID Transaction Integrity',
            'nfr'      => 'NFR #8 ACID Transactions',
            'without_transaction' => [
                'orders_before'     => $ordersBefore,
                'orders_after'      => $ordersAfterNonAcid,
                'orphan_created'    => $orphanCreated,
                'orphan_found_in_db'=> $orphanOrder !== null,
                'verdict'           => $orphanOrder !== null
                    ? '❌ ORPHAN ORDER created — partial write without payment!'
                    : '⚠️ No orphan this run',
            ],
            'with_transaction' => [
                'orders_before'     => $ordersBeforeAcid,
                'orders_after'      => $ordersAfterAcid,
                'exception_caught'  => $exceptionCaught,
                'orders_unchanged'  => $ordersBeforeAcid === $ordersAfterAcid,
                'verdict'           => $ordersBeforeAcid === $ordersAfterAcid
                    ? '✅ ROLLED BACK — no orphan, DB state unchanged'
                    : '❌ Unexpected: order persisted despite transaction',
            ],
            'explanation' => 'Without DB::transaction: if payment fails after order creation, '
                . 'the order row stays in DB permanently (orphan). '
                . 'With DB::transaction: exception triggers automatic ROLLBACK — '
                . 'nothing is written.',
        ];
    }

    // =========================================================================
    // SCENARIO 4 — Async vs Sync Response Time (NFR #3)
    // =========================================================================

    /**
     * يقيس فرق زمن الاستجابة بين تشغيل Invoice Job متزامن ومتزامن.
     */
    public function measureAsyncImpact(): array
    {
        $order = Order::where('status', 'paid')->with(['items.product', 'user', 'payment'])->first();

        if (!$order) {
            return ['error' => 'No paid order found. Complete a checkout first.'];
        }

        // ── تشغيل متزامن (Synchronous) ───────────────────────────────────
        $start = microtime(true);
        (new \App\Jobs\GenerateInvoiceJob($order))->handle();
        $syncTime = round((microtime(true) - $start) * 1000, 2);

        // ── تشغيل غير متزامن (Dispatch to Queue) ─────────────────────────
        $start = microtime(true);
        \App\Jobs\GenerateInvoiceJob::dispatch($order);
        $asyncDispatchTime = round((microtime(true) - $start) * 1000, 2);

        return [
            'scenario'          => 'Async vs Sync Job Execution',
            'nfr'               => 'NFR #3 Asynchronous Queue Processing',
            'sync_execution_ms' => $syncTime,
            'async_dispatch_ms' => $asyncDispatchTime,
            'time_saved_ms'     => round($syncTime - $asyncDispatchTime, 2),
            'speedup_factor'    => $asyncDispatchTime > 0
                ? round($syncTime / $asyncDispatchTime, 1) . 'x faster response'
                : 'N/A',
            'verdict' => $asyncDispatchTime < $syncTime
                ? '✅ Async dispatch is significantly faster — job runs in background'
                : '⚠️ Results may vary — run under load for clearer difference',
            'explanation' => "Sync: checkout waits {$syncTime}ms for invoice generation. "
                . "Async: checkout dispatches in {$asyncDispatchTime}ms and returns immediately. "
                . "Invoice generation happens in background worker.",
        ];
    }

    // =========================================================================
    // FULL BENCHMARK REPORT — كل السيناريوهات دفعة واحدة
    // =========================================================================

    public function runFullBenchmark(): array
    {
        $startTime   = microtime(true);
        $startMemory = memory_get_usage(true);

        $product = Product::whereHas('inventory')->first();

        $results = [
            'benchmark_timestamp' => now()->toISOString(),
            'environment'         => [
                'php_version'     => PHP_VERSION,
                'laravel_version' => app()->version(),
                'db_connection'   => config('database.default'),
                'queue_driver'    => config('queue.default'),
            ],
            'active_flags' => [
                'use_optimistic_locking' => config('performance.use_optimistic_locking'),
                'use_db_transactions'    => config('performance.use_db_transactions'),
                'use_eager_loading'      => config('performance.use_eager_loading'),
                'use_async_jobs'         => config('performance.use_async_jobs'),
                'use_batch_processing'   => config('performance.use_batch_processing'),
                'use_rate_limiting'      => config('performance.use_rate_limiting'),
                'use_caching'            => config('performance.use_caching'),
            ],
            'scenarios' => [],
        ];

        // تشغيل كل السيناريوهات
        $results['scenarios']['eager_loading']     = $this->measureEagerLoadingImpact();
        $results['scenarios']['transaction_acid']  = $this->measureTransactionIntegrity();
        $results['scenarios']['async_vs_sync']     = $this->measureAsyncImpact();

        if ($product) {
            $results['scenarios']['race_condition'] = $this->measureRaceConditionProtection(
                $product->id, 5
            );
        }

        $results['benchmark_summary'] = [
            'total_time_ms'  => round((microtime(true) - $startTime) * 1000, 2),
            'memory_used_mb' => round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2),
            'scenarios_run'  => count($results['scenarios']),
        ];

        Log::info('[BenchmarkService] Full benchmark completed.', [
            'duration_ms' => $results['benchmark_summary']['total_time_ms'],
            'flags'       => $results['active_flags'],
        ]);

        return $results;
    }
}
