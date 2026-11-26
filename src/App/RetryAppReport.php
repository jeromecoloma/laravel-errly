<?php

namespace Errly\LaravelErrly\App;

use Errly\LaravelErrly\Channels\AppChannel;
use Errly\LaravelErrly\Channels\ChannelManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The one retry of a report the Errly service did not take. It holds only
 * the encrypted body; the send token is read from config when it runs.
 */
class RetryAppReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array{id: string, kind: string, fingerprint: string, summary: string, full: string}  $body
     */
    public function __construct(public readonly array $body) {}

    public function handle(ChannelManager $channels): void
    {
        $channel = $channels->driver('app');

        if ($channel instanceof AppChannel && $channel->configured()) {
            $channel->deliver($this->body, isRetry: true);
        }
    }
}
