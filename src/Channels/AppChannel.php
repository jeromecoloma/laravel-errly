<?php

namespace Errly\LaravelErrly\Channels;

use Errly\LaravelErrly\App\Protocol;
use Errly\LaravelErrly\App\ReportBuilder;
use Errly\LaravelErrly\App\RetryAppReport;
use Errly\LaravelErrly\App\ServiceClient;
use Errly\LaravelErrly\ErrorReport;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;

/**
 * Sends encrypted reports to the Errly service for the Errly app. Failures
 * are logged, with one queued retry where the service asks for it; nothing
 * here throws into the host app.
 */
class AppChannel implements Channel
{
    private const RETRY_DELAY_SECONDS = 30;

    /**
     * @param  array<string, mixed>  $config  `errly.app`
     */
    public function __construct(
        private readonly array $config,
        private readonly ServiceClient $client,
    ) {}

    public function configured(): bool
    {
        foreach (['url', 'channel', 'send_token', 'key'] as $name) {
            if (blank($this->config[$name] ?? null)) {
                return false;
            }
        }

        return true;
    }

    public function send(ErrorReport $report): void
    {
        if (! $this->configured()) {
            return;
        }

        if (! function_exists('sodium_crypto_aead_chacha20poly1305_ietf_encrypt')) {
            throw new RuntimeException('The Errly app channel needs the PHP sodium extension.');
        }

        $builder = new ReportBuilder(
            (string) $this->config['channel'],
            Protocol::keys((string) $this->config['key']),
            (bool) ($this->config['include_personal_data'] ?? true),
        );

        $this->deliver($builder->build($report));
    }

    /**
     * @param  array{id: string, kind: string, fingerprint: string, summary: string, full: string}  $body
     */
    public function deliver(array $body, bool $isRetry = false): void
    {
        try {
            $response = $this->client->sendReport($body);
        } catch (ConnectionException) {
            $this->retry($body, $isRetry, self::RETRY_DELAY_SECONDS, 'the Errly service did not answer in time');

            return;
        } catch (Throwable $e) {
            $this->warn('could not reach the Errly service: '.$e->getMessage());

            return;
        }

        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $code = (string) $response->json('error', '');

        match (true) {
            $status === 401 => $this->warn('the Errly service did not accept the send token. Run `php artisan errly:pair` to pair this app again.'),
            $status === 429 => $this->retry($body, $isRetry, max(1, (int) ($response->header('Retry-After') ?: 60)), 'the Errly service is rate limiting this channel'),
            $status >= 500 => $this->retry($body, $isRetry, self::RETRY_DELAY_SECONDS, "the Errly service answered {$status}"),
            default => $this->warn("the Errly service refused the report ({$status} {$code})."),
        };
    }

    /**
     * @param  array{id: string, kind: string, fingerprint: string, summary: string, full: string}  $body
     */
    private function retry(array $body, bool $isRetry, int $delaySeconds, string $reason): void
    {
        if ($isRetry) {
            $this->warn("{$reason}; the retry failed too, so the report was dropped.");

            return;
        }

        try {
            RetryAppReport::dispatch($body)->delay($delaySeconds);
        } catch (Throwable $e) {
            $this->warn("{$reason}, and the retry could not be queued: ".$e->getMessage());
        }
    }

    private function warn(string $message): void
    {
        logger()->warning('Errly: '.$message);
    }
}
