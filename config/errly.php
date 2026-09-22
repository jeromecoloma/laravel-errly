<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel Errly - Error Monitoring
    |--------------------------------------------------------------------------
    |
    | Errly provides early error detection and beautiful Slack notifications
    | for your Laravel applications. Configure your preferences below.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Errly
    |--------------------------------------------------------------------------
    */
    'enabled' => filter_var(env('ERRLY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Slack Configuration
    |--------------------------------------------------------------------------
    */
    'slack' => [
        'webhook_url' => env('ERRLY_SLACK_WEBHOOK_URL'),
        'channel' => env('ERRLY_SLACK_CHANNEL', '#errors'),
        'username' => env('ERRLY_SLACK_USERNAME', 'Laravel Errly'),
        'emoji' => env('ERRLY_SLACK_EMOJI', '🚨'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Filtering
    |--------------------------------------------------------------------------
    */
    'filters' => [
        'environments' => [
            'enabled' => filter_var(env('ERRLY_FILTER_ENVIRONMENTS', true), FILTER_VALIDATE_BOOLEAN),
            'allowed' => array_filter(explode(',', env('ERRLY_ALLOWED_ENVIRONMENTS', 'production,staging'))),
        ],

        'ignored_exceptions' => [
            ValidationException::class,
            NotFoundHttpException::class,
            AuthenticationException::class,
            MethodNotAllowedHttpException::class,
            TooManyRequestsHttpException::class,
        ],

        'critical_exceptions' => [
            ParseError::class,
            TypeError::class,
            Error::class,
            ErrorException::class,
            QueryException::class,
            PDOException::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'enabled' => filter_var(env('ERRLY_RATE_LIMITING', true), FILTER_VALIDATE_BOOLEAN),
        'max_per_minute' => (int) env('ERRLY_MAX_PER_MINUTE', 10),
        'cache_key_prefix' => 'errly_rate_limit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Collection
    |--------------------------------------------------------------------------
    */
    'context' => [
        'include_user' => filter_var(env('ERRLY_INCLUDE_USER', true), FILTER_VALIDATE_BOOLEAN),
        'include_request' => filter_var(env('ERRLY_INCLUDE_REQUEST', true), FILTER_VALIDATE_BOOLEAN),
        'include_headers' => filter_var(env('ERRLY_INCLUDE_HEADERS', false), FILTER_VALIDATE_BOOLEAN),
        'include_stack_trace' => filter_var(env('ERRLY_INCLUDE_STACK_TRACE', true), FILTER_VALIDATE_BOOLEAN),
        'max_stack_trace_length' => (int) env('ERRLY_MAX_STACK_TRACE_LENGTH', 2000),
        'sensitive_headers' => [
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token',
            'x-csrf-token',
            'x-xsrf-token',
        ],

        'sensitive_fields' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
            'credit_card',
            'ssn',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Customization
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'app_name' => env('ERRLY_APP_NAME') ?: config('app.name', 'Laravel App'),
        'include_server_info' => filter_var(env('ERRLY_INCLUDE_SERVER_INFO', true), FILTER_VALIDATE_BOOLEAN),
        'colors' => [
            'critical' => env('ERRLY_COLOR_CRITICAL', 'danger'),
            'high' => env('ERRLY_COLOR_HIGH', 'warning'),
            'medium' => env('ERRLY_COLOR_MEDIUM', '#ff9500'),
            'low' => env('ERRLY_COLOR_LOW', 'good'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heartbeats
    |--------------------------------------------------------------------------
    |
    | A heartbeat pings an outside monitor every minute. When the pings stop,
    | the monitor alerts - which catches a dead scheduler or queue worker that
    | throws no exception. Needs `schedule:run` (or `schedule:work`) running.
    |
    | A check is a full ping URL, a Healthchecks.io check UUID, or a slug
    | (with ERRLY_HEALTHCHECKS_PING_KEY). A blank check is switched off.
    |
    | `queues` maps a queue name to a check. `default` means the connection's
    | default queue. Add more named queues here after publishing the config.
    |
    | ERRLY_ENABLED=false switches heartbeats off as well.
    |
    */
    'heartbeat' => [
        'enabled' => filter_var(env('ERRLY_HEARTBEAT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'client' => env('ERRLY_HEARTBEAT_CLIENT', 'healthchecks'),

        'scheduler' => env('ERRLY_HEARTBEAT_SCHEDULER'),
        'queues' => [
            'default' => env('ERRLY_HEARTBEAT_QUEUE_DEFAULT'),
        ],
        'queue_connection' => env('ERRLY_HEARTBEAT_QUEUE_CONNECTION'),

        'clients' => [
            'healthchecks' => [
                'url' => env('ERRLY_HEALTHCHECKS_URL', 'https://hc-ping.com'),
                'ping_key' => env('ERRLY_HEALTHCHECKS_PING_KEY'),
                // Creates a missing slug check on first ping. Healthchecks.io
                // gives it a 1 day period, so set it to 1 minute afterwards.
                'auto_provision' => filter_var(env('ERRLY_HEALTHCHECKS_AUTO_PROVISION', false), FILTER_VALIDATE_BOOLEAN),
                // The scheduler ping runs inline, so a slow monitor holds up the
                // tasks after it. A missed ping is covered by the check's grace.
                'timeout_seconds' => (int) env('ERRLY_HEALTHCHECKS_TIMEOUT', 5),
                'attempts' => (int) env('ERRLY_HEALTHCHECKS_ATTEMPTS', 1),
                'retry_delay_ms' => 1000,
            ],
        ],
    ],
];
