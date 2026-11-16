<?php

namespace Errly\LaravelErrly\Commands;

use Errly\LaravelErrly\App\ServiceClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class DevicesCommand extends Command
{
    protected $signature = 'errly:devices
        {--revoke= : The ID of a device to remove}';

    protected $description = 'List or remove the devices paired with the Errly app channel';

    public function handle(): int
    {
        $config = (array) config('errly.app', []);

        if (blank($config['channel'] ?? null) || blank($config['send_token'] ?? null)) {
            $this->error('This app is not paired yet. Run `php artisan errly:pair`.');

            return self::FAILURE;
        }

        $client = ServiceClient::for($config);

        try {
            return filled($this->option('revoke'))
                ? $this->revoke($client, (string) $this->option('revoke'))
                : $this->list($client);
        } catch (ConnectionException) {
            $this->error('Could not reach the Errly service at '.$config['url'].'.');

            return self::FAILURE;
        }
    }

    private function list(ServiceClient $client): int
    {
        $response = $client->devices();

        if ($response->status() !== 200) {
            return $this->failed($response, 'list devices');
        }

        $devices = (array) $response->json('devices', []);

        if ($devices === []) {
            $this->line('No devices are paired. Run `php artisan errly:pair` to add one.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Platform', 'Status', 'Paired', 'Last seen'],
            array_map(fn (array $device) => [
                $device['id'] ?? '',
                $device['name'] ?? '',
                $device['platform'] ?? '',
                $device['status'] ?? '',
                $device['created_at'] ?? '',
                $device['last_seen_at'] ?? '',
            ], $devices),
        );

        return self::SUCCESS;
    }

    private function revoke(ServiceClient $client, string $device): int
    {
        $response = $client->revokeDevice($device);

        if ($response->status() === 404) {
            $this->error("No device with ID {$device}.");

            return self::FAILURE;
        }

        if ($response->status() !== 204) {
            return $this->failed($response, 'remove the device');
        }

        $this->info("Removed device {$device}.");

        return self::SUCCESS;
    }

    private function failed(Response $response, string $action): int
    {
        $code = (string) $response->json('error', '');

        $this->error(match ($response->status()) {
            401 => "Could not {$action}: the service did not accept the send token. Run `php artisan errly:pair` to pair again.",
            default => "Could not {$action}: the service answered {$response->status()}".($code !== '' ? " ({$code})" : '').'.',
        });

        return self::FAILURE;
    }
}
