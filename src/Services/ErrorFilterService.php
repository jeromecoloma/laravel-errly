<?php

namespace Errly\LaravelErrly\Services;

use Throwable;

class ErrorFilterService
{
    public function shouldReport(Throwable $exception): bool
    {
        if (! config('errly.enabled')) {
            return false;
        }

        if (! $this->isEnvironmentAllowed()) {
            return false;
        }

        if ($this->isIgnoredException($exception)) {
            return false;
        }

        // Report critical exceptions with high priority
        if ($this->isCriticalException($exception)) {
            return true;
        }

        // Report server errors (HTTP 500+)
        if ($this->isServerError($exception)) {
            return true;
        }

        // Report all other exceptions by default (unless specifically ignored)
        // This catches RuntimeException, Exception, etc.
        return true;
    }

    protected function isEnvironmentAllowed(): bool
    {
        if (! config('errly.filters.environments.enabled')) {
            return true;
        }

        $allowedEnvironments = config('errly.filters.environments.allowed', []);

        return in_array(app()->environment(), $allowedEnvironments);
    }

    protected function isIgnoredException(Throwable $exception): bool
    {
        $ignoredExceptions = config('errly.filters.ignored_exceptions', []);

        foreach ($ignoredExceptions as $ignoredException) {
            if ($exception instanceof $ignoredException) {
                return true;
            }
        }

        return false;
    }

    protected function isCriticalException(Throwable $exception): bool
    {
        $criticalExceptions = config('errly.filters.critical_exceptions', []);

        foreach ($criticalExceptions as $criticalException) {
            if ($exception instanceof $criticalException) {
                return true;
            }
        }

        return false;
    }

    protected function isServerError(Throwable $exception): bool
    {
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = call_user_func([$exception, 'getStatusCode']);

            return $statusCode >= 500;
        }

        return false;
    }
}
