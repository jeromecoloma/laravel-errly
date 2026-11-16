<?php

use Errly\LaravelErrly\App\Protocol;
use Errly\LaravelErrly\App\ReportBuilder;
use Errly\LaravelErrly\App\RetryAppReport;
use Errly\LaravelErrly\App\ServiceClient;
use Errly\LaravelErrly\ErrorReport;
use Errly\LaravelErrly\Facades\Errly;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

const TEST_KEY = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';
const TEST_CHANNEL = 'AAECAwQFBgcICQoLDA0ODw';

beforeEach(function () {
    config([
        'errly.enabled' => true,
        'errly.filters.environments.enabled' => false,
        'errly.rate_limiting.enabled' => false,
        'errly.channels' => ['app'],
        'errly.notifications.app_name' => 'client-portal',
        'errly.app.url' => 'https://errly.test',
        'errly.app.channel' => TEST_CHANNEL,
        'errly.app.send_token' => 'test-send-token',
        'errly.app.key' => TEST_KEY,
    ]);

    $this->http = $http = (new Factory)->preventStrayRequests();
    app()->bind(ServiceClient::class, fn ($app, array $params) => new ServiceClient($params['config'], $http));

    Queue::fake();
});

function acceptReports(Factory $http): void
{
    $http->fake(['errly.test/*' => $http->response(['id' => 'x', 'duplicate' => false, 'pushed' => true], 202)]);
}

/**
 * @return array<string, mixed>
 */
function openEnvelope(string $envelope, string $id, string $part): array
{
    $raw = Protocol::base64UrlDecode($envelope);
    $json = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
        substr($raw, 13),
        Protocol::aad(TEST_CHANNEL, $id, $part),
        substr($raw, 1, 12),
        Protocol::keys(TEST_KEY)['enc'],
    );

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

function sentReport(Factory $http): array
{
    $requests = $http->recorded();
    expect($requests)->toHaveCount(1);

    return $requests[0][0]->data();
}

test('a report is encrypted and posted to the channel', function () {
    acceptReports($this->http);

    $exception = new TypeError('token=secret-value broke');
    Errly::report($exception, [
        'server' => 'web-1',
        'user' => ['id' => 42, 'email' => 'jane@example.com'],
        'request' => ['url' => 'https://portal.test/invoices', 'method' => 'POST', 'ip' => '10.0.0.1'],
    ]);

    $this->http->assertSent(fn (Request $request) => $request->url() === 'https://errly.test/v1/channels/'.TEST_CHANNEL.'/reports'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-send-token'));

    $body = sentReport($this->http);
    $summary = openEnvelope($body['summary'], $body['id'], 'summary');
    $full = openEnvelope($body['full'], $body['id'], 'full');
    $location = ErrorReport::relativeFile($exception).':'.$exception->getLine();

    expect(array_keys($body))->toBe(['id', 'kind', 'fingerprint', 'summary', 'full'])
        ->and($body['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($body['kind'])->toBe('error')
        ->and($body['fingerprint'])->toBe(hash_hmac('sha256', TypeError::class.'|'.str_replace(':', '|', $location), Protocol::keys(TEST_KEY)['fp']))
        ->and($body['summary'])->not->toContain('secret-value')
        ->and($summary)->toMatchArray([
            'v' => 1,
            'id' => $body['id'],
            'kind' => 'error',
            'severity' => 'critical',
            'app' => 'client-portal',
            'environment' => 'testing',
            'title' => 'TypeError',
            'location' => $location,
            'suppressed' => 0,
        ])
        ->and($summary['message'])->toContain('[REDACTED]')->not->toContain('secret-value')
        ->and($summary['occurred_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and($full)->toMatchArray($summary + [
            'exception' => TypeError::class,
            'server' => 'web-1',
            'request' => ['url' => 'https://portal.test/invoices', 'method' => 'POST'],
            'user' => ['id' => 42, 'email' => 'jane@example.com'],
        ])
        ->and($full['trace'])->toBeString()->not->toBeEmpty();
});

test('the summary and full reports keep the field order of the protocol vectors', function () {
    acceptReports($this->http);
    $vectors = json_decode(file_get_contents(__DIR__.'/../fixtures/protocol-v1-vectors.json'), true);

    Errly::report(new RuntimeException('Boom'), [
        'server' => 'web-1',
        'user' => ['id' => 1],
        'request' => ['url' => 'https://portal.test', 'method' => 'GET'],
    ]);

    $body = sentReport($this->http);

    expect(array_keys(openEnvelope($body['summary'], $body['id'], 'summary')))
        ->toBe(array_keys(json_decode($vectors['summary_plaintext'], true)))
        ->and(array_keys(openEnvelope($body['full'], $body['id'], 'full')))
        ->toBe(array_keys(json_decode($vectors['full_plaintext'], true)));
});

test('personal data can be left out of app reports', function () {
    acceptReports($this->http);
    config(['errly.app.include_personal_data' => false]);

    Errly::report(new RuntimeException('Boom'), [
        'user' => ['id' => 42, 'email' => 'jane@example.com'],
        'request' => ['url' => 'https://portal.test/invoices', 'method' => 'POST'],
    ]);

    $body = sentReport($this->http);
    $full = openEnvelope($body['full'], $body['id'], 'full');

    expect($full)->not->toHaveKey('user')->not->toHaveKey('request');
});

test('nothing is sent until the app channel is paired', function (string $missing) {
    config(["errly.app.{$missing}" => null]);

    Errly::report(new RuntimeException('Boom'));

    $this->http->assertNothingSent();
})->with(['channel', 'send_token', 'key']);

test('long messages and traces are trimmed to fit the size caps', function () {
    $exception = new RuntimeException('Boom');
    $report = new ErrorReport(
        exception: $exception,
        context: ['environment' => 'production'],
        severity: 'HIGH',
        message: str_repeat('é', 5000),
        fields: [],
        trace: str_repeat("#0 /app/Some/Long/Path.php(1): call()\n", 3000),
    );

    $body = (new ReportBuilder(TEST_CHANNEL, Protocol::keys(TEST_KEY)))->build($report);
    $summary = openEnvelope($body['summary'], $body['id'], 'summary');
    $full = openEnvelope($body['full'], $body['id'], 'full');

    expect(strlen($body['summary']))->toBeLessThanOrEqual(ReportBuilder::SUMMARY_LIMIT)
        ->and(strlen($body['full']))->toBeLessThanOrEqual(ReportBuilder::FULL_LIMIT)
        ->and(strlen($body['full']))->toBeGreaterThan(ReportBuilder::FULL_LIMIT - 200)
        ->and(mb_strlen($summary['message']))->toBe(ReportBuilder::MESSAGE_LIMIT)
        ->and($full['trace'])->toStartWith('#0 /app/Some/Long/Path.php(1): call()');
});

test('a refused send token is logged with a hint and not retried', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'unauthorized'], 401)]);
    Log::spy();

    Errly::report(new RuntimeException('Boom'));

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'php artisan errly:pair'));
    Queue::assertNothingPushed();
});

test('a report the service refuses for its content is logged and not retried', function (int $status, string $code) {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => $code], $status)]);
    Log::spy();

    Errly::report(new RuntimeException('Boom'));

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, $code));
    Queue::assertNothingPushed();
})->with([
    [403, 'channel_suspended'],
    [413, 'summary_too_large'],
    [422, 'invalid_fingerprint'],
]);

test('a server error or timeout queues one retry of the same report', function (Closure $reply) {
    $this->http->fake(['errly.test/*' => $reply]);

    Errly::report(new RuntimeException('Boom'));

    Queue::assertPushed(RetryAppReport::class, fn (RetryAppReport $job) => $job->delay === 30
        && array_keys($job->body) === ['id', 'kind', 'fingerprint', 'summary', 'full']);
})->with([
    'server error' => fn () => fn () => (new Factory)->response(['error' => 'internal'], 503),
    'timeout' => fn () => fn () => throw new ConnectionException('timed out'),
]);

test('a rate limited report is retried once after Retry-After', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'rate_limited'], 429, ['Retry-After' => '60'])]);

    Errly::report(new RuntimeException('Boom'));

    Queue::assertPushed(RetryAppReport::class, fn (RetryAppReport $job) => $job->delay === 60);
});

test('the retry sends the same body and gives up after one more failure', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'internal'], 500)]);
    Log::spy();
    $body = ['id' => 'abc', 'kind' => 'error', 'fingerprint' => 'f', 'summary' => 's', 'full' => 'f'];

    app()->call([new RetryAppReport($body), 'handle']);

    expect(sentReport($this->http))->toBe($body);
    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'dropped'));
});

test('repeats dropped by the rate limiter are sent as suppressed on the next report', function () {
    acceptReports($this->http);
    config(['errly.rate_limiting.enabled' => true, 'errly.rate_limiting.max_per_minute' => 1]);
    $boom = fn () => new RuntimeException('Boom');

    Errly::report($boom());
    Errly::report($boom());
    Errly::report($boom());
    $this->travel(61)->seconds();
    Errly::report($boom());
    $this->travel(61)->seconds();
    Errly::report($boom());

    $suppressed = collect($this->http->recorded())
        ->map(fn ($pair) => $pair[0]->data())
        ->map(fn (array $body) => openEnvelope($body['summary'], $body['id'], 'summary')['suppressed']);

    expect($suppressed->all())->toBe([0, 2, 0]);
});

test('the reporter does not throw when the service cannot be reached', function () {
    $this->http->fake(fn () => throw new RuntimeException('dns failure'));
    Log::spy();

    Errly::report(new RuntimeException('Boom'));

    Log::shouldHaveReceived('warning')->once();
});
