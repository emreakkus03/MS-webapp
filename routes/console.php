<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Log::info('🔁 Keep-alive ping uitgevoerd op ' . now());
    try {
        file_get_contents(config('app.url') . '/ping');
    } catch (\Throwable $e) {
        Log::warning('⚠️ Keep-alive ping mislukt: ' . $e->getMessage());
    }
})->everyFiveMinutes();