<?php

namespace Errly\LaravelErrly\Heartbeat\Clients;

use Errly\LaravelErrly\Heartbeat\HeartbeatClient;
use Errly\LaravelErrly\Heartbeat\HeartbeatException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * Pings Healthchecks.io, or a self-hosted Healthchecks instance.
 *
 * A check can be a full ping URL, a check UUID, or a slug. A slug needs the
 * project's ping key.
 */
class HealthchecksClient implements HeartbeatClient
{
    /**
     * A private HTTP factory by default, not the app's `Http` one: Telescope,
     * Pulse and Nightwatch hook into that, and would record every ping, with
     * the secret ping URL, once a minute per check.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly Factory $http = new Factory,
    ) {}

    /**
     * Error messages never contain the ping URL, the UUID or the ping key:
     * anyone holding one can keep a dead check looking healthy.
     */
    public function ping(string $check): void
    {
        $url = $this->url($check);

        try {
            $response = $this->http
                ->timeout((int) ($this->config['timeout_seconds'] ?? 5))
                ->retry(
                    max(1, (int) ($this->config['attempts'] ?? 1)),
                    (int) ($this->config['retry_delay_ms'] ?? 1000),
                    fn (\Throwable $e): bool => ! $e instanceof RequestException
                        || $e->response->serverError()
                        || $e->response->status() === 429,
                    throw: false,
                )
                ->get($url);
        } catch (\Throwable $e) {
            throw new HeartbeatException(
                'Could not reach Healthchecks: '.$this->redact($e->getMessage(), $url)
            );
        }

        // An unknown UUID is answered with "200 OK (not found)", and a rate
        // limited ping with "200 OK (rate limited)". Neither was recorded.
        if ($response->failed() || str_starts_with(trim($response->body()), 'OK (')) {
            throw new HeartbeatException(sprintf(
                'Healthchecks did not record the ping: HTTP %d %s',
                $response->status(),
                Str::limit($this->redact(trim($response->body()), $url), 100),
            ));
        }
    }

    public function url(string $check): string
    {
        if (preg_match('#^https?://#i', $check)) {
            return $check;
        }

        $base = rtrim((string) ($this->config['url'] ?? 'https://hc-ping.com'), '/');

        if (Str::isUuid($check)) {
            return "{$base}/{$check}";
        }

        $pingKey = $this->config['ping_key'] ?? null;

        if (blank($pingKey)) {
            throw new HeartbeatException(
                'A slug check needs ERRLY_HEALTHCHECKS_PING_KEY.'
            );
        }

        $url = "{$base}/{$pingKey}/{$check}";

        return ($this->config['auto_provision'] ?? false) ? "{$url}?create=1" : $url;
    }

    private function redact(string $message, string $url): string
    {
        return str_replace(
            array_filter([$url, $this->config['ping_key'] ?? null]),
            '[redacted]',
            $message,
        );
    }
}
