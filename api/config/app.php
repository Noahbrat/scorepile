<?php

use Cake\Cache\Engine\FileEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;

return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    /*
     * CORS Configuration
     */
    'Cors' => [
        'allowedOrigins' => explode(',', env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5173',
        )),
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowedHeaders' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],
        'allowCredentials' => filter_var(env('CORS_ALLOW_CREDENTIALS', true), FILTER_VALIDATE_BOOLEAN),
        'maxAge' => (int)env('CORS_MAX_AGE', 3600),
    ],

    /*
     * Application configuration
     */
    'App' => [
        'namespace' => 'App',
        'encoding' => env('APP_ENCODING', 'UTF-8'),
        'defaultLocale' => env('APP_DEFAULT_LOCALE', 'en_US'),
        'defaultTimezone' => env('APP_DEFAULT_TIMEZONE', 'UTC'),
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        'fullBaseUrl' => false,
        'imageBaseUrl' => 'img/',
        'cssBaseUrl' => 'css/',
        'jsBaseUrl' => 'js/',
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [ROOT . DS . 'templates' . DS],
            'locales' => [RESOURCES . 'locales' . DS],
        ],
    ],

    /*
     * Security and encryption
     */
    'Security' => [
        'salt' => env('SECURITY_SALT'),
        'jwtSecret' => env('JWT_SECRET', null),
    ],

    'Asset' => [
        // 'timestamp' => true,
        // 'cacheTime' => '+1 year'
    ],

    /*
     * Cache configuration
     */
    'Cache' => [
        'default' => [
            'className' => FileEngine::class,
            'path' => CACHE,
            'url' => env('CACHE_DEFAULT_URL', null),
        ],
        '_cake_core_' => [
            'className' => FileEngine::class,
            'prefix' => 'myapp_cake_core_',
            'path' => CACHE . 'persistent' . DS,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKECORE_URL', null),
        ],
        '_cake_model_' => [
            'className' => FileEngine::class,
            'prefix' => 'myapp_cake_model_',
            'path' => CACHE . 'models' . DS,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKEMODEL_URL', null),
        ],
        '_cake_routes_' => [
            'className' => FileEngine::class,
            'prefix' => 'myapp_cake_routes_',
            'path' => CACHE,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKEROUTES_URL', null),
        ],
    ],

    /*
     * Error handling
     */
    'Error' => [
        'errorLevel' => E_ALL,
        'skipLog' => [],
        'log' => true,
        'trace' => true,
        'ignoredDeprecationPaths' => [],
        'exceptionRenderer' => 'App\Error\AppExceptionRenderer',
    ],

    'Debugger' => [
        'editor' => 'phpstorm',
    ],

    /*
     * Email configuration
     */
    'EmailTransport' => [
        'default' => [
            'className' => MailTransport::class,
            'host' => 'localhost',
            'port' => 25,
            'timeout' => 30,
            'client' => null,
            'tls' => false,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],

    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => [
                env('EMAIL_FROM_ADDRESS', 'noreply@example.com') => env('EMAIL_FROM_NAME', 'Scorepile'),
            ],
            'charset' => 'utf-8',
            'headerCharset' => 'utf-8',
        ],
    ],

    /*
     * Database configuration
     */
    'Datasources' => [
        'default' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            'timezone' => 'UTC',
            'flags' => [],
            'cacheMetadata' => true,
            'log' => false,
            'quoteIdentifiers' => false,
        ],
        'test' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            'timezone' => 'UTC',
            'encoding' => 'utf8mb4',
            'flags' => [],
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'log' => false,
        ],
    ],

    /*
     * Logging
     */
    'Log' => [
        'debug' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'debug',
            'url' => env('LOG_DEBUG_URL', null),
            'scopes' => null,
            'levels' => ['notice', 'info', 'debug'],
        ],
        'error' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'error',
            'url' => env('LOG_ERROR_URL', null),
            'scopes' => null,
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ],
    ],

    'Session' => [
        'defaults' => 'php',
    ],

    /*
     * Rate Limiting Configuration
     */
    'RateLimit' => [
        'auth_endpoints' => [
            'jwt_login' => [
                'requests_per_minute' => (int)env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
                'requests_per_hour' => (int)env('RATE_LIMIT_LOGIN_PER_HOUR', 20),
                'burst_allowance' => (int)env('RATE_LIMIT_LOGIN_BURST', 2),
            ],
            'jwt_refresh' => [
                'requests_per_minute' => (int)env('RATE_LIMIT_REFRESH_PER_MINUTE', 10),
                'requests_per_hour' => (int)env('RATE_LIMIT_REFRESH_PER_HOUR', 60),
                'burst_allowance' => (int)env('RATE_LIMIT_REFRESH_BURST', 5),
            ],
            'register' => [
                'requests_per_minute' => (int)env('RATE_LIMIT_REGISTER_PER_MINUTE', 3),
                'requests_per_hour' => (int)env('RATE_LIMIT_REGISTER_PER_HOUR', 10),
                'burst_allowance' => (int)env('RATE_LIMIT_REGISTER_BURST', 1),
            ],
        ],
        'cache_config' => env('RATE_LIMIT_CACHE_CONFIG', 'default'),
        'header_prefix' => env('RATE_LIMIT_HEADER_PREFIX', 'X-RateLimit-'),
    ],
];
