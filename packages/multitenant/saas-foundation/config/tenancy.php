<?php

return [

    'driver' => env('TENANCY_DRIVER', env('SAAS_TENANCY_MODE', 'shared')),

    'identifier' => [
        'type' => env('TENANCY_IDENTIFIER_TYPE', env('SAAS_TENANT_IDENTIFIER_TYPE', 'uuid')),
        'column' => env('TENANCY_IDENTIFIER_COLUMN', 'id'),
    ],

    'resolvers' => [
        'domain' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_DOMAIN_ENABLED', true),
            'priority' => (int) env('TENANCY_RESOLVER_DOMAIN_PRIORITY', 10),
        ],
        'header' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_HEADER_ENABLED', true),
            'header' => env('TENANCY_RESOLVER_HEADER', 'X-Tenant'),
            'priority' => (int) env('TENANCY_RESOLVER_HEADER_PRIORITY', 20),
        ],
        'api_token' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_API_TOKEN_ENABLED', true),
            'header' => env('TENANCY_RESOLVER_API_TOKEN_HEADER', 'X-Tenant-Token'),
            'priority' => (int) env('TENANCY_RESOLVER_API_TOKEN_PRIORITY', 30),
        ],
        'auth_user' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_AUTH_USER_ENABLED', true),
            'priority' => (int) env('TENANCY_RESOLVER_AUTH_USER_PRIORITY', 40),
        ],
        'route_param' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_ROUTE_PARAM_ENABLED', true),
            'param' => env('TENANCY_RESOLVER_ROUTE_PARAM', 'tenant'),
            'priority' => (int) env('TENANCY_RESOLVER_ROUTE_PARAM_PRIORITY', 50),
        ],
        'path' => [
            'enabled' => (bool) env('TENANCY_RESOLVER_PATH_ENABLED', false),
            'segment' => (int) env('TENANCY_RESOLVER_PATH_SEGMENT', 1),
            'priority' => (int) env('TENANCY_RESOLVER_PATH_PRIORITY', 60),
        ],
    ],

    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', ''))))),

    'database' => [
        'tenant_connection' => env('TENANCY_DB_CONNECTION', 'tenant'),
        'database' => env('TENANCY_DB_DATABASE_PATTERN', 'tenant_%s'),
        'host' => env('TENANCY_DB_HOST'),
        'port' => env('TENANCY_DB_PORT'),
        'username' => env('TENANCY_DB_USERNAME'),
        'password' => env('TENANCY_DB_PASSWORD'),
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
        'charset' => env('TENANCY_DB_CHARSET', 'utf8mb4'),
        'collation' => env('TENANCY_DB_COLLATION', 'utf8mb4_unicode_ci'),
        'foreign_keys' => (bool) env('TENANCY_DB_FOREIGN_KEYS', true),
        'migrations' => env('TENANCY_DB_MIGRATIONS_PATH', __DIR__.'/../database/migrations/tenant'),
        'seeder' => env('TENANCY_DB_SEEDER', 'Database\\Seeders\\TenantDatabaseSeeder'),
        'preserve_default_connection' => (bool) env('TENANCY_DB_PRESERVE_DEFAULT_CONNECTION', false),
    ],

    'cache' => [
        'store' => env('TENANCY_CACHE_STORE'),
        'prefix_pattern' => env('TENANCY_CACHE_PREFIX_PATTERN', 'tenant:%s:'),
    ],

    'filesystem' => [
        'disk' => env('TENANCY_FILESYSTEM_DISK'),
        'root' => env('TENANCY_FILESYSTEM_ROOT', 'tenants/%s'),
    ],

    'queue' => [
        'connection' => env('TENANCY_QUEUE_CONNECTION'),
        'prefix' => env('TENANCY_QUEUE_PREFIX', 'tenant:%s:'),
    ],

    'broadcasting' => [
        'connection' => env('TENANCY_BROADCASTING_CONNECTION'),
        'prefix' => env('TENANCY_BROADCASTING_PREFIX', 'tenant:%s:'),
    ],

];
