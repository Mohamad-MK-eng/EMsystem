<?php

use App\Jobs\GenerateDailyReportJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — NFR #4: Batch Processing مجدوَل
|--------------------------------------------------------------------------
|
| الـ Scheduler هو الطرف الآخر لـ NFR #4:
| بدلاً من إطلاق التقرير يدوياً بعد كل عملية شراء،
| يُطلَق تلقائياً كل يوم في منتصف الليل.
|
| لتشغيل الـ Scheduler في التطوير:
|   php artisan schedule:run        (مرة واحدة)
|   php artisan schedule:work       (يعمل باستمرار)
|
| لتشغيله في الإنتاج — أضف في crontab:
|   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
*/

// ── التقرير اليومي ────────────────────────────────────────────────────────
// NFR #4: يُطلق GenerateDailyReportJob كل يوم في 00:05
// نختار 00:05 وليس 00:00 لأن الطلبات الأخيرة لليوم السابق
// تحتاج بضع ثوانٍ لتكتمل في قاعدة البيانات.

Schedule::command('report:daily')
    ->dailyAt('11:21')
    ->name('generate-daily-sales-report')
    ->withoutOverlapping() // لا تشغيل مزدوج اذا تأخر الطلب السابق
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));


Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->name('prune-old-failed-jobs');
