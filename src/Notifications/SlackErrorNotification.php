<?php

namespace Errly\LaravelErrly\Notifications;

use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class SlackErrorNotification extends Notification
{
    public function __construct(
        protected Throwable $exception,
        protected array $context = []
    ) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        $severity = $this->getSeverityLevel();
        $emoji = config('errly.slack.emoji', '🚨');
        $appName = config('errly.notifications.app_name', 'Laravel App');

        return (new SlackMessage)
            ->to(config('errly.slack.channel'))
            ->from(config('errly.slack.username', 'Laravel Errly'))
            ->content("{$emoji} **{$severity} Error in {$appName}**")
            ->attachment(function (SlackAttachment $attachment) {
                $this->buildMainAttachment($attachment);
            })
            ->attachment(function (SlackAttachment $attachment) {
                $this->buildStackTraceAttachment($attachment);
            });
    }

    protected function buildMainAttachment(SlackAttachment $attachment): void
    {
        $attachment
            ->title('🔍 Error Details')
            ->color($this->getColorBySeverity())
            ->fields($this->getErrorFields())
            ->footer('Laravel Errly')
            ->timestamp(now());
    }

    protected function buildStackTraceAttachment(SlackAttachment $attachment): void
    {
        if (! config('errly.context.include_stack_trace')) {
            return;
        }

        $stackTrace = $this->getFormattedStackTrace();
        if (! $stackTrace) {
            return;
        }

        $attachment
            ->title('📋 Stack Trace')
            ->content("```{$stackTrace}```")
            ->color('warning');
    }

    protected function getErrorFields(): array
    {
        return [
            'Exception' => get_class($this->exception),
            'Message' => $this->exception->getMessage() ?: 'No message provided',
            'File' => $this->getFormattedFilePath(),
            'Line' => $this->exception->getLine(),
            'URL' => $this->context['request']['url'] ?? 'N/A',
            'Method' => $this->context['request']['method'] ?? 'N/A',
            'User' => $this->getUserInfo(),
            'Environment' => $this->context['environment'] ?? 'Unknown',
            'Server' => $this->context['server'] ?? 'Unknown',
        ];
    }

    protected function getSeverityLevel(): string
    {
        $criticalExceptions = config('errly.filters.critical_exceptions', []);

        foreach ($criticalExceptions as $critical) {
            if ($this->exception instanceof $critical) {
                return 'CRITICAL';
            }
        }

        if (method_exists($this->exception, 'getStatusCode') && $this->exception->getStatusCode() >= 500) {
            return 'HIGH';
        }

        return 'MEDIUM';
    }

    protected function getColorBySeverity(): string
    {
        $colors = config('errly.notifications.colors', []);

        return match ($this->getSeverityLevel()) {
            'CRITICAL' => $colors['critical'] ?? 'danger',
            'HIGH' => $colors['high'] ?? 'warning',
            'MEDIUM' => $colors['medium'] ?? '#ff9500',
            default => $colors['low'] ?? 'good'
        };
    }

    protected function getFormattedFilePath(): string
    {
        $file = $this->exception->getFile();

        return str_replace(base_path(), '', $file);
    }

    protected function getUserInfo(): string
    {
        if (! isset($this->context['user'])) {
            return 'Anonymous';
        }

        $user = $this->context['user'];
        $info = 'ID: '.($user['id'] ?? 'Unknown');

        if (isset($user['email'])) {
            $info .= ' ('.$user['email'].')';
        }

        return $info;
    }

    protected function getFormattedStackTrace(): ?string
    {
        $trace = $this->exception->getTraceAsString();
        $maxLength = config('errly.context.max_stack_trace_length', 2000);

        if (strlen($trace) > $maxLength) {
            $trace = substr($trace, 0, $maxLength)."\n... (truncated)";
        }

        return $trace;
    }
}
