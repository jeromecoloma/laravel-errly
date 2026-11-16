<?php

namespace Errly\LaravelErrly\App;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Draws a QR code with half-block characters, black on white whatever the
 * terminal's own colours, so it scans on light and dark themes alike.
 */
final class TerminalQr
{
    private const QUIET_ZONE = 4;

    public static function render(string $text): string
    {
        $matrix = Encoder::encode($text, ErrorCorrectionLevel::L())->getMatrix();
        $size = $matrix->getWidth();
        $padded = $size + self::QUIET_ZONE * 2;

        $dark = function (int $x, int $y) use ($matrix, $size): bool {
            $x -= self::QUIET_ZONE;
            $y -= self::QUIET_ZONE;

            return $x >= 0 && $y >= 0 && $x < $size && $y < $size && $matrix->get($x, $y) === 1;
        };

        $lines = [];

        for ($y = 0; $y < $padded; $y += 2) {
            $line = '';

            for ($x = 0; $x < $padded; $x++) {
                $line .= match ([$dark($x, $y), $dark($x, $y + 1)]) {
                    [true, true] => '█',
                    [true, false] => '▀',
                    [false, true] => '▄',
                    default => ' ',
                };
            }

            $lines[] = "\e[30;107m{$line}\e[0m";
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
