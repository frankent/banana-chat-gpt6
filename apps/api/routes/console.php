<?php

use Illuminate\Support\Facades\Schedule;

Schedule::job(new \App\Jobs\PurgeExpiredUploads)->hourly();
Schedule::job(new \App\Jobs\EnforceRetention)->dailyAt('02:15');

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
