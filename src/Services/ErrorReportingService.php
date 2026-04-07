<?php

namespace Errly\LaravelErrly\Services;

use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Errly\LaravelErrly\Support\SensitiveDataRedactor;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ErrorReportingService
{
    public function __construct(
        protected ErrorFilterService $filterService,
        protected ErrorContextService $contextService
    ) {}

    public function configure(Exceptions $exceptions): void
    {
        if (! config('errly.enabled')) {
            return;
        }

        // Custom exception reporting
        $exceptions->report(function (Throwable $e) {
            $this->handleException($e);
        });

        // Configure ignored exceptions
        $exceptions->dontReport(config('errly.filters.ignored_exceptions', []));

        // Set log levels for critical exceptions
        foreach (config('errly.filters.critical_exceptions', []) as $exception) {
            $exceptions->level($exception, 'critical');
        }

        // Prevent duplicate reports
        $exceptions->dontReportDuplicates();
    }

    public function handleException(Throwable $e): void
    {
        $this->reportException($e);
    }

    protected function shouldReport(Throwable $e): bool
    {
        return $this->filterService->shouldReport($e);
    }

    protected function isRateLimited(Throwable $e): bool
    {
        if (! config('errly.rate_limiting.enabled')) {
            return false;
        }

        $key = $this->getRateLimitKey($e);
        $maxPerMinute = config('errly.rate_limiting.max_per_minute', 10);

        $count = Cache::get($key, 0);

        if ($count >= $maxPerMinute) {
            return true;
        }

        Cache::put($key, $count + 1, 60); // 1 minute TTL

        return false;
    }

    protected function getRateLimitKey(Throwable $e): string
    {
        $prefix = config('errly.rate_limiting.cache_key_prefix', 'errly_rate_limit');

        return $prefix.':'.md5(get_class($e).$e->getMessage().$e->getFile().$e->getLine());
    }

    protected function sendSlackNotification(Throwable $e, array $context): void
    {
        $webhookUrl = config('errly.slack.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        Notification::route('slack', $webhookUrl)
            ->notify(new SlackErrorNotification($e, $context));
    }

    /**
     * Manually report an exception (can be used with Errly facade)
     */
    public function report(Throwable $e, array $context = []): void
    {
        $this->reportException($e, $context);
    }

    protected function reportException(Throwable $e, array $context = []): void
    {
        if (! config('errly.enabled') || ! $this->shouldReport($e) || $this->isRateLimited($e)) {
            return;
        }

        try {
            $baseContext = $this->contextService->gather();
            $mergedContext = empty($context) ? $baseContext : array_merge($baseContext, $context);

            $this->sendSlackNotification($e, $mergedContext);
        } catch (Throwable $notificationException) {
            $sensitiveFields = config('errly.context.sensitive_fields', []);

            logger()->error('Errly: Failed to send error notification', [
                'original_exception' => SensitiveDataRedactor::redactString($e->getMessage(), $sensitiveFields),
                'notification_exception' => SensitiveDataRedactor::redactString($notificationException->getMessage(), $sensitiveFields),
            ]);
        }
    }
}
