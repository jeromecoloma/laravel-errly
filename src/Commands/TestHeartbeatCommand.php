<?php

namespace Errly\LaravelErrly\Commands;

use Errly\LaravelErrly\Heartbeat\HeartbeatManager;
use Illuminate\Console\Command;

class TestHeartbeatCommand extends Command
{
    protected $signature = 'errly:heartbeat-test';

    protected $description = 'Send one ping per configured Errly heartbeat, straight from this process';

    public function handle(HeartbeatManager $heartbeats): int
    {
        if (! $heartbeats->enabled()) {
            $this->warn('Heartbeats are disabled (ERRLY_ENABLED or ERRLY_HEARTBEAT_ENABLED is false).');

            return self::FAILURE;
        }

        $checks = array_filter([
            'scheduler' => config('errly.heartbeat.scheduler'),
            ...collect(config('errly.heartbeat.queues', []))
                ->mapWithKeys(fn ($check, $queue) => ["queue:{$queue}" => $check])
                ->all(),
        ], 'filled');

        if ($checks === []) {
            $this->warn('No heartbeats are configured.');

            return self::FAILURE;
        }

        $this->info('Pinging with the ['.$heartbeats->getDefaultDriver().'] client.');
        $this->comment('This does not go through the queue, so it proves the checks exist, not that workers run.');

        $failed = false;

        foreach ($checks as $name => $check) {
            try {
                $heartbeats->ping($check);
                $this->line("  <info>OK</info>     {$name}");
            } catch (\Throwable $e) {
                $failed = true;
                $this->line("  <error>FAILED</error> {$name}: {$e->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
