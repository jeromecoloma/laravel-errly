<?php

namespace Errly\LaravelErrly;

use Errly\LaravelErrly\Support\SensitiveDataRedactor;
use Throwable;

/**
 * One reported error, worked out once and shared by every channel: severity,
 * the redacted message, the summary fields and the redacted stack trace.
 */
final class ErrorReport
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public readonly Throwable $exception,
        public readonly array $context,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $fields,
        public readonly string $trace,
        public readonly int $suppressed = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromException(Throwable $exception, array $context = [], int $suppressed = 0): self
    {
        $message = self::redactedMessage($exception);

        return new self(
            exception: $exception,
            context: $context,
            severity: self::severity($exception),
            message: $message,
            fields: self::fields($exception, $context, $message),
            trace: self::trace($exception),
            suppressed: $suppressed,
        );
    }

    /**
     * The file relative to the app, without a leading slash.
     */
    public static function relativeFile(Throwable $exception): string
    {
        return ltrim(str_replace(base_path(), '', $exception->getFile()), '/\\');
    }

    /**
     * What identifies an error across reports. No message text, because
     * messages often carry IDs.
     */
    public static function fingerprintInput(Throwable $exception): string
    {
        return get_class($exception).'|'.self::relativeFile($exception).'|'.$exception->getLine();
    }

    private static function severity(Throwable $exception): string
    {
        foreach (config('errly.filters.critical_exceptions', []) as $critical) {
            if ($exception instanceof $critical) {
                return 'CRITICAL';
            }
        }

        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = call_user_func([$exception, 'getStatusCode']);
            if ($statusCode >= 500) {
                return 'HIGH';
            }
        }

        return 'MEDIUM';
    }

    private static function redactedMessage(Throwable $exception): string
    {
        $message = $exception->getMessage() ?: 'No message provided';

        if (config('errly.context.redact_sql_bindings', true)) {
            $message = SensitiveDataRedactor::redactDatabaseValues($exception, $message);
        }

        return SensitiveDataRedactor::redactString($message, config('errly.context.sensitive_fields', [])) ?? $message;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private static function fields(Throwable $exception, array $context, string $message): array
    {
        return [
            'Exception' => get_class($exception),
            'Message' => $message,
            'File' => str_replace(base_path(), '', $exception->getFile()),
            'Line' => $exception->getLine(),
            'URL' => $context['request']['url'] ?? 'N/A',
            'Method' => $context['request']['method'] ?? 'N/A',
            'User' => self::userInfo($context),
            'Environment' => $context['environment'] ?? 'Unknown',
            'Server' => $context['server'] ?? 'Unknown',
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function userInfo(array $context): string
    {
        if (! isset($context['user'])) {
            return 'Anonymous';
        }

        $user = $context['user'];
        $info = 'ID: '.($user['id'] ?? 'Unknown');

        if (isset($user['email'])) {
            $info .= ' ('.$user['email'].')';
        }

        return $info;
    }

    private static function trace(Throwable $exception): string
    {
        $trace = $exception->getTraceAsString();
        $maxLength = config('errly.context.max_stack_trace_length', 2000);
        $trace = SensitiveDataRedactor::redactString($trace, config('errly.context.sensitive_fields', [])) ?? $trace;

        if (strlen($trace) > $maxLength) {
            $trace = substr($trace, 0, $maxLength)."\n... (truncated)";
        }

        return $trace;
    }
}
