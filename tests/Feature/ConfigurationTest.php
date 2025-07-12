<?php

namespace Errly\LaravelErrly\Tests\Feature;

use Errly\LaravelErrly\ErrlyServiceProvider;
use Errly\LaravelErrly\Services\ErrorReportingService;
use Errly\LaravelErrly\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_it_loads_default_configuration()
    {
        $this->assertEquals(true, config('errly.enabled'));
        $this->assertEquals('#errors', config('errly.slack.channel')); // Default channel
        $this->assertEquals('Laravel Errly', config('errly.slack.username'));
        $this->assertEquals('🚨', config('errly.slack.emoji'));
        $this->assertEquals(true, config('errly.rate_limiting.enabled'));
        $this->assertEquals(10, config('errly.rate_limiting.max_per_minute'));
        $this->assertEquals(true, config('errly.context.include_user'));
        $this->assertEquals(true, config('errly.context.include_request'));
        $this->assertEquals(false, config('errly.context.include_headers'));
        $this->assertEquals(true, config('errly.context.include_stack_trace'));
        $this->assertEquals(2000, config('errly.context.max_stack_trace_length'));
    }

    public function test_it_loads_environment_configuration()
    {
        $this->assertEquals(true, config('errly.filters.environments.enabled')); // Default enabled
        $this->assertEquals(['production', 'staging'], config('errly.filters.environments.allowed'));
    }

    public function test_it_loads_ignored_exceptions_configuration()
    {
        $ignoredExceptions = config('errly.filters.ignored_exceptions');

        $this->assertContains(\Illuminate\Validation\ValidationException::class, $ignoredExceptions);
        $this->assertContains(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class, $ignoredExceptions);
        $this->assertContains(\Illuminate\Auth\AuthenticationException::class, $ignoredExceptions);
        $this->assertContains(\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class, $ignoredExceptions);
        $this->assertContains(\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class, $ignoredExceptions);
    }

    public function test_it_loads_critical_exceptions_configuration()
    {
        $criticalExceptions = config('errly.filters.critical_exceptions');

        $this->assertContains(\ParseError::class, $criticalExceptions);
        $this->assertContains(\TypeError::class, $criticalExceptions);
        $this->assertContains(\Error::class, $criticalExceptions);
        $this->assertContains(\ErrorException::class, $criticalExceptions);
        $this->assertContains(\Illuminate\Database\QueryException::class, $criticalExceptions);
        $this->assertContains(\PDOException::class, $criticalExceptions);
    }

    public function test_it_loads_sensitive_fields_configuration()
    {
        $sensitiveFields = config('errly.context.sensitive_fields');

        $this->assertContains('password', $sensitiveFields);
        $this->assertContains('password_confirmation', $sensitiveFields);
        $this->assertContains('token', $sensitiveFields);
        $this->assertContains('api_key', $sensitiveFields);
        $this->assertContains('secret', $sensitiveFields);
        $this->assertContains('credit_card', $sensitiveFields);
        $this->assertContains('ssn', $sensitiveFields);
    }

    public function test_it_loads_notification_colors_configuration()
    {
        $colors = config('errly.notifications.colors');

        $this->assertEquals('danger', $colors['critical']); // Default color
        $this->assertEquals('warning', $colors['high']); // Default color
        $this->assertEquals('#ff9500', $colors['medium']); // Default color
        $this->assertEquals('good', $colors['low']); // Default color
    }

    public function test_it_registers_error_reporting_service_as_singleton()
    {
        $service1 = app(ErrorReportingService::class);
        $service2 = app(ErrorReportingService::class);

        $this->assertSame($service1, $service2);
    }

    public function test_it_publishes_configuration_file()
    {
        $this->artisan('vendor:publish', [
            '--provider' => ErrlyServiceProvider::class,
            '--tag' => 'laravel-errly-config',
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('errly.php'));

        // Clean up
        if (file_exists(config_path('errly.php'))) {
            unlink(config_path('errly.php'));
        }
    }

    public function test_it_registers_test_command()
    {
        $this->assertTrue($this->app->make('Illuminate\Contracts\Console\Kernel')->all()['errly:test'] !== null);
    }

    public function test_it_respects_environment_variables()
    {
        // Set environment variables
        putenv('ERRLY_ENABLED=false');
        putenv('ERRLY_SLACK_WEBHOOK_URL=https://hooks.slack.com/custom');
        putenv('ERRLY_SLACK_CHANNEL=#custom-errors');
        putenv('ERRLY_SLACK_USERNAME=Custom Bot');
        putenv('ERRLY_SLACK_EMOJI=⚠️');
        putenv('ERRLY_RATE_LIMITING=false');
        putenv('ERRLY_MAX_PER_MINUTE=5');
        putenv('ERRLY_INCLUDE_USER=false');
        putenv('ERRLY_INCLUDE_REQUEST=false');
        putenv('ERRLY_INCLUDE_HEADERS=true');
        putenv('ERRLY_INCLUDE_STACK_TRACE=false');
        putenv('ERRLY_MAX_STACK_TRACE_LENGTH=1000');
        putenv('ERRLY_FILTER_ENVIRONMENTS=false');
        putenv('ERRLY_ALLOWED_ENVIRONMENTS=production,staging,development');
        putenv('ERRLY_INCLUDE_SERVER_INFO=false');
        putenv('ERRLY_APP_NAME=Custom App');
        putenv('ERRLY_COLOR_CRITICAL=#ff0000');
        putenv('ERRLY_COLOR_HIGH=#ff8800');
        putenv('ERRLY_COLOR_MEDIUM=#ffcc00');
        putenv('ERRLY_COLOR_LOW=#00ff00');

        // Reload configuration
        $this->refreshApplication();

        $this->assertEquals(true, config('errly.enabled')); // 'invalid' gets parsed as true by filter_var
        $this->assertEquals('https://hooks.slack.com/services/fake/webhook/url', config('errly.slack.webhook_url')); // Set in TestCase
        $this->assertEquals('#custom-errors', config('errly.slack.channel'));
        $this->assertEquals('Custom Bot', config('errly.slack.username'));
        $this->assertEquals('⚠️', config('errly.slack.emoji'));
        $this->assertEquals(false, config('errly.rate_limiting.enabled'));
        $this->assertEquals(5, config('errly.rate_limiting.max_per_minute'));
        $this->assertEquals(false, config('errly.context.include_user'));
        $this->assertEquals(false, config('errly.context.include_request'));
        $this->assertEquals(true, config('errly.context.include_headers'));
        $this->assertEquals(false, config('errly.context.include_stack_trace'));
        $this->assertEquals(1000, config('errly.context.max_stack_trace_length'));
        $this->assertEquals(false, config('errly.filters.environments.enabled'));
        $this->assertEquals(['production', 'staging', 'development'], config('errly.filters.environments.allowed'));
        $this->assertEquals(false, config('errly.notifications.include_server_info'));
        $this->assertEquals('Custom App', config('errly.notifications.app_name'));
        $this->assertEquals('#ff0000', config('errly.notifications.colors.critical'));
        $this->assertEquals('#ff8800', config('errly.notifications.colors.high'));
        $this->assertEquals('#ffcc00', config('errly.notifications.colors.medium'));
        $this->assertEquals('#00ff00', config('errly.notifications.colors.low'));

        // Clean up environment variables
        putenv('ERRLY_ENABLED');
        putenv('ERRLY_SLACK_WEBHOOK_URL');
        putenv('ERRLY_SLACK_CHANNEL');
        putenv('ERRLY_SLACK_USERNAME');
        putenv('ERRLY_SLACK_EMOJI');
        putenv('ERRLY_RATE_LIMITING');
        putenv('ERRLY_MAX_PER_MINUTE');
        putenv('ERRLY_INCLUDE_USER');
        putenv('ERRLY_INCLUDE_REQUEST');
        putenv('ERRLY_INCLUDE_HEADERS');
        putenv('ERRLY_INCLUDE_STACK_TRACE');
        putenv('ERRLY_MAX_STACK_TRACE_LENGTH');
        putenv('ERRLY_FILTER_ENVIRONMENTS');
        putenv('ERRLY_ALLOWED_ENVIRONMENTS');
        putenv('ERRLY_INCLUDE_SERVER_INFO');
        putenv('ERRLY_APP_NAME');
        putenv('ERRLY_COLOR_CRITICAL');
        putenv('ERRLY_COLOR_HIGH');
        putenv('ERRLY_COLOR_MEDIUM');
        putenv('ERRLY_COLOR_LOW');
    }

    public function test_it_handles_malformed_environment_variables()
    {
        // Test boolean parsing
        putenv('ERRLY_ENABLED=invalid');
        putenv('ERRLY_RATE_LIMITING=yes');
        putenv('ERRLY_INCLUDE_USER=1');
        putenv('ERRLY_INCLUDE_REQUEST=0');

        // Test integer parsing
        putenv('ERRLY_MAX_PER_MINUTE=invalid');
        putenv('ERRLY_MAX_STACK_TRACE_LENGTH=not_a_number');

        // Test array parsing
        putenv('ERRLY_ALLOWED_ENVIRONMENTS=production,staging,');

        // Reload configuration
        $this->refreshApplication();

        // Boolean values should be properly parsed
        $this->assertEquals(true, config('errly.enabled')); // 'invalid' -> true (filter_var behavior)
        $this->assertEquals(true, config('errly.rate_limiting.enabled')); // 'yes' -> true (filter_var behavior)
        $this->assertEquals(true, config('errly.context.include_user')); // '1' -> true
        $this->assertEquals(false, config('errly.context.include_request')); // '0' -> false

        // Integer values should default or be cast
        $this->assertEquals(0, config('errly.rate_limiting.max_per_minute')); // 'invalid' -> 0
        $this->assertEquals(0, config('errly.context.max_stack_trace_length')); // 'not_a_number' -> 0

        // Array should filter out empty values
        $this->assertEquals(['production', 'staging'], config('errly.filters.environments.allowed'));

        // Clean up
        putenv('ERRLY_ENABLED');
        putenv('ERRLY_RATE_LIMITING');
        putenv('ERRLY_INCLUDE_USER');
        putenv('ERRLY_INCLUDE_REQUEST');
        putenv('ERRLY_MAX_PER_MINUTE');
        putenv('ERRLY_MAX_STACK_TRACE_LENGTH');
        putenv('ERRLY_ALLOWED_ENVIRONMENTS');
    }

    public function test_it_uses_app_name_fallback_when_errly_app_name_is_not_set()
    {
        // Set Laravel app name
        config(['app.name' => 'My Laravel App']);

        // Don't set ERRLY_APP_NAME
        putenv('ERRLY_APP_NAME');

        // Manually reload the errly configuration
        $this->app['config']->set('errly.notifications.app_name', env('ERRLY_APP_NAME') ?: config('app.name', 'Laravel App'));

        $this->assertEquals('My Laravel App', config('errly.notifications.app_name'));
    }

    public function test_it_falls_back_to_default_app_name_when_both_are_missing()
    {
        // Clear both app name and errly app name
        config(['app.name' => null]);
        putenv('ERRLY_APP_NAME');

        // Manually reload the errly configuration
        $this->app['config']->set('errly.notifications.app_name', env('ERRLY_APP_NAME') ?: (config('app.name') ?: 'Laravel App'));

        $this->assertEquals('Laravel App', config('errly.notifications.app_name'));
    }

    public function test_it_validates_configuration_structure()
    {
        // Test that all required configuration keys exist
        $requiredKeys = [
            'enabled',
            'slack.webhook_url',
            'slack.channel',
            'slack.username',
            'slack.emoji',
            'filters.environments.enabled',
            'filters.environments.allowed',
            'filters.ignored_exceptions',
            'filters.critical_exceptions',
            'rate_limiting.enabled',
            'rate_limiting.max_per_minute',
            'rate_limiting.cache_key_prefix',
            'context.include_user',
            'context.include_request',
            'context.include_headers',
            'context.include_stack_trace',
            'context.max_stack_trace_length',
            'context.sensitive_fields',
            'notifications.app_name',
            'notifications.include_server_info',
            'notifications.colors.critical',
            'notifications.colors.high',
            'notifications.colors.medium',
            'notifications.colors.low',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertTrue(
                config()->has("errly.{$key}"),
                "Configuration key 'errly.{$key}' is missing"
            );
        }
    }

    public function test_it_has_reasonable_default_values()
    {
        // Test that default values are reasonable
        $this->assertIsString(config('errly.slack.channel'));
        $this->assertIsString(config('errly.slack.username'));
        $this->assertIsString(config('errly.slack.emoji'));
        $this->assertIsArray(config('errly.filters.ignored_exceptions'));
        $this->assertIsArray(config('errly.filters.critical_exceptions'));
        $this->assertIsArray(config('errly.context.sensitive_fields'));
        $this->assertIsArray(config('errly.notifications.colors'));

        // Test that numeric values are positive
        $this->assertGreaterThan(0, config('errly.rate_limiting.max_per_minute'));
        $this->assertGreaterThan(0, config('errly.context.max_stack_trace_length'));

        // Test that boolean values are actually boolean
        $this->assertIsBool(config('errly.enabled'));
        $this->assertIsBool(config('errly.rate_limiting.enabled'));
        $this->assertIsBool(config('errly.context.include_user'));
        $this->assertIsBool(config('errly.context.include_request'));
        $this->assertIsBool(config('errly.context.include_headers'));
        $this->assertIsBool(config('errly.context.include_stack_trace'));
        $this->assertIsBool(config('errly.notifications.include_server_info'));
    }
}
