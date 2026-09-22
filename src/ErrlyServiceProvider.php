<?php

namespace Errly\LaravelErrly;

use Errly\LaravelErrly\Commands\TestErrorCommand;
use Errly\LaravelErrly\Commands\TestHeartbeatCommand;
use Errly\LaravelErrly\Heartbeat\HeartbeatManager;
use Errly\LaravelErrly\Heartbeat\HeartbeatSchedule;
use Errly\LaravelErrly\Services\ErrorReportingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\ServiceProvider;

class ErrlyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/errly.php',
            'errly'
        );

        $this->app->singleton(ErrorReportingService::class);
        $this->app->singleton(HeartbeatManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/errly.php' => config_path('errly.php'),
            ], 'laravel-errly-config');

            $this->commands([
                TestErrorCommand::class,
                TestHeartbeatCommand::class,
            ]);

            $this->callAfterResolving(Schedule::class, HeartbeatSchedule::register(...));
        }
    }

    public static function configureExceptions(Exceptions $exceptions): void
    {
        if (! config('errly.enabled', true)) {
            return;
        }

        app(ErrorReportingService::class)->configure($exceptions);
    }
}
