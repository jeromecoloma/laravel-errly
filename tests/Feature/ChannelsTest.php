<?php

use Errly\LaravelErrly\Channels\Channel;
use Errly\LaravelErrly\Channels\ChannelManager;
use Errly\LaravelErrly\Channels\SlackChannel;
use Errly\LaravelErrly\ErrorReport;
use Errly\LaravelErrly\Facades\Errly;
use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'errly.enabled' => true,
        'errly.filters.environments.enabled' => false,
        'errly.rate_limiting.enabled' => false,
        'errly.slack.webhook_url' => 'https://hooks.slack.com/test',
    ]);

    Notification::fake();
});

function recordingChannel(): Channel
{
    return new class implements Channel
    {
        /** @var list<ErrorReport> */
        public array $reports = [];

        public function send(ErrorReport $report): void
        {
            $this->reports[] = $report;
        }
    };
}

test('slack is the default channel', function () {
    expect(config('errly.channels'))->toBe(['slack'])
        ->and(app(ChannelManager::class)->driver('slack'))->toBeInstanceOf(SlackChannel::class);
});

test('channels can be set from a comma separated env value', function () {
    putenv('ERRLY_CHANNELS=slack, custom');
    $config = require __DIR__.'/../../config/errly.php';
    putenv('ERRLY_CHANNELS');

    expect(array_values($config['channels']))->toBe(['slack', 'custom']);
});

test('every configured channel gets the same report', function () {
    $custom = recordingChannel();
    app(ChannelManager::class)->extend('custom', fn () => $custom);
    config(['errly.channels' => ['slack', 'custom']]);

    $exception = new RuntimeException('Boom');
    Errly::report($exception);

    expect($custom->reports)->toHaveCount(1)
        ->and($custom->reports[0]->exception)->toBe($exception);

    Notification::assertSentOnDemand(
        SlackErrorNotification::class,
        fn (SlackErrorNotification $notification) => $notification->getReport() === $custom->reports[0],
    );
});

test('slack is not sent when it is left out of the channels', function () {
    $custom = recordingChannel();
    app(ChannelManager::class)->extend('custom', fn () => $custom);
    config(['errly.channels' => ['custom']]);

    Errly::report(new RuntimeException('Boom'));

    expect($custom->reports)->toHaveCount(1);
    Notification::assertNothingSent();
});

test('a failing channel does not stop the others', function () {
    $custom = recordingChannel();
    $manager = app(ChannelManager::class);
    $manager->extend('broken', fn () => new class implements Channel
    {
        public function send(ErrorReport $report): void
        {
            throw new RuntimeException('channel down');
        }
    });
    $manager->extend('custom', fn () => $custom);
    config(['errly.channels' => ['broken', 'custom']]);

    Log::spy();

    Errly::report(new RuntimeException('Boom'));

    expect($custom->reports)->toHaveCount(1);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn (string $message, array $context) => $message === 'Errly: Failed to send error notification'
            && $context['notification_exception'] === 'channel down',
    );
});

test('an unknown channel is logged and the others still send', function () {
    config(['errly.channels' => ['missing', 'slack']]);

    Log::spy();

    Errly::report(new RuntimeException('Boom'));

    Notification::assertSentOnDemand(SlackErrorNotification::class);
    Log::shouldHaveReceived('error')->once();
});

test('no channels means nothing is sent', function () {
    config(['errly.channels' => []]);

    Errly::report(new RuntimeException('Boom'));

    Notification::assertNothingSent();
});

test('the report holds the severity, redacted message, fields and trace', function () {
    config(['errly.context.sensitive_fields' => ['token']]);

    $exception = new TypeError('token=secret-value');
    $report = ErrorReport::fromException($exception, [
        'environment' => 'production',
        'server' => 'web-1',
        'request' => ['url' => 'https://app.test/billing', 'method' => 'POST'],
        'user' => ['id' => 7, 'email' => 'jane@example.com'],
    ]);

    expect($report->severity)->toBe('CRITICAL')
        ->and($report->message)->not->toContain('secret-value')
        ->and($report->message)->toContain('[REDACTED]')
        ->and($report->fields)->toMatchArray([
            'Exception' => TypeError::class,
            'Message' => $report->message,
            'Line' => $exception->getLine(),
            'URL' => 'https://app.test/billing',
            'Method' => 'POST',
            'User' => 'ID: 7 (jane@example.com)',
            'Environment' => 'production',
            'Server' => 'web-1',
        ])
        ->and($report->fields['File'])->toEndWith('tests/Feature/ChannelsTest.php')
        ->and($report->trace)->not->toBeEmpty();
});
