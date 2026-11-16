<?php

namespace Errly\LaravelErrly\Commands;

use Errly\LaravelErrly\App\Protocol;
use Errly\LaravelErrly\App\ServiceClient;
use Errly\LaravelErrly\App\TerminalQr;
use Errly\LaravelErrly\Support\EnvFile;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Pairs the Errly app with this Laravel app. The pairing link holds the
 * channel key, so it is only ever drawn in the terminal.
 */
class PairCommand extends Command
{
    protected $signature = 'errly:pair
        {--rotate : Start over with a new send token and key; every paired device is removed}
        {--link : Also print the pairing link, to paste into the Mac app}
        {--force : Rotate without asking}';

    protected $description = 'Pair the Errly app on iPhone or Mac with this application';

    /**
     * @var array<string, mixed>
     */
    private array $app;

    public function handle(): int
    {
        $this->app = (array) config('errly.app', []);

        if (! str_starts_with(strtolower((string) ($this->app['url'] ?? '')), 'https://')) {
            $this->error('ERRLY_APP_URL must be an https URL.');

            return self::FAILURE;
        }

        $envPath = $this->laravel->environmentFilePath();
        $hasChannel = filled($this->app['channel'] ?? null) && filled($this->app['send_token'] ?? null);

        if (($this->option('rotate') || ! $hasChannel) && ! is_writable($envPath)) {
            $this->error("Cannot write {$envPath}. Errly saves the channel, send token and key there.");

            return self::FAILURE;
        }

        try {
            if ($this->option('rotate')) {
                if (! $hasChannel) {
                    $this->error('There is no channel to rotate yet. Run `php artisan errly:pair` first.');

                    return self::FAILURE;
                }

                if (! $this->option('force') && ! $this->confirm('Rotating removes every paired device and all stored reports. Continue?')) {
                    return self::FAILURE;
                }

                if (! $this->saveChannel($this->client()->rotate(), $envPath)) {
                    return self::FAILURE;
                }
            } elseif (! $hasChannel) {
                if (! $this->saveChannel($this->client()->createChannel(), $envPath)) {
                    return self::FAILURE;
                }
            } elseif (blank($this->app['key'] ?? null)) {
                $this->error('ERRLY_APP_KEY is missing. Run `php artisan errly:pair --rotate` to make a new key.');

                return self::FAILURE;
            }

            $joinCode = $this->client()->createJoinCode();

            if ($joinCode->status() === 401 && ! $this->option('rotate')) {
                $this->warn('The Errly service no longer knows this channel. Channels with no paired device are removed after 24 hours, and idle ones after 30 days.');

                if (! is_writable($envPath) || ! $this->confirm('Create a new channel?', true)) {
                    return self::FAILURE;
                }

                if (! $this->saveChannel($this->client()->createChannel(), $envPath)) {
                    return self::FAILURE;
                }

                $joinCode = $this->client()->createJoinCode();
            }

            if ($joinCode->status() !== 201) {
                return $this->failed($joinCode, 'create a join code');
            }
        } catch (ConnectionException) {
            $this->error('Could not reach the Errly service at '.$this->app['url'].'.');

            return self::FAILURE;
        }

        $this->showPairing((string) $joinCode->json('join_code'), (string) $joinCode->json('expires_at'));

        return self::SUCCESS;
    }

    private function client(): ServiceClient
    {
        return ServiceClient::for($this->app);
    }

    /**
     * Saves a new channel and send token with a new key, in .env and in the
     * running config.
     */
    private function saveChannel(Response $response, string $envPath): bool
    {
        if (! in_array($response->status(), [200, 201], true)) {
            $this->failed($response, $this->option('rotate') ? 'rotate the channel' : 'create a channel');

            return false;
        }

        $values = [
            'ERRLY_APP_CHANNEL' => (string) $response->json('channel_id'),
            'ERRLY_APP_SEND_TOKEN' => (string) $response->json('send_token'),
            'ERRLY_APP_KEY' => Protocol::newKey(),
        ];

        $channels = (array) config('errly.channels', []);

        if (! in_array('app', $channels, true)) {
            $values['ERRLY_CHANNELS'] = implode(',', [...$channels, 'app']);
            config(['errly.channels' => [...$channels, 'app']]);
        }

        EnvFile::set($envPath, $values);

        $this->app['channel'] = $values['ERRLY_APP_CHANNEL'];
        $this->app['send_token'] = $values['ERRLY_APP_SEND_TOKEN'];
        $this->app['key'] = $values['ERRLY_APP_KEY'];
        config(['errly.app' => $this->app]);

        $this->info('Saved ERRLY_APP_CHANNEL, ERRLY_APP_SEND_TOKEN and ERRLY_APP_KEY to .env'
            .(isset($values['ERRLY_CHANNELS']) ? ", and added app to ERRLY_CHANNELS ({$values['ERRLY_CHANNELS']})." : '.'));

        if ($this->laravel->configurationIsCached()) {
            $this->warn('The config is cached. Run `php artisan config:cache` so the app picks up the new values.');
        }

        return true;
    }

    private function showPairing(string $joinCode, string $expiresAt): void
    {
        $link = Protocol::pairingLink((string) $this->app['channel'], (string) $this->app['key'], $joinCode, (string) $this->app['url']);

        $this->newLine();
        $this->output->write(TerminalQr::render($link), false, OutputInterface::OUTPUT_RAW);
        $this->newLine();
        $this->line('Scan this with the Errly app. The code works once and expires at '.$expiresAt.'.');

        if ($this->option('link')) {
            $this->newLine();
            $this->line('Pairing link, to paste into the Mac app. It holds the channel key: do not save or share it.');
            $this->output->writeln($link, OutputInterface::OUTPUT_RAW);
        } else {
            $this->line('On a Mac, run with --link to print the link to paste instead.');
        }
    }

    private function failed(Response $response, string $action): int
    {
        $code = (string) $response->json('error', '');

        $this->error(match ($response->status()) {
            401 => "Could not {$action}: the service did not accept the send token. Remove ERRLY_APP_CHANNEL, ERRLY_APP_SEND_TOKEN and ERRLY_APP_KEY from .env and run `php artisan errly:pair` again.",
            403 => "Could not {$action}: this channel is suspended.",
            429 => "Could not {$action}: too many requests. Try again in ".($response->header('Retry-After') ?: 'a few').' seconds.',
            default => "Could not {$action}: the service answered {$response->status()}".($code !== '' ? " ({$code})" : '').'.',
        });

        return self::FAILURE;
    }
}
