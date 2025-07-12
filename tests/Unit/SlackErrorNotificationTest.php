<?php

namespace Errly\LaravelErrly\Tests\Unit;

use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Errly\LaravelErrly\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Messages\SlackMessage;
use PDOException;
use RuntimeException;
use TypeError;

class SlackErrorNotificationTest extends TestCase
{
    public function test_it_creates_slack_message_with_basic_exception()
    {
        config(['errly.slack.emoji' => '🚨']);

        $exception = new RuntimeException('Test runtime exception');
        $context = [
            'environment' => 'testing',
            'timestamp' => '2024-01-01T12:00:00Z',
            'server' => 'test-server',
        ];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertInstanceOf(SlackMessage::class, $slackMessage);
        $this->assertStringContainsString('MEDIUM Error', $slackMessage->content);
        $this->assertStringContainsString('🚨', $slackMessage->content);
    }

    public function test_it_determines_critical_severity_for_type_error()
    {
        $exception = new TypeError('Type error occurred');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertStringContainsString('CRITICAL Error', $slackMessage->content);
    }

    public function test_it_determines_critical_severity_for_database_exception()
    {
        $exception = new QueryException(
            'mysql',
            'SELECT * FROM users',
            [],
            new PDOException('Connection failed')
        );
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertStringContainsString('CRITICAL Error', $slackMessage->content);
    }

    public function test_it_determines_high_severity_for_server_errors()
    {
        $exception = new class extends \Exception
        {
            public function getStatusCode(): int
            {
                return 500;
            }
        };
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertStringContainsString('HIGH Error', $slackMessage->content);
    }

    public function test_it_determines_medium_severity_for_general_exceptions()
    {
        $exception = new RuntimeException('General runtime error');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertStringContainsString('MEDIUM Error', $slackMessage->content);
    }

    public function test_it_includes_exception_details_in_fields()
    {
        $exception = new RuntimeException('Test exception message');
        $context = [
            'environment' => 'production',
            'server' => 'web-server-01',
            'request' => [
                'url' => 'https://example.com/api/users',
                'method' => 'POST',
            ],
        ];

        $notification = new SlackErrorNotification($exception, $context);

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('RuntimeException', $fields['Exception']);
        $this->assertEquals('Test exception message', $fields['Message']);
        $this->assertEquals('POST', $fields['Method']);
        $this->assertEquals('https://example.com/api/users', $fields['URL']);
        $this->assertEquals('production', $fields['Environment']);
        $this->assertEquals('web-server-01', $fields['Server']);
        $this->assertEquals('Anonymous', $fields['User']);
    }

    public function test_it_includes_user_info_when_available()
    {
        $exception = new RuntimeException('Test exception');
        $context = [
            'user' => [
                'id' => 123,
                'email' => 'test@example.com',
                'name' => 'Test User',
            ],
        ];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('ID: 123 (test@example.com)', $fields['User']);
    }

    public function test_it_handles_user_without_email()
    {
        $exception = new RuntimeException('Test exception');
        $context = [
            'user' => [
                'id' => 456,
                'name' => 'Test User',
            ],
        ];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('ID: 456', $fields['User']);
    }

    public function test_it_handles_unknown_user_id()
    {
        $exception = new RuntimeException('Test exception');
        $context = [
            'user' => [
                'email' => 'test@example.com',
            ],
        ];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('ID: Unknown (test@example.com)', $fields['User']);
    }

    public function test_it_formats_file_path_relative_to_base_path()
    {
        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getFormattedFilePath');
        $method->setAccessible(true);

        $formattedPath = $method->invoke($notification);

        // Should remove the base path from the file path
        $this->assertStringStartsNotWith(base_path(), $formattedPath);
        $this->assertStringContainsString('tests/Unit/SlackErrorNotificationTest.php', $formattedPath);
    }

    public function test_it_gets_correct_colors_for_different_severities()
    {
        $testCases = [
            [new TypeError('Type error'), 'danger'], // Default color
            [new QueryException('mysql', 'SELECT', [], new PDOException), 'danger'], // Default color
            [new class extends \Exception
            {
                public function getStatusCode(): int
                {
                    return 500;
                }
            }, 'warning'], // Default color
            [new RuntimeException('Runtime error'), '#ff9500'], // Default color
        ];

        foreach ($testCases as [$exception, $expectedColor]) {
            $notification = new SlackErrorNotification($exception, []);

            $reflection = new \ReflectionClass($notification);
            $method = $reflection->getMethod('getColorBySeverity');
            $method->setAccessible(true);

            $color = $method->invoke($notification);

            $this->assertEquals($expectedColor, $color);
        }
    }

    public function test_it_respects_custom_colors_configuration()
    {
        config(['errly.notifications.colors' => [
            'critical' => '#ff0000',
            'high' => '#ff8800',
            'medium' => '#ffcc00',
            'low' => '#00ff00',
        ]]);

        $exception = new TypeError('Type error');
        $notification = new SlackErrorNotification($exception, []);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getColorBySeverity');
        $method->setAccessible(true);

        $color = $method->invoke($notification);

        $this->assertEquals('#ff0000', $color);
    }

    public function test_it_formats_stack_trace_when_enabled()
    {
        config(['errly.context.include_stack_trace' => true]);

        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getFormattedStackTrace');
        $method->setAccessible(true);

        $stackTrace = $method->invoke($notification);

        $this->assertIsString($stackTrace);
        $this->assertNotEmpty($stackTrace);
    }

    public function test_it_truncates_long_stack_traces()
    {
        config(['errly.context.include_stack_trace' => true]);
        config(['errly.context.max_stack_trace_length' => 100]);

        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getFormattedStackTrace');
        $method->setAccessible(true);

        $stackTrace = $method->invoke($notification);

        $this->assertLessThanOrEqual(120, strlen($stackTrace)); // 100 + "... (truncated)"
        $this->assertStringContainsString('(truncated)', $stackTrace);
    }

    public function test_it_uses_custom_slack_configuration()
    {
        config([
            'errly.slack.channel' => '#custom-errors',
            'errly.slack.username' => 'Custom Bot',
            'errly.slack.emoji' => '⚠️',
            'errly.notifications.app_name' => 'My Custom App',
        ]);

        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);
        $slackMessage = $notification->toSlack(null);

        $this->assertEquals('#custom-errors', $slackMessage->channel);
        $this->assertEquals('Custom Bot', $slackMessage->username);
        $this->assertStringContainsString('⚠️', $slackMessage->content);
        $this->assertStringContainsString('My Custom App', $slackMessage->content);
    }

    public function test_it_handles_exception_without_message()
    {
        $exception = new RuntimeException('');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('No message provided', $fields['Message']);
    }

    public function test_it_handles_missing_request_context()
    {
        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('N/A', $fields['URL']);
        $this->assertEquals('N/A', $fields['Method']);
    }

    public function test_it_handles_missing_environment_context()
    {
        $exception = new RuntimeException('Test exception');
        $context = [];

        $notification = new SlackErrorNotification($exception, $context);

        $reflection = new \ReflectionClass($notification);
        $method = $reflection->getMethod('getErrorFields');
        $method->setAccessible(true);

        $fields = $method->invoke($notification);

        $this->assertEquals('Unknown', $fields['Environment']);
        $this->assertEquals('Unknown', $fields['Server']);
    }

    public function test_it_returns_correct_notification_channels()
    {
        $exception = new RuntimeException('Test exception');
        $notification = new SlackErrorNotification($exception, []);

        $channels = $notification->via(null);

        $this->assertEquals(['slack'], $channels);
    }
}
