<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Actualizar estado de documentos diariamente a las 6:00 AM
Schedule::command('documentos:actualizar-estado')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();
