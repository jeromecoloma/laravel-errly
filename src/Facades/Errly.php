<?php

namespace Errly\LaravelErrly\Facades;

use Errly\LaravelErrly\Services\ErrorReportingService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void report(\Throwable $e, array $context = [])
 * @method static void handleException(\Throwable $e)
 *
 * @see \Errly\LaravelErrly\Services\ErrorReportingService
 */
class Errly extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ErrorReportingService::class;
    }
}
