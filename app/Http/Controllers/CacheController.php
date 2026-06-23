<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CacheController extends Controller
{
    public function __construct(
        private CacheService  $cacheService,
        private ProductService $productService
    ) {
    }

    public function test(): JsonResponse
    {
        $result = $this->cacheService->testConnection();

        return response()->json([
            'success' => $result['success'],
            'data'    => $result,
            'nfr'     => 'NFR #6 — Distributed Caching connection test',
        ], $result['success'] ? 200 : 503);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->cacheService->getStats();

        return response()->json([
            'success' => true,
            'data'    => $stats,
            'nfr'     => 'NFR #6 — Cache statistics and Redis info',
            'hint'    => 'keyspace_hits / (hits + misses) = hit rate. '
                       . 'High hit rate = less DB load under concurrent requests.',
        ]);
    }

    public function compare(): JsonResponse
    {
        $start = microtime(true);
        \App\Models\Product::where('status', 'active')
            ->with('inventory')
            ->orderBy('name')
            ->get();
        $dbTime = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        $this->cacheService->getAllProducts();
        $firstCacheTime = round((microtime(true) - $start) * 1000, 2);

        $start = microtime(true);
        $this->cacheService->getAllProducts();
        $secondCacheTime = round((microtime(true) - $start) * 1000, 2);

        $improvement = $dbTime > 0
            ? round((($dbTime - $secondCacheTime) / $dbTime) * 100, 1)
            : 0;

        return response()->json([
            'success' => true,
            'nfr'     => 'NFR #6 — Cache vs DB performance comparison',
            'data'    => [
                'caching_flag_enabled' => config('performance.use_caching'),
                'cache_driver'         => config('cache.default'),
                'measurements' => [
                    'direct_db_query_ms'     => $dbTime,
                    'cache_first_request_ms' => $firstCacheTime,
                    'cache_hit_ms'           => $secondCacheTime,
                ],
                'improvement' => [
                    'time_saved_ms'   => round($dbTime - $secondCacheTime, 2),
                    'speedup_percent' => $improvement . '%',
                    'verdict'         => $secondCacheTime < $dbTime
                        ? "✅ Cache HIT is {$improvement}% faster than direct DB query"
                        : '⚠️ Enable caching flag and ensure cache driver is connected',
                ],
                'explanation' => 'First cache request may be slow (MISS = DB hit + write to cache). '
                    . 'Subsequent requests are served from memory (HIT). '
                    . 'Under 100 concurrent users, HIT rate dramatically reduces DB load.',
            ],
        ]);
    }

    public function flush(): JsonResponse
    {
        $result = $this->cacheService->flushAll();

        return response()->json([
            'success' => $result,
            'message' => $result
                ? 'Cache flushed successfully. Next requests will rebuild from DB.'
                : 'Cache flush failed.',
            'nfr'     => 'NFR #6 — Cache invalidation test',
            'hint'    => 'Call /cache/compare again after flush to see cache rebuild in action.',
        ]);
    }

    public function products(): JsonResponse
    {
        $start    = microtime(true);
        $products = $this->productService->getAllActive();
        $elapsed  = round((microtime(true) - $start) * 1000, 2);

        return response()->json([
            'success'        => true,
            'count'          => $products->count(),
            'data'           => $products,
            'served_from'    => config('performance.use_caching') ? 'cache' : 'database',
            'response_ms'    => $elapsed,
        ]);
    }
}
