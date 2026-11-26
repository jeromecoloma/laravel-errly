<?php

namespace Errly\LaravelErrly\App;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Talks to the Errly service. Only ciphertext, fingerprints and the send
 * token leave the app; the channel key is never sent.
 */
class ServiceClient
{
    /**
     * A private HTTP factory by default, not the app's `Http` one: Telescope,
     * Pulse and Nightwatch hook into that and would record the send token.
     *
     * @param  array<string, mixed>  $config  `errly.app`
     */
    public function __construct(
        private readonly array $config,
        private readonly Factory $http = new Factory,
    ) {}

    /**
     * Built through the container, so tests can swap in a faked factory.
     *
     * @param  array<string, mixed>  $config
     */
    public static function for(array $config): self
    {
        return app()->make(self::class, ['config' => $config, 'http' => new Factory]);
    }

    public function url(): string
    {
        return rtrim((string) ($this->config['url'] ?? ''), '/');
    }

    public function channel(): string
    {
        return (string) ($this->config['channel'] ?? '');
    }

    public function createChannel(): Response
    {
        return $this->request(authenticated: false)->post('/v1/channels');
    }

    public function createJoinCode(): Response
    {
        return $this->request()->post($this->channelPath('/join-codes'));
    }

    /**
     * @param  array<string, string>  $report
     */
    public function sendReport(array $report): Response
    {
        return $this->request()->post($this->channelPath('/reports'), $report);
    }

    public function devices(): Response
    {
        return $this->request()->get($this->channelPath('/devices'));
    }

    public function revokeDevice(string $device): Response
    {
        return $this->request()->delete($this->channelPath('/devices/'.rawurlencode($device)));
    }

    public function rotate(): Response
    {
        return $this->request()->post($this->channelPath('/rotate'));
    }

    private function channelPath(string $path): string
    {
        return '/v1/channels/'.rawurlencode($this->channel()).$path;
    }

    private function request(bool $authenticated = true): PendingRequest
    {
        $request = $this->http
            ->baseUrl($this->url())
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout_seconds'] ?? 5))
            ->withUserAgent('laravel-errly');

        return $authenticated ? $request->withToken((string) ($this->config['send_token'] ?? '')) : $request;
    }
}
