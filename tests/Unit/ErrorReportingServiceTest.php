<?php

namespace Errly\LaravelErrly\Tests\Unit;

use Errly\LaravelErrly\Notifications\SlackErrorNotification;
use Errly\LaravelErrly\Services\ErrorContextService;
use Errly\LaravelErrly\Services\ErrorFilterService;
use Errly\LaravelErrly\Services\ErrorReportingService;
use Errly\LaravelErrly\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

class ErrorReportingServiceTest extends TestCase
{
    private ErrorReportingService $reportingService;

    private ErrorFilterService|MockObject $filterService;

    private ErrorContextService|MockObject $contextService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filterService = $this->createMock(ErrorFilterService::class);
        $this->contextService = $this->createMock(ErrorContextService::class);
        $this->reportingService = new ErrorReportingService($this->filterService, $this->contextService);
    }

    public function test_it_handles_exception_when_should_report_is_true()
    {
        $exception = new RuntimeException('Test exception');
        $context = ['environment' => 'testing'];

        $this->filterService
            ->expects($this->once())
            ->method('shouldReport')
            ->with($exception)
            ->willReturn(true);

        $this->contextService
            ->expects($this->once())
            ->method('gather')
            ->willReturn($context);

        config(['errly.rate_limiting.enabled' => false]);
        config(['errly.slack.webhook_url' => 'https://hooks.slack.com/test']);

        Notification::fake();

        $this->reportingService->handleException($exception);

        Notification::assertSentOnDemand(

            SlackErrorNotification::class
        );
    }

    public function test_it_does_not_handle_exception_when_should_report_is_false()
    {
        $exception = new RuntimeException('Test exception');

        $this->filterService
            ->expects($this->once())
            ->method('shouldReport')
            ->with($exception)
            ->willReturn(false);

        $this->contextService
            ->expects($this->never())
            ->method('gather');

        Notification::fake();

        $this->reportingService->handleException($exception);

        Notification::assertNothingSent();
    }

    public function test_it_respects_rate_limiting_when_enabled()
    {
        $exception = new RuntimeException('Test exception');

        $this->filterService
            ->expects($this->once())
            ->method('shouldReport')
            ->with($exception)
            ->willReturn(true);

        config(['errly.rate_limiting.enabled' => true]);
        config(['errly.rate_limiting.max_per_minute' => 2]);

        // Simulate that we've already hit the rate limit
        $rateLimitKey = 'errly_rate_limit:'.md5(get_class($exception).$exception->getMessage().$exception->getFile().$exception->getLine());
        Cache::put($rateLimitKey, 3, 60); // 3 > max_per_minute (2)

        $this->contextService
            ->expects($this->never())
            ->method('gather');

        Notification::fake();

        $this->reportingService->handleException($exception);

        Notification::assertNothingSent();
    }

    public function test_it_increments_rate_limit_counter_when_reporting()
    {
        $exception = new RuntimeException('Test exception');
        $context = ['environment' => 'testing'];

        $this->filterService
            ->expects($this->once())
            ->method('shouldReport')
            ->with($exception)
            ->willReturn(true);

        $this->contextService
            ->expects($this->once())
            ->method('gather')
            ->willReturn($context);

        config(['errly.rate_limiting.enabled' => true]);
        config(['errly.rate_limiting.max_per_minute' => 10]);
        config(['errly.slack.webhook_url' => 'https://hooks.slack.com/test']);

        $rateLimitKey = 'errly_rate_limit:'.md5(get_class($exception).$exception->getMessage().$exception->getFile().$exception->getLine());

        // Ensure cache is clear
        Cache::forget($rateLimitKey);

        Notification::fake();

        $this->reportingService->handleException($exception);

        // Check that the rate limit counter was incremented
        $this->assertEquals(1, Cache::get($rateLimitKey));
    }

    public function test_it_does_not_send_notification_when_webhook_url_is_missing()
    {
        $exception = new RuntimeException('Test exception');
        $context = ['environment' => 'testing'];

        $this->filterService
            ->expects($this->once())
            ->method('shouldReport')
            ->with($exception)
            ->willReturn(true);

        $this->contextService
            ->expects($this->once())
            ->method('gather')
            ->willReturn($context);

        config(['errly.rate_limiting.enabled' => false]);
        config(['errly.slack.webhook_url' => null]);

        Notification::fake();

        $this->reportingService->handleException($exception);

        Notification::assertNothingSent();
    }

    public function test_it_manually_reports_exception_with_custom_context()
    {
        $exception = new RuntimeException('Test exception');
        $gatheredContext = ['environment' => 'testing'];
        $customContext = ['custom' => 'data'];

        $this->contextService
            ->expects($this->once())
            ->method('gather')
            ->willReturn($gatheredContext);

        config(['errly.slack.webhook_url' => 'https://hooks.slack.com/test']);

        Notification::fake();

        $this->reportingService->report($exception, $customContext);

        Notification::assertSentOnDemand(

            SlackErrorNotification::class
        );
    }

    public function test_it_manually_reports_exception_without_custom_context()
    {
        $exception = new RuntimeException('Test exception');
        $gatheredContext = ['environment' => 'testing'];

        $this->contextService
            ->expects($this->once())
            ->method('gather')
            ->willReturn($gatheredContext);

        config(['errly.slack.webhook_url' => 'https://hooks.slack.com/test']);

        Notification::fake();

        $this->reportingService->report($exception);

        Notification::assertSentOnDemand(

            SlackErrorNotification::class
        );
    }

    public function test_it_generates_correct_rate_limit_key()
    {
        $exception = new RuntimeException('Test message');

        $expectedKey = 'errly_rate_limit:'.md5(
            get_class($exception).
            $exception->getMessage().
            $exception->getFile().
            $exception->getLine()
        );

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($this->reportingService);
        $method = $reflection->getMethod('getRateLimitKey');
        $method->setAccessible(true);

        $actualKey = $method->invoke($this->reportingService, $exception);

        $this->assertEquals($expectedKey, $actualKey);
    }

    public function test_it_uses_custom_rate_limit_cache_prefix()
    {
        config(['errly.rate_limiting.cache_key_prefix' => 'custom_prefix']);

        $exception = new RuntimeException('Test message');

        $expectedKey = 'custom_prefix:'.md5(
            get_class($exception).
            $exception->getMessage().
            $exception->getFile().
            $exception->getLine()
        );

        $reflection = new \ReflectionClass($this->reportingService);
        $method = $reflection->getMethod('getRateLimitKey');
        $method->setAccessible(true);

        $actualKey = $method->invoke($this->reportingService, $exception);

        $this->assertEquals($expectedKey, $actualKey);
    }

    public function test_it_allows_reporting_when_rate_limiting_is_disabled()
    {
        $exception = new RuntimeException('Test exception');

        config(['errly.rate_limiting.enabled' => false]);

        $reflection = new \ReflectionClass($this->reportingService);
        $method = $reflection->getMethod('isRateLimited');
        $method->setAccessible(true);

        $isRateLimited = $method->invoke($this->reportingService, $exception);

        $this->assertFalse($isRateLimited);
    }

    public function test_it_allows_reporting_when_under_rate_limit()
    {
        $exception = new RuntimeException('Test exception');

        config(['errly.rate_limiting.enabled' => true]);
        config(['errly.rate_limiting.max_per_minute' => 10]);

        $rateLimitKey = 'errly_rate_limit:'.md5(get_class($exception).$exception->getMessage().$exception->getFile().$exception->getLine());
        Cache::put($rateLimitKey, 5, 60); // 5 < 10 (max_per_minute)

        $reflection = new \ReflectionClass($this->reportingService);
        $method = $reflection->getMethod('isRateLimited');
        $method->setAccessible(true);

        $isRateLimited = $method->invoke($this->reportingService, $exception);

        $this->assertFalse($isRateLimited);
    }

    public function test_it_blocks_reporting_when_over_rate_limit()
    {
        $exception = new RuntimeException('Test exception');

        config(['errly.rate_limiting.enabled' => true]);
        config(['errly.rate_limiting.max_per_minute' => 10]);

        $rateLimitKey = 'errly_rate_limit:'.md5(get_class($exception).$exception->getMessage().$exception->getFile().$exception->getLine());
        Cache::put($rateLimitKey, 15, 60); // 15 > 10 (max_per_minute)

        $reflection = new \ReflectionClass($this->reportingService);
        $method = $reflection->getMethod('isRateLimited');
        $method->setAccessible(true);

        $isRateLimited = $method->invoke($this->reportingService, $exception);

        $this->assertTrue($isRateLimited);
    }
}
