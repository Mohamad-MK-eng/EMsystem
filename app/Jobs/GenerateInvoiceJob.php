<?php

namespace App\Jobs;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $tries = 3;

    public int $backoff = 7;

    public function __construct(public readonly Order $order)
    {
    }


    public function handle(): void
    {
        Log::info("[GenerateInvoiceJob] Starting for Order #{$this->order->id}");

        usleep(rand(100, 300) * 5000);

        $order = $this->order->load(['items.product', 'user', 'payment']);

        $pdf = Pdf::loadView('invoices.order', [
            'order'       => $order,
            'invoiceDate' => now('GMT+3'),
        ])->setPaper('a4');

        $filename = "invoices/invoice_order_{$this->order->id}.pdf";
        Storage::put($filename, $pdf->output());

        Log::info("[GenerateInvoiceJob] Invoice saved → {$filename} for Order #{$this->order->id}");
    }


    public function failed(\Throwable $exception): void
    {
        Log::error(
            "[GenerateInvoiceJob] FAILED for Order #{$this->order->id}: "
            . $exception->getMessage()
        );
    }
}
