<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime   = microtime(true);
        $startMemory = memory_get_usage(true);

        if (config('app.debug')) {
            DB::enableQueryLog();
        }

        $response = $next($request);

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsed   = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);
        $queryCount   = config('app.debug') ? count(DB::getQueryLog()) : 'N/A (debug off)';

        $response->headers->set('X-Response-Time-Ms', $responseTime);
        $response->headers->set('X-Memory-MB', $memoryUsed);
        $response->headers->set('X-Query-Count', $queryCount);
        $response->headers->set('X-NFR-Flags', $this->getActiveFlags());

        $logLevel = $responseTime > 500 ? 'warning' : 'info';

        Log::$logLevel('[PerformanceMonitor]', [
            'method'        => $request->method(),
            'path'          => $request->path(),
            'response_ms'   => $responseTime,
            'memory_mb'     => $memoryUsed,
            'query_count'   => $queryCount,
            'status'        => $response->getStatusCode(),
            'user_id'       => $request->user()?->id,
            'nfr_flags'     => $this->getActiveFlags(),
        ]);

        return $response;
    }

    private function getActiveFlags(): string
    {
        $flags = [
            'locking='      . (config('performance.use_optimistic_locking') ? '1' : '0'),
            'tx='           . (config('performance.use_db_transactions')    ? '1' : '0'),
            'eager='        . (config('performance.use_eager_loading')      ? '1' : '0'),
            'async='        . (config('performance.use_async_jobs')         ? '1' : '0'),
            'batch='        . (config('performance.use_batch_processing')   ? '1' : '0'),
            'cache='        . (config('performance.use_caching')            ? '1' : '0'),
            'rate_limit='   . (config('performance.use_rate_limiting')      ? '1' : '0'),
            'pagination='   . (config('performance.use_pagination')         ? '1' : '0'),
        ];

        return implode(',', $flags);
    }
}
