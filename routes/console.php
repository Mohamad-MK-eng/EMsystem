<?php

use App\Jobs\GenerateDailyReportJob;
use Illuminate\Support\Facades\Schedule;


Schedule::command('report:daily')
    ->dailyAt('11:21')
    ->name('generate-daily-sales-report')
    ->withoutOverlapping() 
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));


Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->name('prune-old-failed-jobs');
