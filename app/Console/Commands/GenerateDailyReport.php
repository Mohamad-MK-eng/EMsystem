<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateDailyReportJob;

class GenerateDailyReport extends Command
{
    protected $signature = 'report:daily';

    protected $description = 'Generate daily sales report';

    public function handle(): int
    {
        $yesterday = now()->subDay()->toDateString();

        if (!config('performance.use_batch_processing')) {
            Log::info(
                "[Command] Batch processing disabled — skipping daily report for {$yesterday}"
            );

            $this->warn('Batch processing disabled.');

            return self::SUCCESS;
        }

        Log::info(
            "[Command] Dispatching daily report job for {$yesterday}"
        );

        GenerateDailyReportJob::dispatch($yesterday);

        $this->info("Daily report dispatched for {$yesterday}");

        return self::SUCCESS;
    }
}
