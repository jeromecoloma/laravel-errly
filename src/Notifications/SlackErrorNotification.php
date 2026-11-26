<?php

namespace Errly\LaravelErrly\Notifications;

use Errly\LaravelErrly\ErrorReport;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class SlackErrorNotification extends Notification
{
    protected ErrorReport $report;

    /**
     * Takes a report built once for every channel. An exception and context
     * are still accepted and turned into a report here.
     */
    public function __construct(ErrorReport|Throwable $report, array $context = [])
    {
        $this->report = $report instanceof ErrorReport
            ? $report
            : ErrorReport::fromException($report, $context);
    }

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function getReport(): ErrorReport
    {
        return $this->report;
    }

    public function getException(): Throwable
    {
        return $this->report->exception;
    }

    public function getContext(): array
    {
        return $this->report->context;
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
        return $this->report->fields;
    }

    protected function getSeverityLevel(): string
    {
        return $this->report->severity;
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
        return $this->report->fields['File'];
    }

    protected function getFormattedStackTrace(): ?string
    {
        return $this->report->trace;
    }
}
