<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Heartbeat for /health/ready and the admin dashboard.
Schedule::call(function () {
    Cache::forever('mtc:scheduler:heartbeat', now()->toIso8601String());
    Cache::forever('mtc:task:heartbeat', now()->toIso8601String());
})->everyMinute()->name('heartbeat');

Schedule::command('matocenu:prune')->dailyAt('03:30')->timezone('Europe/Prague')->onOneServer();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

foreach (glob(__DIR__.'/schedule/*.php') as $file) {
    require $file;
}
