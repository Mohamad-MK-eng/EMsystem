<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    private const TTL_PRODUCTS     = 60;
    private const TTL_SINGLE       = 600;
    private const TTL_REPORT       = 3600;
    private const TTL_INVENTORY    = 60;

    private const KEY_PRODUCTS_ALL  = 'products:all';
    private const KEY_PRODUCT       = 'products:single:';
    private const KEY_INVENTORY     = 'inventory:product:';
    private const KEY_REPORT        = 'reports:daily:';
    private const KEY_ACTIVE_COUNT  = 'products:active_count';

    public function getAllProducts()
    {


        if (!config('performance.use_caching')) {
            Log::debug('[CacheService] Caching OFF — querying DB directly for all products.');
            return Product::where('status', 'active')
                ->orderBy('name')->with('inventory')
                ->get()->toArray();
        }

        return Cache::remember(
            self::KEY_PRODUCTS_ALL,
            self::TTL_PRODUCTS,
            function () {
                Log::debug('[CacheService] Cache MISS for all products — querying DB.');
                return Product::where('status', 'active')
                    ->orderBy('name')->with('inventory')
                    ->get()
                    ->toArray();
            }
        );
    }

    public function getProduct(int $productId): array|Product|null
    {
        if (!config('performance.use_caching')) {
            return Product::with('inventory')->find($productId)?->toArray();
        }

        return Cache::remember(
            self::KEY_PRODUCT . $productId,
            self::TTL_SINGLE,
            fn() => Product::with('inventory')
                ->find($productId)?->toArray()
        );
    }

    public function getDailyReport(string $date): ?SalesReport
    {
        if (!config('performance.use_caching')) {
            return SalesReport::where('report_date', $date)->first();
        }

        return Cache::remember(
            self::KEY_REPORT . $date,
            self::TTL_REPORT,
            fn() => SalesReport::where('report_date', $date)->first()
        );
    }

    public function invalidateProduct(int $productId): void
    {
        Cache::forget(self::KEY_PRODUCT . $productId);
        Cache::forget(self::KEY_INVENTORY . $productId);

        Log::info("[CacheService] Invalidated cache for product #{$productId}.");
    }

    public function invalidateAllProducts(): void
    {
        Cache::forget(self::KEY_PRODUCTS_ALL);

        Log::info("[CacheService] Invalidated cache for all products.");
    }

    public function flushAll(): bool
    {
        $result = Cache::flush();
        Log::warning('[CacheService] Full cache flush executed by admin.');
        return $result;
    }

    public function getStats(): array
    {
        $driver = config('cache.default');
        $isRedis = $driver === 'redis';

        $stats = [
            'driver'            => $driver,
            'is_redis'          => $isRedis,
            'caching_enabled'   => config('performance.use_caching'),
            'ttl_config' => [
                'products_all_seconds'  => self::TTL_PRODUCTS,
                'single_product_seconds'=> self::TTL_SINGLE,
                'inventory_seconds'     => self::TTL_INVENTORY,
                'daily_report_seconds'  => self::TTL_REPORT,
            ],
        ];

        $stats['cached_keys'] = [
            'products_all'  => Cache::has(self::KEY_PRODUCTS_ALL),
            'active_count'  => Cache::has(self::KEY_ACTIVE_COUNT),
        ];

        if ($isRedis && config('performance.use_caching')) {
            try {
                $info  =Cache::getStore()->getRedis()->info('stats');
                $stats['redis_stats'] = [
                    'connected'       => true,
                    'keyspace_hits'   => $info['Stats']['keyspace_hits']   ?? 0,
                    'keyspace_misses' => $info['Stats']['keyspace_misses'] ?? 0,
                    'hit_rate'        => isset($info['Stats']['keyspace_hits'], $info['Stats']['keyspace_misses'])
                        ? round(
                            $info['Stats']['keyspace_hits'] /
                            max(1, $info['Stats']['keyspace_hits'] + $info['Stats']['keyspace_misses']) * 100, 2
                          ) . '%'
                        : 'N/A',
                ];
            } catch (\Throwable $e) {
                $stats['redis_stats'] = [
                    'connected' => false,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    public function testConnection(): array
    {
        $testKey   = 'cache:connection:test';
        $testValue = 'ok_' . time();

        try {
            Cache::put($testKey, $testValue, 10);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'success'       => $retrieved === $testValue,
                'driver'        => config('cache.default'),
                'write_read_ok' => $retrieved === $testValue,
                'message'       => $retrieved === $testValue
                    ? 'Cache is working correctly.'
                    : 'Cache write/read mismatch — check configuration.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'driver'  => config('cache.default'),
                'error'   => $e->getMessage(),
                'message' => 'Cache connection failed. Falling back to file cache.',
            ];
        }
    }
}
