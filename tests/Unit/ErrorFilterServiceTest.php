<?php

namespace Errly\LaravelErrly\Tests\Unit;

use Errly\LaravelErrly\Services\ErrorFilterService;
use Errly\LaravelErrly\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use PDOException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use TypeError;

class ErrorFilterServiceTest extends TestCase
{
    private ErrorFilterService $filterService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterService = new ErrorFilterService();
        
        // Disable environment filtering by default for tests
        config(['errly.filters.environments.enabled' => false]);
    }


    public function test_it_reports_exceptions_by_default()
    {
        $exception = new RuntimeException('Test exception');
        
        $this->assertTrue($this->filterService->shouldReport($exception));
    }


    public function test_it_filters_out_ignored_exceptions()
    {
        // Test ValidationException (in ignored list)
        $validationException = new ValidationException(
            validator([], ['field' => 'required'])
        );
        
        $this->assertFalse($this->filterService->shouldReport($validationException));
        
        // Test NotFoundHttpException (in ignored list)
        $notFoundException = new NotFoundHttpException('Page not found');
        
        $this->assertFalse($this->filterService->shouldReport($notFoundException));
    }


    public function test_it_reports_critical_exceptions()
    {
        // Test TypeError (critical exception)
        $typeError = new TypeError('Type error occurred');
        
        $this->assertTrue($this->filterService->shouldReport($typeError));
        
        // Test QueryException (critical exception)
        $queryException = new QueryException(
            'mysql',
            'SELECT * FROM users',
            [],
            new PDOException('Connection failed')
        );
        
        $this->assertTrue($this->filterService->shouldReport($queryException));
        
        // Test PDOException (critical exception)
        $pdoException = new PDOException('Database connection failed');
        
        $this->assertTrue($this->filterService->shouldReport($pdoException));
    }


    public function test_it_reports_server_errors()
    {
        // Create a mock exception with getStatusCode method
        $serverError = new HttpResponseException(
            response('Server Error', 500)
        );
        
        $this->assertTrue($this->filterService->shouldReport($serverError));
        
        // Test with 404 (should not be reported as server error, but might be ignored)
        $clientError = new HttpResponseException(
            response('Not Found', 404)
        );
        
        // This should return true because it's not in ignored exceptions
        // and not a server error, but still reportable by default
        $this->assertTrue($this->filterService->shouldReport($clientError));
    }


    public function test_it_respects_environment_filtering_when_enabled()
    {
        // Enable environment filtering
        config(['errly.filters.environments.enabled' => true]);
        config(['errly.filters.environments.allowed' => ['production', 'staging']]);
        
        // Set current environment to 'testing' (not in allowed list)
        app()->detectEnvironment(function () {
            return 'testing';
        });
        
        $exception = new RuntimeException('Test exception');
        
        $this->assertFalse($this->filterService->shouldReport($exception));
    }


    public function test_it_allows_all_environments_when_filtering_disabled()
    {
        // Disable environment filtering
        config(['errly.filters.environments.enabled' => false]);
        
        // Set current environment to 'testing'
        app()->detectEnvironment(function () {
            return 'testing';
        });
        
        $exception = new RuntimeException('Test exception');
        
        $this->assertTrue($this->filterService->shouldReport($exception));
    }


    public function test_it_allows_exceptions_in_allowed_environments()
    {
        // Enable environment filtering
        config(['errly.filters.environments.enabled' => true]);
        config(['errly.filters.environments.allowed' => ['production', 'staging', 'testing']]);
        
        // Set current environment to 'testing' (in allowed list)
        app()->detectEnvironment(function () {
            return 'testing';
        });
        
        $exception = new RuntimeException('Test exception');
        
        $this->assertTrue($this->filterService->shouldReport($exception));
    }


    public function test_it_handles_custom_ignored_exceptions()
    {
        // Add custom exception to ignored list
        config(['errly.filters.ignored_exceptions' => [
            ValidationException::class,
            NotFoundHttpException::class,
            RuntimeException::class, // Add RuntimeException to ignored
        ]]);
        
        $exception = new RuntimeException('This should be ignored');
        
        $this->assertFalse($this->filterService->shouldReport($exception));
    }


    public function test_it_handles_custom_critical_exceptions()
    {
        // Add custom exception to critical list
        config(['errly.filters.critical_exceptions' => [
            TypeError::class,
            QueryException::class,
            RuntimeException::class, // Add RuntimeException as critical
        ]]);
        
        $exception = new RuntimeException('This should be critical');
        
        $this->assertTrue($this->filterService->shouldReport($exception));
    }


    public function test_it_prioritizes_ignored_over_critical()
    {
        // Add same exception to both ignored and critical lists
        config(['errly.filters.ignored_exceptions' => [RuntimeException::class]]);
        config(['errly.filters.critical_exceptions' => [RuntimeException::class]]);
        
        $exception = new RuntimeException('Should be ignored despite being critical');
        
        // Ignored should take precedence
        $this->assertFalse($this->filterService->shouldReport($exception));
    }


    public function test_it_handles_inheritance_in_exception_filtering()
    {
        // Create a custom exception that extends RuntimeException
        $customException = new class('Custom exception') extends RuntimeException {};
        
        // Add RuntimeException to ignored list
        config(['errly.filters.ignored_exceptions' => [RuntimeException::class]]);
        
        // Should be ignored because it extends RuntimeException
        $this->assertFalse($this->filterService->shouldReport($customException));
    }


    public function test_it_handles_exceptions_without_status_code()
    {
        $exception = new RuntimeException('No status code');
        
        // Should be reported (default behavior)
        $this->assertTrue($this->filterService->shouldReport($exception));
    }


    public function test_it_handles_empty_configuration_arrays()
    {
        // Set empty arrays for exception lists
        config(['errly.filters.ignored_exceptions' => []]);
        config(['errly.filters.critical_exceptions' => []]);
        
        $exception = new RuntimeException('Test exception');
        
        // Should be reported (default behavior)
        $this->assertTrue($this->filterService->shouldReport($exception));
    }
} 