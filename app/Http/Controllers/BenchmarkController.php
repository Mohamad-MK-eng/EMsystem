<?php

namespace App\Http\Controllers;

use App\Services\BenchmarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class BenchmarkController extends Controller
{
    public function __construct(private BenchmarkService $benchmarkService)
    {
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Current NFR flag states. Change in .env then php artisan config:clear',
            'flags'   => [
                'use_optimistic_locking' => [
                    'value' => config('performance.use_optimistic_locking'),
                    'nfr'   => 'NFR #1 Race Condition + NFR #7 Optimistic Locking',
                    'effect_when_false' => 'Inventory can go negative under concurrent load',
                ],
                'use_db_transactions' => [
                    'value' => config('performance.use_db_transactions'),
                    'nfr'   => 'NFR #8 ACID Transactions',
                    'effect_when_false' => 'Partial writes possible (order without payment)',
                ],
                'use_eager_loading' => [
                    'value' => config('performance.use_eager_loading'),
                    'nfr'   => 'NFR #2 Resource Management',
                    'effect_when_false' => 'N+1 queries — DB load multiplies with cart size',
                ],
                'use_async_jobs' => [
                    'value' => config('performance.use_async_jobs'),
                    'nfr'   => 'NFR #3 Async Queue Processing',
                    'effect_when_false' => 'Checkout blocks waiting for invoice generation',
                ],
                'use_batch_processing' => [
                    'value' => config('performance.use_batch_processing'),
                    'nfr'   => 'NFR #4 Batch Processing',
                    'effect_when_false' => 'Reports run synchronously, blocking the thread',
                ],
                'use_rate_limiting' => [
                    'value' => config('performance.use_rate_limiting'),
                    'nfr'   => 'NFR #2 Resource Management',
                    'effect_when_false' => 'No request throttling — server can be overwhelmed',
                ],
                'use_caching' => [
                    'value' => config('performance.use_caching'),
                    'nfr'   => 'NFR #6 Distributed Caching',
                    'effect_when_false' => 'Every request hits the database directly',
                ],
            ],
            'hint' => 'To test BAD scenario: set all flags to false in .env, run config:clear, then benchmark. '
                    . 'To test GOOD scenario: set all flags to true, run config:clear, then benchmark again.',
        ]);
    }

    public function full(): JsonResponse
    {
        $results = $this->benchmarkService->runFullBenchmark();

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }

    public function eager(): JsonResponse
    {
        $result = $this->benchmarkService->measureEagerLoadingImpact();

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function race(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'concurrent' => 'sometimes|integer|min:2|max:20',
        ]);

        $result = $this->benchmarkService->measureRaceConditionProtection(
            $request->integer('product_id'),
            $request->integer('concurrent', 5)
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function transaction(): JsonResponse
    {
        $result = $this->benchmarkService->measureTransactionIntegrity();

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    public function async(): JsonResponse
    {
        $result = $this->benchmarkService->measureAsyncImpact();

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }
}
