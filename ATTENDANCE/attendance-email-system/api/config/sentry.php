<?php

return [
    /*
     * DSN — leave empty or unset to disable Sentry entirely.
     * Get your DSN from https://sentry.io → Project → SDK Setup.
     */
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    'profiles_sample_rate' => 0.0,

    'environment' => env('APP_ENV', 'production'),

    'release' => env('APP_VERSION'),

    'send_default_pii' => false,

    'breadcrumbs' => [
        'sql_queries'  => true,
        'sql_bindings' => false,
        'queue_info'   => true,
        'command_info' => true,
    ],

    // These exception types are not worth noise in Sentry
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    ],
];
