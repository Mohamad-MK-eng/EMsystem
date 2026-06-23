<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SalesReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class UpdateDailySalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    private const CHUNK_SIZE = 50;

    public function __construct(private readonly string $date)
    {
    }

    public function handle(): void
    {
        Log::info("[UpdateDailySalesReportJob] Processing report for {$this->date}");

        $report = SalesReport::firstOrCreate(
            ['report_date' => $this->date],
            [
                'total_orders'        => 0,
                'total_revenue'       => 0,
                'average_order_value' => 0,
                'status'              => 'processing',
            ]
        );

        $report->update(['status' => 'processing']);


        $totalOrders  = 0;
        $totalRevenue = 0.0;

        Order::where('status', 'paid')
            ->whereDate('created_at', $this->date)
            ->select(['id', 'total_price'])
            ->chunk(self::CHUNK_SIZE, function ($orders) use (&$totalOrders, &$totalRevenue) {
                // كل chunk = CHUNK_SIZE طلب محمّل في الذاكرة فقط
                $totalOrders  += $orders->count();
                $totalRevenue += $orders->sum('total_price');

                Log::debug(
                    "[UpdateDailySalesReportJob] Chunk processed: "
                    . "{$orders->count()} orders, subtotal: {$orders->sum('total_price')}"
                );
            });

        $average = $totalOrders > 0
            ? round($totalRevenue / $totalOrders, 2)
            : 0.0;


        $report->update([
            'total_orders'        => $totalOrders,
            'total_revenue'       => $totalRevenue,
            'average_order_value' => $average,
            'status'              => 'completed',
            'processed_at'        => now(),
        ]);

        Log::info(
            "[UpdateDailySalesReportJob] Done for {$this->date}: "
            . "orders={$totalOrders}, revenue={$totalRevenue}, avg={$average}"
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            "[UpdateDailySalesReportJob] FAILED for {$this->date}: "
            . $exception->getMessage()
        );

        SalesReport::where('report_date', $this->date)
            ->update([
                'status'        => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
    }
}
