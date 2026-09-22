<?php

namespace Errly\LaravelErrly\Heartbeat;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proves a worker is draining the queue this job landed on: nothing arrives
 * while that worker is down, and the monitor's silence is the alert.
 *
 * The check is read from config when the ping is sent, never stored in the
 * payload, so a changed or blanked check takes effect on jobs already waiting.
 */
class SendHeartbeat implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * A missed ping is replaced by next minute's. A retry would only report
     * that the worker was alive at some earlier moment.
     */
    public int $tries = 1;

    /**
     * One heartbeat waits per queue while its worker is down, rather than one
     * a minute piling up behind it. Bounded so a lost job cannot hold the lock
     * for good - if it does, the monitor alerts, which is the safe direction.
     */
    public int $uniqueFor = 600;

    /**
     * @param  string  $heartbeat  A key under `errly.heartbeat.queues`.
     */
    public function __construct(public readonly string $heartbeat) {}

    public function uniqueId(): string
    {
        return $this->heartbeat;
    }

    /**
     * A failed ping is not reported: the monitor raises the missed ping
     * itself, and an unreachable monitor must not fill failed_jobs or Slack
     * once a minute.
     */
    public function handle(HeartbeatManager $heartbeats): void
    {
        $check = config('errly.heartbeat.queues', [])[$this->heartbeat] ?? null;

        if (! $heartbeats->enabled() || blank($check)) {
            return;
        }

        rescue(fn () => $heartbeats->ping($check), report: false);
    }
}
