<?php

namespace Errly\LaravelErrly\Commands;

use Errly\LaravelErrly\Facades\Errly;
use Errly\LaravelErrly\Services\ErrorFilterService;
use Illuminate\Console\Command;

class TestErrorCommand extends Command
{
    protected $signature = 'errly:test {type=general : Type of error to test}';
    protected $description = 'Test Laravel Errly by throwing different types of exceptions';

    public function handle(): void
    {
        $type = $this->argument('type');

        $this->info("🚨 Testing Laravel Errly with error type: {$type}");
        $this->newLine();

        try {
            match ($type) {
                'database' => $this->testDatabaseError(),
                'critical' => $this->testCriticalError(),
                'validation' => $this->testValidationError(),
                'custom' => $this->testCustomError(),
                default => $this->testGeneralError(),
            };
        } catch (\Throwable $e) {
            // Check if this exception should be reported
            $filterService = app(ErrorFilterService::class);
            $shouldReport = $filterService->shouldReport($e);

            if ($shouldReport) {
                $this->warn("📤 Reporting exception to Errly...");

                Errly::report($e, [
                    'command' => 'errly:test',
                    'type' => $type,
                    'context' => 'console_test',
                    'timestamp' => now()->toISOString(),
                    'server' => gethostname(),
                ]);

                $this->info("✅ Exception reported to Errly - check your Slack!");
                $severity = $this->getSeverityLevel($e);
                $this->comment("🎯 Expected Slack severity: {$severity}");

            } else {
                $this->warn("🚫 Exception filtered out by Errly (as expected)");
                $this->info("✅ No Slack notification should be sent");
                $this->comment("💡 This exception type is in the ignored_exceptions list");
            }

            $this->newLine();

            // Show exception details
            $this->error("Exception Details:");
            $this->error("Type: " . get_class($e));
            $this->error("Message: " . $e->getMessage());
            $this->error("File: " . $e->getFile() . ":" . $e->getLine());
        }

        $this->newLine();
        $this->comment("🔍 Test completed. Check your Slack channel for notifications!");
    }

    protected function testDatabaseError(): void
    {
        $this->warn("🗄️  Throwing database error...");
        $this->comment("💡 This should trigger a CRITICAL severity notification");

        $previous = new \PDOException('Table "non_existent_table" not found');

        throw new \Illuminate\Database\QueryException(
            connectionName: 'mysql',
            sql: 'SELECT * FROM non_existent_table',
            bindings: [],
            previous: $previous
        );
    }

    protected function testCriticalError(): void
    {
        $this->warn("⚠️  Throwing critical error...");
        $this->comment("💡 This should trigger a CRITICAL severity notification");

        throw new \ErrorException('This is a critical test error from Laravel Errly');
    }

    protected function testValidationError(): void
    {
        $this->warn("📝 Throwing validation error...");
        $this->comment("💡 This should be IGNORED (no Slack notification)");

        throw new \Illuminate\Validation\ValidationException(
            validator([], ['required_field' => 'required'])
        );
    }

    protected function testCustomError(): void
    {
        $this->warn("🎯 Throwing custom error...");
        $this->comment("💡 This should trigger a MEDIUM severity notification");

        throw new \Exception('Custom test error from Laravel Errly - Check your Slack!');
    }

    protected function testGeneralError(): void
    {
        $this->warn("🚀 Throwing general error...");
        $this->comment("💡 This should trigger a MEDIUM severity notification");

        throw new \RuntimeException('General test error from Laravel Errly occurred');
    }

    protected function getSeverityLevel(\Throwable $exception): string
    {
        $criticalExceptions = [
            \ParseError::class,
            \TypeError::class,
            \Error::class,
            \ErrorException::class,
            \Illuminate\Database\QueryException::class,
            \PDOException::class,
        ];

        foreach ($criticalExceptions as $critical) {
            if ($exception instanceof $critical) {
                return 'CRITICAL';
            }
        }

        if (method_exists($exception, 'getStatusCode') && $exception->getStatusCode() >= 500) {
            return 'HIGH';
        }

        return 'MEDIUM';
    }
}
