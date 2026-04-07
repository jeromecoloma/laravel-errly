<?php

namespace Errly\LaravelErrly\Support;

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
