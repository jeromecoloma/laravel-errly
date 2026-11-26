<?php

namespace Errly\LaravelErrly\Channels;

use Errly\LaravelErrly\ErrorReport;
use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Illuminate\Support\Facades\Notification;

class SlackChannel implements Channel
{
    public function send(ErrorReport $report): void
    {
        $webhookUrl = config('errly.slack.webhook_url');

        if (! $webhookUrl) {
            return;
        }

        Notification::route('slack', $webhookUrl)
            ->notify(new SlackErrorNotification($report));
    }
}
