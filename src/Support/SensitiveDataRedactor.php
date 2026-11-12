<?php

namespace Errly\LaravelErrly\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use PDOException;
use Throwable;

class SensitiveDataRedactor
{
    /**
     * @param  array<int, string>  $sensitiveFields
     */
    public static function redactArray(array $data, array $sensitiveFields = []): array
    {
        return self::redactArrayAtPath($data, self::normalizeFields($sensitiveFields));
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    public static function redactString(?string $value, array $sensitiveFields = []): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $patterns = array_filter(array_map(
            static fn (string $field): string => preg_quote(strtolower($field), '/'),
            self::normalizeFields($sensitiveFields)
        ));

        if ($patterns === []) {
            return $value;
        }

        $fieldPattern = implode('|', $patterns);
        $sanitized = preg_replace(
            [
                "/\\b({$fieldPattern})\\b\\s*([=:])\\s*([^\\s,;]+)/iu",
                '/\\b(bearer)\\s+([^\\s]+)/iu',
            ],
            [
                '$1$2 [REDACTED]',
                '$1 [REDACTED]',
            ],
            $value
        );

        return $sanitized ?? $value;
    }

    /**
     * Strip the values a database error carries in its message: the bound
     * query values, and the row values the driver repeats (duplicate keys,
     * rejected column values, failing rows). The query keeps its placeholders.
     */
    public static function redactDatabaseValues(Throwable $exception, string $message): string
    {
        if ($exception instanceof QueryException) {
            $message = self::replaceBoundSql($exception, $message);
        }

        if (! $exception instanceof QueryException && ! $exception instanceof PDOException) {
            return $message;
        }

        $redacted = preg_replace(
            [
                "/(Duplicate entry )'.*'( for key )/s",
                "/(Incorrect \\w+ value: )'.*?'( for column )/s",
                '/(Key \\(.*?\\)=\\().*?(\\) already exists)/s',
                '/(Failing row contains \\().*?(\\)\\.)/s',
            ],
            [
                "$1'[REDACTED]'$2",
                "$1'[REDACTED]'$2",
                '$1[REDACTED]$2',
                '$1[REDACTED]$2',
            ],
            $message
        );

        return $redacted ?? $message;
    }

    protected static function replaceBoundSql(QueryException $exception, string $message): string
    {
        $sql = $exception->getSql();
        $boundSql = Str::replaceArray('?', $exception->getBindings(), $sql);

        if ($boundSql !== '' && str_contains($message, $boundSql)) {
            return str_replace($boundSql, $sql, $message);
        }

        return preg_replace_callback('/, SQL: .*\)$/s', static fn (): string => ', SQL: '.$sql.')', $message) ?? $message;
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     * @return array<int, string>
     */
    protected static function normalizeFields(array $sensitiveFields): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $field): string => strtolower(trim((string) $field)),
            $sensitiveFields
        ))));
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    protected static function redactArrayAtPath(array $data, array $sensitiveFields, string $path = ''): array
    {
        foreach ($data as $key => $value) {
            $keyName = strtolower((string) $key);
            $keyPath = ltrim($path.'.'.$keyName, '.');

            if (self::shouldRedactKey($keyName, $keyPath, $sensitiveFields)) {
                $data[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redactArrayAtPath($value, $sensitiveFields, $keyPath);
            }
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    protected static function shouldRedactKey(string $key, string $path, array $sensitiveFields): bool
    {
        foreach ($sensitiveFields as $sensitiveField) {
            if ($sensitiveField === $key || $sensitiveField === $path) {
                return true;
            }
        }

        return false;
    }
}
