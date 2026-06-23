<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class ProcessOrderChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly string $date,
        private readonly array  $orderIds,
        private readonly int    $chunkNumber,
        private readonly int    $totalChunks
    ) {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            Log::warning("[ProcessOrderChunkJob] Batch cancelled — skipping chunk {$this->chunkNumber}.");
            return;
        }

        Log::info(
            "[ProcessOrderChunkJob] Processing chunk {$this->chunkNumber}/{$this->totalChunks} "
            . "for {$this->date} — " . count($this->orderIds) . " orders."
        );

        $result = Order::whereIn('id', $this->orderIds)
            ->selectRaw('COUNT(*) as chunk_orders, SUM(total_price) as chunk_revenue')
            ->first();

        $chunkOrders  = (int)   ($result->chunk_orders  ?? 0);
        $chunkRevenue = (float) ($result->chunk_revenue ?? 0);

        $cacheKey = "report_chunk:{$this->date}:{$this->chunkNumber}";

        Cache::put($cacheKey, [
            'orders'  => $chunkOrders,
            'revenue' => $chunkRevenue,
        ], now()->addHours(2));

        Log::info(
            "[ProcessOrderChunkJob] Chunk {$this->chunkNumber}/{$this->totalChunks} done. "
            . "orders={$chunkOrders}, revenue={$chunkRevenue}. "
            . "Saved to cache key: {$cacheKey}"
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            "[ProcessOrderChunkJob] CHUNK {$this->chunkNumber} FAILED for {$this->date}: "
            . $exception->getMessage()
        );

        Cache::forget("report_chunk:{$this->date}:{$this->chunkNumber}");
    }
}
