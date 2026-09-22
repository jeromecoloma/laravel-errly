<?php

namespace Errly\LaravelErrly\Heartbeat;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Adds one every-minute entry per configured heartbeat. A blank check adds
 * nothing, which is how local and staging stay quiet.
 */
class HeartbeatSchedule
{
    public static function register(Schedule $schedule): void
    {
        if (! app(HeartbeatManager::class)->enabled()) {
            return;
        }

        if (filled(config('errly.heartbeat.scheduler'))) {
            // Run inline, so the ping proves only that the scheduler ran.
            $schedule->call(static function (HeartbeatManager $heartbeats): void {
                $check = config('errly.heartbeat.scheduler');

                if (filled($check)) {
                    rescue(fn () => $heartbeats->ping($check), report: false);
                }
            })
                ->name('errly:heartbeat:scheduler')
                ->everyMinute();
        }

        foreach (config('errly.heartbeat.queues', []) as $queue => $check) {
            if (blank($check)) {
                continue;
            }

            // `default` means the connection's default queue, which is pushed
            // as null rather than as a queue literally named "default".
            $job = (new SendHeartbeat((string) $queue))
                ->onConnection(config('errly.heartbeat.queue_connection'))
                ->onQueue($queue === 'default' ? null : (string) $queue);

            $schedule->job($job)
                ->name("errly:heartbeat:queue:{$queue}")
                ->everyMinute();
        }
    }
}
