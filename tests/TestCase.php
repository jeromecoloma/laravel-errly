<?php

namespace Errly\LaravelErrly\Tests;

use Errly\LaravelErrly\ErrlyServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Errly\\LaravelErrly\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            ErrlyServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Package configuration
        config()->set('errly.enabled', true);
        config()->set('errly.slack.webhook_url', 'https://hooks.slack.com/services/fake/webhook/url');
    }
}
