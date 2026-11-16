<?php

namespace Errly\LaravelErrly\Channels;

use Errly\LaravelErrly\App\ServiceClient;
use Illuminate\Support\Manager;

/**
 * Resolves the channels named in `errly.channels`. Other channels can be
 * added with `extend()` from an app's service provider.
 *
 * @mixin Channel
 */
class ChannelManager extends Manager
{
    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', (array) $this->config->get('errly.channels', ['slack']))
        )));
    }

    public function getDefaultDriver(): string
    {
        return $this->names()[0] ?? 'slack';
    }

    protected function createSlackDriver(): Channel
    {
        return new SlackChannel;
    }

    protected function createAppDriver(): Channel
    {
        $config = $this->config->get('errly.app', []);

        return new AppChannel($config, ServiceClient::for($config));
    }
}
