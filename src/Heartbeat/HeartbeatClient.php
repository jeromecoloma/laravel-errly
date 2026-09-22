<?php

namespace Errly\LaravelErrly\Heartbeat;

interface HeartbeatClient
{
    /**
     * Tell the monitor that the given check is alive.
     *
     * @param  string  $check  The check as written in `errly.heartbeat`: a
     *                         full URL, or whatever the client knows how to
     *                         turn into one.
     *
     * @throws HeartbeatException when the monitor did not record the ping.
     */
    public function ping(string $check): void;
}
