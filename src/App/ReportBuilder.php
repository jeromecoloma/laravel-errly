<?php

namespace Errly\LaravelErrly\App;

use Errly\LaravelErrly\ErrorReport;
use Illuminate\Support\Str;

/**
 * Turns an error report into the encrypted body the service accepts: a
 * summary that travels in the push and a full report fetched by the app.
 */
final class ReportBuilder
{
    public const SUMMARY_LIMIT = 3500;

    public const FULL_LIMIT = 32768;

    public const MESSAGE_LIMIT = 200;

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /**
     * @param  array{enc: string, fp: string}  $keys
     */
    public function __construct(
        private readonly string $channel,
        private readonly array $keys,
        private readonly bool $includePersonalData = true,
    ) {}

    /**
     * @return array{id: string, kind: string, fingerprint: string, summary: string, full: string}
     */
    public function build(ErrorReport $report): array
    {
        $id = strtolower((string) Str::uuid());
        $summary = $this->summary($report, $id);

        return [
            'id' => $id,
            'kind' => 'error',
            'fingerprint' => Protocol::fingerprint(ErrorReport::fingerprintInput($report->exception), $this->keys['fp']),
            'summary' => $this->seal($summary, $id, 'summary', self::SUMMARY_LIMIT, ['message', 'location', 'title']),
            'full' => $this->seal($this->full($report, $summary), $id, 'full', self::FULL_LIMIT, ['trace', 'message', 'location', 'title', 'server']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(ErrorReport $report, string $id): array
    {
        $exception = $report->exception;

        return [
            'v' => Protocol::VERSION,
            'id' => $id,
            'kind' => 'error',
            'severity' => strtolower($report->severity),
            'app' => (string) config('errly.notifications.app_name', 'Laravel App'),
            'environment' => (string) ($report->context['environment'] ?? app()->environment()),
            'title' => class_basename($exception),
            'message' => mb_substr($report->message, 0, self::MESSAGE_LIMIT),
            'location' => ErrorReport::relativeFile($exception).':'.$exception->getLine(),
            'suppressed' => $report->suppressed,
            'occurred_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function full(ErrorReport $report, array $summary): array
    {
        $context = $report->context;
        $full = $summary + [
            'exception' => get_class($report->exception),
            'server' => isset($context['server']) ? (string) $context['server'] : null,
        ];

        if ($this->includePersonalData) {
            $request = array_intersect_key((array) ($context['request'] ?? []), ['url' => true, 'method' => true]);
            $full['request'] = $request === [] ? null : $request;
            $full['user'] = $context['user'] ?? null;
        }

        $full['trace'] = $report->trace;

        return array_filter($full, fn ($value) => $value !== null);
    }

    /**
     * Seals the payload, cutting the listed fields in turn until the envelope
     * fits. Personal data goes last, if even that is not enough.
     *
     * @param  array<string, mixed>  $payload
     * @param  'summary'|'full'  $part
     * @param  list<string>  $trimmable
     */
    private function seal(array $payload, string $id, string $part, int $limit, array $trimmable): string
    {
        $json = json_encode($payload, self::JSON_FLAGS);

        foreach ($trimmable as $field) {
            while (self::excess($json, $limit) > 0 && ($payload[$field] ?? '') !== '') {
                $value = (string) $payload[$field];
                // Escaping makes some characters longer in JSON, so cut in proportion.
                $encoded = strlen((string) json_encode($value, self::JSON_FLAGS)) - 2;
                $cut = (int) ceil(self::excess($json, $limit) * strlen($value) / max(1, $encoded));
                $payload[$field] = mb_strcut($value, 0, max(0, strlen($value) - $cut));
                $json = json_encode($payload, self::JSON_FLAGS);
            }
        }

        if (self::excess($json, $limit) > 0) {
            unset($payload['request'], $payload['user']);
            $json = json_encode($payload, self::JSON_FLAGS);
        }

        return Protocol::seal($json, Protocol::aad($this->channel, $id, $part), $this->keys['enc']);
    }

    /**
     * How many plaintext bytes must go for the envelope to fit; zero or less
     * when it already does.
     */
    private static function excess(string $json, int $limit): int
    {
        return (int) ceil((Protocol::envelopeLength(strlen($json)) - $limit) * 3 / 4);
    }
}
