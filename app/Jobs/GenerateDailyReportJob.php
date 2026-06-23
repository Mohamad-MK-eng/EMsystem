<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SalesReport;
use App\Services\SalesReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;


class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    private const CHUNK_SIZE = 50;

    public function __construct(private readonly string $date)
    {
    }

    public function handle(): void
    {
        $date = $this->date;

        Log::info("[GenerateDailyReportJob] Starting batch report for {$date}");

        $report = SalesReport::updateOrCreate(
            ['report_date' => $date],
            [
                'status'              => 'processing',
                'total_orders'        => 0,
                'total_revenue'       => 0,
                'average_order_value' => 0,
                'error_message'       => null,
            ]
        );

        $orderIds = Order::where('status', 'paid')
            ->whereDate('created_at', $date)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            $report->update([
                'status'       => 'completed',
                'processed_at' => now(),
            ]);
            Log::info("[GenerateDailyReportJob] No paid orders for {$date}. Done.");
            return;
        }

        $chunks    = array_chunk($orderIds, self::CHUNK_SIZE);
        $chunkJobs = [];

        foreach ($chunks as $index => $chunkIds) {
            $chunkJobs[] = new ProcessOrderChunkJob(
                $date,
                $chunkIds,
                $index + 1,
                count($chunks)
            );
        }

        Bus::batch($chunkJobs)
            ->name("daily-report-{$date}")
            ->then(function () use ($date) {
                app(SalesReportService::class)->finalizeReportForDate($date);
            })
            ->catch(function (\Throwable $e) use ($date) {
                Log::error("[GenerateDailyReportJob] Batch failed: " . $e->getMessage());
                app(SalesReportService::class)->markReportFailed($date, $e->getMessage());
            })
            ->finally(function () use ($date) {
                Log::info("[GenerateDailyReportJob] Batch finished for {$date}.");
            })
            ->dispatch();

        Log::info(
            "[GenerateDailyReportJob] Dispatched " . count($chunkJobs)
            . " chunk jobs for {$date} (" . count($orderIds) . " orders)"
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            "[GenerateDailyReportJob] JOB FAILED for {$this->date}: "
            . $exception->getMessage()
        );

        app(SalesReportService::class)->markReportFailed(
            $this->date,
            $exception->getMessage()
        );
    }
}
