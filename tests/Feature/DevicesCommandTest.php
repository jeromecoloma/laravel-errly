<?php

use Errly\LaravelErrly\App\ServiceClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

beforeEach(function () {
    config([
        'errly.app.url' => 'https://errly.test',
        'errly.app.channel' => 'AAAAAAAAAAAAAAAAAAAAAA',
        'errly.app.send_token' => 'send-token',
    ]);

    $this->http = $http = (new Factory)->preventStrayRequests();
    app()->bind(ServiceClient::class, fn ($app, array $params) => new ServiceClient($params['config'], $http));
});

test('it lists the paired devices', function () {
    $this->http->fake(['errly.test/v1/channels/AAAAAAAAAAAAAAAAAAAAAA/devices' => $this->http->response(['devices' => [[
        'id' => 'DDDDDDDDDDDDDDDDDDDDDD',
        'name' => 'Jane’s iPhone',
        'platform' => 'ios',
        'apns_env' => 'production',
        'status' => 'active',
        'created_at' => '2026-11-16T09:00:00.000Z',
        'last_seen_at' => '2026-11-17T09:00:00.000Z',
    ]]])]);

    $this->artisan('errly:devices')
        ->expectsTable(
            ['ID', 'Name', 'Platform', 'Status', 'Paired', 'Last seen'],
            [['DDDDDDDDDDDDDDDDDDDDDD', 'Jane’s iPhone', 'ios', 'active', '2026-11-16T09:00:00.000Z', '2026-11-17T09:00:00.000Z']],
        )
        ->assertSuccessful();

    $this->http->assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer send-token'));
});

test('it removes a device', function () {
    $this->http->fake(['errly.test/v1/channels/AAAAAAAAAAAAAAAAAAAAAA/devices/DDDDDDDDDDDDDDDDDDDDDD' => $this->http->response(null, 204)]);

    $this->artisan('errly:devices', ['--revoke' => 'DDDDDDDDDDDDDDDDDDDDDD'])
        ->expectsOutputToContain('Removed device DDDDDDDDDDDDDDDDDDDDDD')
        ->assertSuccessful();

    $this->http->assertSent(fn (Request $request) => $request->method() === 'DELETE');
});

test('an unknown device is reported', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'no_device'], 404)]);

    $this->artisan('errly:devices', ['--revoke' => 'nope'])
        ->expectsOutputToContain('No device with ID nope')
        ->assertFailed();
});

test('a refused send token points at errly:pair', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'unauthorized'], 401)]);

    $this->artisan('errly:devices')
        ->expectsOutputToContain('errly:pair')
        ->assertFailed();
});

test('a deleted channel points at errly:pair', function () {
    $this->http->fake(['errly.test/*' => $this->http->response(['error' => 'channel_deleted'], 410)]);

    $this->artisan('errly:devices')
        ->expectsOutputToContain('deleted this channel')
        ->assertFailed();
});

test('it needs a paired app', function () {
    config(['errly.app.channel' => null]);

    $this->artisan('errly:devices')->assertFailed();
    $this->http->assertNothingSent();
});
