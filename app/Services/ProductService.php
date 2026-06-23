<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Product;
use Dompdf\Image\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ProductService
{
    public function __construct(private CacheService $cacheService)
    {
    }


    public function getProducts(Request $request): array
    {
        if (
            config('performance.use_caching')
            && !config('performance.use_pagination')
        ) {
            $products = $this->cacheService->getAllProducts();

            return [
                'total_items' => count($products),
                'source' => 'cache',
                'data' => $products,
            ];
        }

        $query = Product::query();

        // NFR #2 — Eager Loading
        if (config('performance.use_eager_loading')) {
            $query->with('inventory');
        }

        $query->latest();

        if (config('performance.use_pagination')) {
            $products = $query->paginate(
                perPage: 15,
                page: (int) $request->query('page', 1)
            );

            return [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total_items'  => $products->total(),
                'per_page'     => $products->perPage(),
                'current_size' => $products->count(),
                'source'       => 'database',
                'data'         => $products->items(),
            ];
        }

        return [
            'total_items' => $query->count(),
            'source'      => 'database',
            'data'        => $query->get(),
        ];
    }


    public function getProductById(int $id): array|Product|null
    {

        return $this->cacheService->getProduct($id);
    }


    public function createProduct(array $data): Product
    {
        $callback = function () use ($data) {
            $product = Product::create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'price'       => $data['price'],
                'status'      => 'active',
            ]);

            Inventory::create([
                'product_id'     => $product->id,
                'stock_quantity' => $data['stock_quantity'],
                'version'        => 0,
            ]);

            return $product->load('inventory');
        };

        // التصحيح: use_transactions → use_db_transactions
        $product = config('performance.use_db_transactions')
            ? DB::transaction($callback)
            : $callback();

        // NFR #6: إبطال Cache قائمة المنتجات بعد الإضافة
        $this->cacheService->invalidateProduct($product->id);
        $this->cacheService->invalidateAllProducts();

        return $product;
    }


    public function updateProduct(Product $product, array $data): Product
    {
        $callback = function () use ($product, $data) {

            $product->update([
                'name'        => $data['name']        ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'price'       => $data['price']        ?? $product->price,
            ]);

            if (isset($data['increment'])) {
                $inventory = $product->inventory()->first();

                if (!$inventory) {
                    abort(404, 'Inventory not found.');
                }

                if (config('performance.use_optimistic_locking')) {

                    $updated = Inventory::where('id', $inventory->id)
                        ->where('version', (int) $data['version'])
                        ->update([
                            'stock_quantity' => $inventory->stock_quantity + $data['increment'],
                            'version'        => $inventory->version + 1,
                        ]);

                    if (!$updated) {
                        abort(409, 'Inventory modified by another process. Please retry.');
                    }
                } else {
                    $inventory->update([
                        'stock_quantity' => $inventory->stock_quantity + $data['increment'],
                    ]);
                }
            }
        };

        config('performance.use_db_transactions')
            ? DB::transaction($callback)
            : $callback();


        $this->cacheService->invalidateProduct($product->id);

        return $product->fresh('inventory');
    }



    public function deleteProduct(Product $product): void
    {
        $callback = function () use ($product) {
            if (
                config('performance.use_soft_delete_protection')
                && $product->cartItems()->exists()
            ) {
                abort(409, 'Cannot delete product: exists in active carts.');
            }

            $product->delete(); // SoftDeletes trait — يضع deleted_at فقط
        };

        config('performance.use_db_transactions')
            ? DB::transaction($callback)
            : $callback();

        $this->cacheService->invalidateProduct($product->id);
        $this->cacheService->invalidateAllProducts();
    }


    public function toggleStatus(Product $product): Product
    {
        $callback = function () use ($product) {
            if (
                config('performance.use_soft_delete_protection')
                && $product->trashed()
            ) {
                abort(409, 'Cannot change status of a deleted product.');
            }

            $product->update([
                'status' => $product->status === 'active' ? 'inactive' : 'active',
            ]);
        };

        config('performance.use_db_transactions')
            ? DB::transaction($callback)
            : $callback();

        $this->cacheService->invalidateProduct($product->id);

        return $product->fresh();
    }
}
