<?php

namespace Errly\LaravelErrly;

use Errly\LaravelErrly\Commands\TestErrorCommand;
use Errly\LaravelErrly\Services\ErrorReportingService;
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/errly.php' => config_path('errly.php'),
            ], 'laravel-errly-config');

            $this->commands([
                TestErrorCommand::class,
            ]);
        }
    }

    public static function configureExceptions(Exceptions $exceptions): void
    {
        if (!config('errly.enabled', true)) {
            return;
        }

        app(ErrorReportingService::class)->configure($exceptions);
    }
}
