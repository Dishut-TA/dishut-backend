<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Siklus rehabilitasi P0-P4.
 *
 * Program yang siklus monitoringnya sudah tuntas dinaikkan ke periode
 * berikutnya setahun kemudian. Dicek harian karena tanggal selesainya tiap
 * program berbeda-beda. Perlu `php artisan schedule:work` atau entri cron
 * `php artisan schedule:run` di server agar berjalan.
 */
Schedule::command('program:naikkan-periode')
    ->dailyAt('01:00')
    ->withoutOverlapping();
