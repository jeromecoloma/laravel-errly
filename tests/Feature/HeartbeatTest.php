<?php

use Errly\LaravelErrly\Commands\TestHeartbeatCommand;
use Errly\LaravelErrly\Heartbeat\Clients\HealthchecksClient;
use Errly\LaravelErrly\Heartbeat\HeartbeatClient;
use Errly\LaravelErrly\Heartbeat\HeartbeatException;
use Errly\LaravelErrly\Heartbeat\HeartbeatManager;
use Errly\LaravelErrly\Heartbeat\HeartbeatSchedule;
use Errly\LaravelErrly\Heartbeat\SendHeartbeat;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Pings go through a private HTTP factory that the Http facade cannot
    // fake, so every test swaps in a faked one that refuses real requests.
    $this->http = $http = (new Factory)->preventStrayRequests();

    app(HeartbeatManager::class)->extend(
        'healthchecks',
        fn () => new HealthchecksClient(config('errly.heartbeat.clients.healthchecks'), $http),
    );
});

function heartbeatSchedule(): Schedule
{
    $schedule = new Schedule;
    HeartbeatSchedule::register($schedule);

    return $schedule;
}

function heartbeatEvent(string $name): Event
{
    return collect(heartbeatSchedule()->events())
        ->sole(fn (Event $event): bool => $event->description === $name);
}

test('nothing is scheduled until a check is configured', function () {
    expect(heartbeatSchedule()->events())->toBe([]);
});

test('nothing is scheduled when heartbeats or Errly are disabled', function (string $switch) {
    config([
        $switch => false,
        'errly.heartbeat.scheduler' => 'https://monitor.test/scheduler',
        'errly.heartbeat.queues.default' => 'https://monitor.test/default',
    ]);

    expect(heartbeatSchedule()->events())->toBe([]);
})->with(['errly.heartbeat.enabled', 'errly.enabled']);

test('a queued heartbeat sends nothing once Errly is disabled', function () {
    config([
        'errly.heartbeat.queues.default' => 'https://monitor.test/worker',
        'errly.enabled' => false,
    ]);
    $this->http->fake();

    app()->call([new SendHeartbeat('default'), 'handle']);

    $this->http->assertNothingSent();
});

test('the service provider adds heartbeats to the app schedule', function () {
    config(['errly.heartbeat.scheduler' => 'https://monitor.test/scheduler']);
    app()->forgetInstance(Schedule::class);

    $descriptions = collect(app(Schedule::class)->events())->pluck('description');

    expect($descriptions)->toContain('errly:heartbeat:scheduler');
});

test('the scheduler pings inline every minute', function () {
    config(['errly.heartbeat.scheduler' => 'https://monitor.test/scheduler']);
    $this->http->fake(['https://monitor.test/scheduler' => Http::response('OK')]);
    Queue::fake();
    $event = heartbeatEvent('errly:heartbeat:scheduler');

    $event->run(app());

    expect($event->expression)->toBe('* * * * *');
    $this->http->assertSentCount(1);
    Queue::assertNothingPushed();
});

test('each queue heartbeat is queued every minute on the queue it vouches for', function (string $queue, ?string $pushedOn) {
    config(["errly.heartbeat.queues.{$queue}" => 'https://monitor.test/worker']);
    Queue::fake([SendHeartbeat::class]);
    $event = heartbeatEvent("errly:heartbeat:queue:{$queue}");

    $event->run(app());

    expect($event->expression)->toBe('* * * * *');
    Queue::assertPushed(
        SendHeartbeat::class,
        fn (SendHeartbeat $job, ?string $queueName): bool => $job->heartbeat === $queue && $queueName === $pushedOn,
    );
})->with([
    'default goes to the connection default' => ['default', null],
    'a named queue' => ['provisioning', 'provisioning'],
]);

test('a queue heartbeat goes to the configured connection', function () {
    config([
        'errly.heartbeat.queues.default' => 'https://monitor.test/worker',
        'errly.heartbeat.queue_connection' => 'redis',
    ]);
    Queue::fake([SendHeartbeat::class]);

    heartbeatEvent('errly:heartbeat:queue:default')->run(app());

    Queue::assertPushed(SendHeartbeat::class, fn (SendHeartbeat $job): bool => $job->connection === 'redis');
});

test('a dead worker collects one waiting heartbeat, not one a minute', function () {
    config(['errly.heartbeat.queues.default' => 'https://monitor.test/worker']);
    Queue::fake([SendHeartbeat::class]);
    $event = heartbeatEvent('errly:heartbeat:queue:default');

    $event->run(app());
    $event->run(app());
    $event->run(app());

    Queue::assertPushed(SendHeartbeat::class, 1);
});

test('two queue heartbeats do not share a lock', function () {
    config([
        'errly.heartbeat.queues.default' => 'https://monitor.test/default',
        'errly.heartbeat.queues.provisioning' => 'https://monitor.test/provisioning',
    ]);
    Queue::fake([SendHeartbeat::class]);

    heartbeatEvent('errly:heartbeat:queue:default')->run(app());
    heartbeatEvent('errly:heartbeat:queue:provisioning')->run(app());

    Queue::assertPushed(SendHeartbeat::class, 2);
});

test('a queued heartbeat pings the check it reads from config when it runs', function () {
    config(['errly.heartbeat.queues.default' => 'https://monitor.test/old']);
    $job = new SendHeartbeat('default');
    config(['errly.heartbeat.queues.default' => 'https://monitor.test/new']);
    $this->http->fake(['https://monitor.test/new' => Http::response('OK')]);

    app()->call([$job, 'handle']);

    $this->http->assertSent(fn ($request): bool => $request->url() === 'https://monitor.test/new');
});

test('a queued heartbeat whose check was blanked sends nothing', function () {
    config(['errly.heartbeat.queues.default' => null]);
    $this->http->fake();

    app()->call([new SendHeartbeat('default'), 'handle']);

    $this->http->assertNothingSent();
});

test('an unreachable monitor does not fail the heartbeat', function () {
    config([
        'errly.heartbeat.queues.default' => 'https://monitor.test/worker',
    ]);
    $this->http->fake(['https://monitor.test/worker' => Http::failedConnection()]);

    app()->call([new SendHeartbeat('default'), 'handle']);

    $this->http->assertSentCount(1);
});

test('the default client is Healthchecks and others can be added', function () {
    $manager = app(HeartbeatManager::class);

    expect($manager->driver())->toBeInstanceOf(HealthchecksClient::class);

    $custom = new class implements HeartbeatClient
    {
        public array $pinged = [];

        public function ping(string $check): void
        {
            $this->pinged[] = $check;
        }
    };
    $manager->extend('custom', fn () => $custom);
    config(['errly.heartbeat.client' => 'custom']);

    $manager->ping('anything');

    expect($custom->pinged)->toBe(['anything']);
});

test('the Healthchecks client builds a URL from each kind of check', function (array $config, string $check, string $url) {
    expect((new HealthchecksClient($config))->url($check))->toBe($url);
})->with([
    'full URL' => [[], 'https://hc.example.com/ping/abc', 'https://hc.example.com/ping/abc'],
    'UUID' => [[], '5bf66975-d4c7-4bf5-bcc8-b8d8a82ea278', 'https://hc-ping.com/5bf66975-d4c7-4bf5-bcc8-b8d8a82ea278'],
    'slug' => [['ping_key' => 'key123'], 'app-scheduler', 'https://hc-ping.com/key123/app-scheduler'],
    'slug, auto provisioned' => [['ping_key' => 'key123', 'auto_provision' => true], 'app-scheduler', 'https://hc-ping.com/key123/app-scheduler?create=1'],
    'self hosted' => [['url' => 'https://hc.example.com/ping/'], '5bf66975-d4c7-4bf5-bcc8-b8d8a82ea278', 'https://hc.example.com/ping/5bf66975-d4c7-4bf5-bcc8-b8d8a82ea278'],
]);

test('a slug without a ping key is refused', function () {
    (new HealthchecksClient([]))->url('app-scheduler');
})->throws(HeartbeatException::class, 'ERRLY_HEALTHCHECKS_PING_KEY');

test('Healthchecks answers that were not recorded are failures', function ($response) {
    $this->http->fake(['https://monitor.test/*' => $response]);

    (new HealthchecksClient([], $this->http))->ping('https://monitor.test/check');
})->with([
    'unknown UUID' => fn () => Http::response('OK (not found)'),
    'rate limited' => fn () => Http::response('OK (rate limited)'),
    'unknown slug' => fn () => Http::response('not found', 404),
    'ambiguous slug' => fn () => Http::response('ambiguous slug', 409),
])->throws(HeartbeatException::class);

test('Healthchecks retries server errors but not client errors', function (int $status, int $sent) {
    $this->http->fake(['https://monitor.test/*' => Http::response('', $status)]);

    rescue(fn () => (new HealthchecksClient(['attempts' => 3, 'retry_delay_ms' => 0], $this->http))->ping('https://monitor.test/check'), report: false);

    $this->http->assertSentCount($sent);
})->with([
    'server error' => [503, 3],
    'rate limited' => [429, 3],
    'not found' => [404, 1],
]);

test('failure messages never contain the ping key or ping URL', function ($response) {
    $this->http->fake(['https://hc-ping.com/*' => $response]);
    $client = new HealthchecksClient(['ping_key' => 'secret-key'], $this->http);

    try {
        $client->ping('app-scheduler');
    } catch (HeartbeatException $e) {
        expect($e->getMessage())->not->toContain('secret-key')->not->toContain('hc-ping.com');

        return;
    }

    $this->fail('The ping should have failed.');
})->with([
    'connection error' => fn () => Http::failedConnection('cURL error 28 for https://hc-ping.com/secret-key/app-scheduler'),
    'response body' => fn () => Http::response('bad key secret-key', 400),
]);

test('pings are not seen by tools that watch the app HTTP client', function () {
    app(HeartbeatManager::class)->forgetDrivers();
    $client = (new HeartbeatManager(app()))->driver('healthchecks');

    $http = (fn () => $this->http)->call($client);

    expect($http)->not->toBe(app(Factory::class))
        ->and($http->getDispatcher())->toBeNull();
});

test('the test command pings each configured check', function () {
    config([
        'errly.heartbeat.scheduler' => 'https://monitor.test/scheduler',
        'errly.heartbeat.queues.default' => 'https://monitor.test/default',
    ]);
    $this->http->fake([
        'https://monitor.test/scheduler' => Http::response('OK'),
        'https://monitor.test/default' => Http::response('OK (not found)'),
    ]);

    $this->artisan('errly:heartbeat-test')
        ->expectsOutputToContain('OK     scheduler')
        ->expectsOutputToContain('FAILED queue:default')
        ->assertExitCode(1);

    $this->http->assertSentCount(2);
});

test('the test command fails when nothing is configured', function () {
    $this->artisan('errly:heartbeat-test')
        ->expectsOutput('No heartbeats are configured.')
        ->assertExitCode(1);
});

// cPanel/CloudLinux cron can run the CGI PHP build, where runningInConsole() is false.
function bootOutsideConsole(object $test): void
{
    $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';
    ConsoleApplication::forgetBootstrappers();

    try {
        (fn () => $this->refreshApplication())->call($test);
    } finally {
        unset($_SERVER['APP_RUNNING_IN_CONSOLE']);
    }
}

test('heartbeats are scheduled when PHP is not the CLI build', function () {
    bootOutsideConsole($this);
    config(['errly.heartbeat.scheduler' => 'https://monitor.test/scheduler']);

    expect(app()->runningInConsole())->toBeFalse()
        ->and(collect(app(Schedule::class)->events())->pluck('description'))->toContain('errly:heartbeat:scheduler');
});

test('nothing is scheduled outside the CLI build until a check is configured', function () {
    bootOutsideConsole($this);

    expect(app(Schedule::class)->events())->toBe([]);
});

test('the commands are registered when PHP is not the CLI build', function () {
    bootOutsideConsole($this);

    expect(app()->runningInConsole())->toBeFalse()
        ->and(Artisan::all())->toHaveKeys(['errly:test', 'errly:heartbeat-test']);
});

test('the test command warns when PHP is not the CLI build, and still pings', function (string $sapi, bool $warns) {
    config(['errly.heartbeat.scheduler' => 'https://monitor.test/scheduler']);
    $this->http->fake(['https://monitor.test/scheduler' => Http::response('OK')]);
    app()->bind(TestHeartbeatCommand::class, fn () => new class($sapi) extends TestHeartbeatCommand
    {
        public function __construct(private string $sapi)
        {
            parent::__construct();
        }

        protected function phpSapi(): string
        {
            return $this->sapi;
        }
    });

    $warning = "Running under the {$sapi} PHP build, not the CLI.";
    $command = $this->artisan('errly:heartbeat-test');
    $warns ? $command->expectsOutputToContain($warning) : $command->doesntExpectOutputToContain($warning);
    $command->expectsOutputToContain('OK     scheduler')->assertExitCode(0)->run();

    $this->http->assertSentCount(1);
})->with([
    'cgi' => ['cgi-fcgi', true],
    'cli' => ['cli', false],
]);
