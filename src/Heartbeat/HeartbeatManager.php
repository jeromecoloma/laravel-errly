<?php

namespace Errly\LaravelErrly\Heartbeat;

use Errly\LaravelErrly\Heartbeat\Clients\HealthchecksClient;
use Illuminate\Support\Manager;

/**
 * Resolves the heartbeat client named by `errly.heartbeat.client`. Other
 * monitors can be added with `extend()` from an app's service provider.
 *
 * @mixin HeartbeatClient
 */
class HeartbeatManager extends Manager
{
    /**
     * ERRLY_ENABLED=false switches off the whole package, heartbeats included.
     */
    public function enabled(): bool
    {
        return (bool) $this->config->get('errly.enabled', true)
            && (bool) $this->config->get('errly.heartbeat.enabled', true);
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('errly.heartbeat.client', 'healthchecks');
    }

    protected function createHealthchecksDriver(): HeartbeatClient
    {
        return new HealthchecksClient($this->config->get('errly.heartbeat.clients.healthchecks', []));
    }
}
