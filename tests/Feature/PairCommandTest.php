<?php

use Errly\LaravelErrly\App\Protocol;
use Errly\LaravelErrly\App\ServiceClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->envDir = sys_get_temp_dir().'/errly-pair-'.bin2hex(random_bytes(4));
    mkdir($this->envDir);
    $this->envFile = $this->envDir.'/.env';
    file_put_contents($this->envFile, "APP_NAME=Test\nERRLY_APP_CHANNEL=\n");
    app()->useEnvironmentPath($this->envDir);

    config([
        'errly.channels' => ['slack'],
        'errly.app.url' => 'https://errly.test',
        'errly.app.channel' => null,
        'errly.app.send_token' => null,
        'errly.app.key' => null,
    ]);

    $this->http = $http = (new Factory)->preventStrayRequests();
    app()->bind(ServiceClient::class, fn ($app, array $params) => new ServiceClient($params['config'], $http));
});

afterEach(function () {
    @unlink($this->envFile);
    @rmdir($this->envDir);
});

function pairingService(Factory $http, array $overrides = []): void
{
    $http->fake($overrides + [
        'errly.test/v1/channels' => $http->response(['channel_id' => 'CCCCCCCCCCCCCCCCCCCCCC', 'send_token' => 'new-send-token'], 201),
        'errly.test/v1/channels/*/join-codes' => $http->response(['join_code' => 'JJJJJJJJJJJJJJJJJJJJJJ', 'expires_at' => '2026-11-17T10:10:00.000Z'], 201),
        'errly.test/v1/channels/*/rotate' => $http->response(['channel_id' => 'RRRRRRRRRRRRRRRRRRRRRR', 'send_token' => 'rotated-send-token'], 200),
    ]);
}

/**
 * @return array<string, string>
 */
function envValues(string $path): array
{
    return collect(file($path, FILE_IGNORE_NEW_LINES))
        ->filter()
        ->mapWithKeys(fn (string $line) => [strstr($line, '=', true) => substr(strstr($line, '='), 1)])
        ->all();
}

test('the first run creates a channel, saves it with a new key and draws the QR code', function () {
    pairingService($this->http);

    $exit = Artisan::call('errly:pair');
    $output = Artisan::output();
    $env = envValues($this->envFile);

    expect($exit)->toBe(0)
        ->and($env['APP_NAME'])->toBe('Test')
        ->and($env['ERRLY_APP_CHANNEL'])->toBe('CCCCCCCCCCCCCCCCCCCCCC')
        ->and($env['ERRLY_APP_SEND_TOKEN'])->toBe('new-send-token')
        ->and(strlen(Protocol::base64UrlDecode($env['ERRLY_APP_KEY'])))->toBe(32)
        ->and($env['ERRLY_CHANNELS'])->toBe('slack,app')
        ->and($output)->toContain('█')
        ->and($output)->not->toContain($env['ERRLY_APP_KEY'])
        ->and($output)->not->toContain('errly://');

    $this->http->assertSent(fn (Request $request) => $request->url() === 'https://errly.test/v1/channels'
        && ! $request->hasHeader('Authorization'));
    $this->http->assertSent(fn (Request $request) => $request->url() === 'https://errly.test/v1/channels/CCCCCCCCCCCCCCCCCCCCCC/join-codes'
        && $request->hasHeader('Authorization', 'Bearer new-send-token'));
});

test('--link prints the pairing link for the Mac app', function () {
    pairingService($this->http);

    Artisan::call('errly:pair', ['--link' => true]);
    $key = envValues($this->envFile)['ERRLY_APP_KEY'];

    expect(Artisan::output())->toContain(
        'errly://pair?c=CCCCCCCCCCCCCCCCCCCCCC&k='.$key.'&j=JJJJJJJJJJJJJJJJJJJJJJ&u=https%3A%2F%2Ferrly.test'
    );
});

test('a paired app only asks for a new join code', function () {
    pairingService($this->http);
    config([
        'errly.channels' => ['slack', 'app'],
        'errly.app.channel' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'errly.app.send_token' => 'old-send-token',
        'errly.app.key' => Protocol::newKey(),
    ]);
    $before = file_get_contents($this->envFile);

    expect(Artisan::call('errly:pair'))->toBe(0)
        ->and(file_get_contents($this->envFile))->toBe($before);

    $this->http->assertSentCount(1);
});

test('a channel the service has forgotten is replaced after asking', function () {
    pairingService($this->http, [
        'errly.test/v1/channels/AAAAAAAAAAAAAAAAAAAAAA/join-codes' => $this->http->response(['error' => 'unauthorized'], 401),
    ]);
    config([
        'errly.app.channel' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'errly.app.send_token' => 'old-send-token',
        'errly.app.key' => Protocol::newKey(),
    ]);

    $this->artisan('errly:pair')
        ->expectsConfirmation('Create a new channel?', 'yes')
        ->assertSuccessful();

    expect(envValues($this->envFile)['ERRLY_APP_CHANNEL'])->toBe('CCCCCCCCCCCCCCCCCCCCCC');
});

test('--rotate asks first, then saves the new token and a new key', function () {
    pairingService($this->http);
    $oldKey = Protocol::newKey();
    config([
        'errly.app.channel' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'errly.app.send_token' => 'old-send-token',
        'errly.app.key' => $oldKey,
    ]);

    $this->artisan('errly:pair', ['--rotate' => true])
        ->expectsConfirmation('Rotating removes every paired device and all stored reports. Continue?', 'no')
        ->assertFailed();
    $this->http->assertNothingSent();

    $this->artisan('errly:pair', ['--rotate' => true, '--force' => true])->assertSuccessful();
    $env = envValues($this->envFile);

    expect($env['ERRLY_APP_CHANNEL'])->toBe('RRRRRRRRRRRRRRRRRRRRRR')
        ->and($env['ERRLY_APP_SEND_TOKEN'])->toBe('rotated-send-token')
        ->and($env['ERRLY_APP_KEY'])->not->toBe($oldKey);

    $this->http->assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/channels/AAAAAAAAAAAAAAAAAAAAAA/rotate')
        && $request->hasHeader('Authorization', 'Bearer old-send-token'));
});

test('the service URL must be https', function () {
    config(['errly.app.url' => 'http://errly.test']);

    $this->artisan('errly:pair')->assertFailed();
    $this->http->assertNothingSent();
});

test('a refused channel request is reported', function () {
    pairingService($this->http, [
        'errly.test/v1/channels' => $this->http->response(['error' => 'rate_limited'], 429, ['Retry-After' => '3600']),
    ]);

    $this->artisan('errly:pair')
        ->expectsOutputToContain('too many requests')
        ->assertFailed();

    expect(envValues($this->envFile))->not->toHaveKey('ERRLY_APP_KEY');
});
