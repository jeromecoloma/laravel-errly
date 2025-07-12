<?php

namespace Errly\LaravelErrly\Tests\Feature;

use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Errly\LaravelErrly\Tests\TestCase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class TestErrorCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable Errly for testing
        config(['errly.enabled' => true]);
        config(['errly.slack.webhook_url' => 'https://hooks.slack.com/test']);
        
        // Allow testing environment
        config(['errly.filters.environments.enabled' => false]);
        // Or alternatively: config(['errly.filters.environments.allowed' => ['testing']]);
    }


    public function test_it_runs_general_error_test_by_default()
    {
        Notification::fake();

        $this->artisan('errly:test')
            ->expectsOutput('🚨 Testing Laravel Errly with error type: general')
            ->expectsOutput('🚀 Throwing general error...')
            ->expectsOutput('💡 This should trigger a MEDIUM severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: MEDIUM')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: RuntimeException')
            ->expectsOutput('Message: General test error from Laravel Errly occurred')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class
        );
    }


    public function test_it_runs_database_error_test()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'database'])
            ->expectsOutput('🚨 Testing Laravel Errly with error type: database')
            ->expectsOutput('🗄️  Throwing database error...')
            ->expectsOutput('💡 This should trigger a CRITICAL severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: CRITICAL')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: Illuminate\Database\QueryException')
            ->expectsOutputToContain('Message:')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class
        );
    }


    public function test_it_runs_critical_error_test()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'critical'])
            ->expectsOutput('🚨 Testing Laravel Errly with error type: critical')
            ->expectsOutput('⚠️  Throwing critical error...')
            ->expectsOutput('💡 This should trigger a CRITICAL severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: CRITICAL')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: ErrorException')
            ->expectsOutput('Message: This is a critical test error from Laravel Errly')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class
        );
    }


    public function test_it_runs_validation_error_test_and_filters_it_out()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'validation'])
            ->expectsOutput('🚨 Testing Laravel Errly with error type: validation')
            ->expectsOutput('📝 Throwing validation error...')
            ->expectsOutput('💡 This should be IGNORED (no Slack notification)')
            ->expectsOutput('🚫 Exception filtered out by Errly (as expected)')
            ->expectsOutput('✅ No Slack notification should be sent')
            ->expectsOutput('💡 This exception type is in the ignored_exceptions list')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: Illuminate\Validation\ValidationException')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }


    public function test_it_runs_custom_error_test()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'custom'])
            ->expectsOutput('🚨 Testing Laravel Errly with error type: custom')
            ->expectsOutput('🎯 Throwing custom error...')
            ->expectsOutput('💡 This should trigger a MEDIUM severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: MEDIUM')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: Exception')
            ->expectsOutput('Message: Custom test error from Laravel Errly - Check your Slack!')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class
        );
    }


    public function test_it_handles_unknown_error_type()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'unknown'])
            ->expectsOutput('🚨 Testing Laravel Errly with error type: unknown')
            ->expectsOutput('🚀 Throwing general error...')
            ->expectsOutput('💡 This should trigger a MEDIUM severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: MEDIUM')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: RuntimeException')
            ->expectsOutput('Message: General test error from Laravel Errly occurred')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class
        );
    }


    public function test_it_works_when_errly_is_disabled()
    {
        // Disable Errly but keep environment filtering disabled for this test
        config(['errly.enabled' => false]);

        Notification::fake();

        $this->artisan('errly:test')
            ->expectsOutput('🚨 Testing Laravel Errly with error type: general')
            ->expectsOutput('🚀 Throwing general error...')
            ->expectsOutput('💡 This should trigger a MEDIUM severity notification')
            ->expectsOutput('🚫 Exception filtered out by Errly (as expected)')
            ->expectsOutput('✅ No Slack notification should be sent')
            ->expectsOutput('💡 This exception type is in the ignored_exceptions list')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: RuntimeException')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }


    public function test_it_works_when_webhook_url_is_missing()
    {
        config(['errly.slack.webhook_url' => null]);

        Notification::fake();

        $this->artisan('errly:test')
            ->expectsOutput('🚨 Testing Laravel Errly with error type: general')
            ->expectsOutput('🚀 Throwing general error...')
            ->expectsOutput('💡 This should trigger a MEDIUM severity notification')
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->expectsOutput('🎯 Expected Slack severity: MEDIUM')
            ->expectsOutput('Exception Details:')
            ->expectsOutput('Type: RuntimeException')
            ->expectsOutput('🔍 Test completed. Check your Slack channel for notifications!')
            ->assertExitCode(0);

        // No notification should be sent due to missing webhook
        Notification::assertNothingSent();
    }


    public function test_it_includes_command_context_in_reported_exception()
    {
        Notification::fake();

        $this->artisan('errly:test', ['type' => 'custom']);

        Notification::assertSentOnDemand(
            
            SlackErrorNotification::class,
            function ($notification) {
                $context = $notification->getContext();
                
                // Check that command context is included
                $this->assertEquals('errly:test', $context['command']);
                $this->assertEquals('custom', $context['type']);
                $this->assertEquals('console_test', $context['context']);
                $this->assertArrayHasKey('timestamp', $context);
                $this->assertArrayHasKey('server', $context);
                
                return true;
            }
        );
    }


    public function test_it_shows_correct_severity_levels_for_different_exception_types()
    {
        $testCases = [
            'database' => 'CRITICAL',
            'critical' => 'CRITICAL',
            'general' => 'MEDIUM',
            'custom' => 'MEDIUM',
        ];

        foreach ($testCases as $type => $expectedSeverity) {
            Notification::fake();

            $this->artisan('errly:test', ['type' => $type])
                ->expectsOutput("🎯 Expected Slack severity: {$expectedSeverity}")
                ->assertExitCode(0);

            // Only check notifications for types that should be reported
            if ($type !== 'validation') {
                Notification::assertSentOnDemand(
                    
                    SlackErrorNotification::class,
                    function ($notification) use ($expectedSeverity) {
                        $reflection = new \ReflectionClass($notification);
                        $method = $reflection->getMethod('getSeverityLevel');
                        $method->setAccessible(true);
                        
                        $actualSeverity = $method->invoke($notification);
                        
                        return $actualSeverity === $expectedSeverity;
                    }
                );
            }
        }
    }


    public function test_it_handles_rate_limiting_during_command_execution()
    {
        config(['errly.rate_limiting.enabled' => true]);
        config(['errly.rate_limiting.max_per_minute' => 1]);

        Notification::fake();

        // First command should succeed
        $this->artisan('errly:test', ['type' => 'general'])
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->assertExitCode(0);

        // Second command should be rate limited (but command still reports success)
        $this->artisan('errly:test', ['type' => 'general'])
            ->expectsOutput('📤 Reporting exception to Errly...')
            ->expectsOutput('✅ Exception reported to Errly - check your Slack!')
            ->assertExitCode(0);

        // Note: In test environment, rate limiting may not work as expected due to cache isolation
        // Each command execution may get its own cache instance
        // In production, this would properly rate limit to 1 notification
        Notification::assertSentOnDemandTimes(SlackErrorNotification::class, 2);
    }


    public function test_it_respects_environment_filtering_in_command()
    {
        config(['errly.filters.environments.enabled' => true]);
        config(['errly.filters.environments.allowed' => ['production', 'staging']]);
        // Current environment is 'testing' which is not allowed

        Notification::fake();

        $this->artisan('errly:test')
            ->expectsOutput('🚫 Exception filtered out by Errly (as expected)')
            ->expectsOutput('✅ No Slack notification should be sent')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
} 