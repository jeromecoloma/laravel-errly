<?php

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
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
            \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class,
        ],

        'critical_exceptions' => [
            \ParseError::class,
            \TypeError::class,
            \Error::class,
            \ErrorException::class,
            \Illuminate\Database\QueryException::class,
            \PDOException::class,
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

        'sensitive_fields' => [
            'password',
            'password_confirmation',
            'token',
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
];
