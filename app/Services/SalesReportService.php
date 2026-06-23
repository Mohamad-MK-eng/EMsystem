<?php

namespace App\Services;

use App\Jobs\GenerateDailyReportJob;
use App\Models\Order;
use App\Models\SalesReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SalesReportService
{
    // =========================================================================
    // GENERATE — تشغيل تقرير يوم معين
    // =========================================================================

    public function generateReport(string $date): SalesReport
    {
        Log::info("[SalesReportService] Manual report generation requested for {$date}");

        $report = SalesReport::updateOrCreate(
            ['report_date' => $date],
            [
                'status'              => 'pending',
                'total_orders'        => 0,
                'total_revenue'       => 0,
                'average_order_value' => 0,
                'error_message'       => null,
                'processed_at'        => null,
            ]
        );

        if (config('performance.use_batch_processing')) {
            // ✅ Flag ON — Batch متوازٍ عبر Bus::batch()
            GenerateDailyReportJob::dispatch($date);
            Log::info("[SalesReportService] Dispatched GenerateDailyReportJob (Batch mode) for {$date}");
        } else {
            // ⚠️ Flag OFF — تشغيل مباشر بدون batch لإظهار الفرق
            (new GenerateDailyReportJob($date))->handle();
            Log::info("[SalesReportService] Ran GenerateDailyReportJob synchronously (no-batch mode) for {$date}");
        }

        return $report->fresh();
    }

    // =========================================================================
    // READ — جلب التقارير
    // =========================================================================

    public function getReports(array $filters = [])
    {
        $query = SalesReport::query()->orderBy('report_date', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->betweenDates($filters['from'], $filters['to']);
        }

        if (!empty($filters['from']) && empty($filters['to'])) {
            $query->where('report_date', '>=', $filters['from']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    public function getReport(string $date): ?SalesReport
    {
        return SalesReport::where('report_date', $date)->first();
    }

    public function getSummary(string $from, string $to): array
    {
        $stats = SalesReport::completed()
            ->betweenDates($from, $to)
            ->selectRaw('
                COUNT(*) as report_count,
                SUM(total_orders) as total_orders,
                SUM(total_revenue) as total_revenue,
                AVG(average_order_value) as avg_order_value,
                MAX(total_revenue) as best_day_revenue,
                MIN(total_revenue) as worst_day_revenue
            ')
            ->first();

        return [
            'period'            => ['from' => $from, 'to' => $to],
            'report_count'      => (int)   ($stats->report_count    ?? 0),
            'total_orders'      => (int)   ($stats->total_orders     ?? 0),
            'total_revenue'     => (float) ($stats->total_revenue    ?? 0),
            'avg_order_value'   => round((float) ($stats->avg_order_value ?? 0), 2),
            'best_day_revenue'  => (float) ($stats->best_day_revenue ?? 0),
            'worst_day_revenue' => (float) ($stats->worst_day_revenue ?? 0),
        ];
    }

    // =========================================================================
    // RETRY — إعادة تشغيل التقارير الفاشلة
    // =========================================================================

    public function retryFailedReports(): int
    {
        $failedReports = SalesReport::failed()->get();
        $count         = 0;

        foreach ($failedReports as $report) {
            $this->generateReport($report->report_date->toDateString());
            $count++;
        }

        Log::info("[SalesReportService] Retried {$count} failed reports.");
        return $count;
    }

    // =========================================================================
    // FINALIZE — يُستدعى من then() في GenerateDailyReportJob
    //
    // التصحيح: لا تجلب من DB — تجمع نتائج الـ chunks من Cache فقط.
    // كل chunk حسب جزءه وحفظه بمفتاح خاص — هنا نجمع فقط.
    // =========================================================================

    public function finalizeReportForDate(string $date): void
    {
        Log::info("[SalesReportService] Finalizing report for {$date} from chunk cache...");

        $totalOrders  = 0;
        $totalRevenue = 0.0;
        $chunkNumber  = 1;
        $chunksRead   = 0;

        // نقرأ مفاتيح الـ chunks بالتسلسل حتى لا نجد المزيد
        while (true) {
            $cacheKey = "report_chunk:{$date}:{$chunkNumber}";
            $chunk    = Cache::get($cacheKey);

            if ($chunk === null) {
                break; // لا يوجد chunk بهذا الرقم — انتهت الـ chunks
            }

            $totalOrders  += (int)   $chunk['orders'];
            $totalRevenue += (float) $chunk['revenue'];
            $chunksRead++;

            Cache::forget($cacheKey); // تنظيف بعد القراءة
            $chunkNumber++;
        }

        // Fallback: إذا انتهت مدة Cache قبل وصول then()
        if ($chunksRead === 0) {
            Log::warning("[SalesReportService] No chunks in cache for {$date} — fallback to DB.");
            $this->finalizeFromDB($date);
            return;
        }
        $totalRevenue = round($totalRevenue, 2);
        $average = $totalOrders > 0
            ? round($totalRevenue / $totalOrders, 2)
            : 0.0;

        SalesReport::where('report_date', $date)->update([
            'total_orders'        => $totalOrders,
            'total_revenue'       => $totalRevenue,
            'average_order_value' => $average,
            'status'              => 'completed',
            'processed_at'        => now(),
        ]);

        Log::info(
            "[SalesReportService] Report finalized for {$date} from {$chunksRead} chunks: "
            . "orders={$totalOrders}, revenue={$totalRevenue}, avg={$average}"
        );
    }

    // =========================================================================
    // MARK FAILED — يُستدعى من catch() في GenerateDailyReportJob
    // =========================================================================

    public function markReportFailed(string $date, string $reason): void
    {
        SalesReport::where('report_date', $date)->update([
            'status'        => 'failed',
            'error_message' => $reason,
        ]);

        Log::error("[SalesReportService] Report marked as failed for {$date}: {$reason}");
    }

    // =========================================================================
    // PRIVATE — Fallback إذا انتهت مدة الـ Cache
    // =========================================================================

    private function finalizeFromDB(string $date): void
    {
        $stats = Order::where('status', 'paid')
            ->whereDate('created_at', $date)
            ->selectRaw('COUNT(*) as total_orders, SUM(total_price) as total_revenue')
            ->first();

        $totalOrders  = (int)   ($stats->total_orders  ?? 0);
        $totalRevenue = (float) ($stats->total_revenue ?? 0);
        $average      = $totalOrders > 0
            ? round($totalRevenue / $totalOrders, 2)
            : 0.0;

        SalesReport::where('report_date', $date)->update([
            'total_orders'        => $totalOrders,
            'total_revenue'       => $totalRevenue,
            'average_order_value' => $average,
            'status'              => 'completed',
            'processed_at'        => now(),
            'error_message'       => 'Finalized from DB fallback (cache expired).',
        ]);
    }
}
