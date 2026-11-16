<?php

namespace Errly\LaravelErrly\Support;

/**
 * Sets values in a .env file, replacing a line that is already there.
 */
final class EnvFile
{
    /**
     * @param  array<string, string>  $values
     */
    public static function set(string $path, array $values): void
    {
        $contents = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                $contents = (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $contents);
            } else {
                $contents = rtrim($contents, "\n").($contents === '' ? '' : "\n").$line."\n";
            }
        }

        file_put_contents($path, $contents, LOCK_EX);
    }
}
