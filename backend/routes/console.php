<?php

use App\Jobs\AlerteExpirationDocumentsJob;
use App\Jobs\CloturerContratsExpiresJob;
use App\Jobs\GenererFacturesRecurrentesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new AlerteExpirationDocumentsJob)->dailyAt('06:00');
Schedule::job(new CloturerContratsExpiresJob)->dailyAt('00:05');
Schedule::job(new GenererFacturesRecurrentesJob)->dailyAt('01:00');
Schedule::command('vacations:generer-pool-siege')->dailyAt('00:10');
