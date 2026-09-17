<?php

return [

    'mode' => env('SAAS_TENANCY_MODE', 'shared'),

    'identifier' => [
        'type' => env('SAAS_TENANT_IDENTIFIER_TYPE', 'uuid'),
        'column' => 'id',
    ],

    'central_domain' => env('SAAS_CENTRAL_DOMAIN'),

    'models' => [
        'tenant' => env('SAAS_TENANT_MODEL', 'SaasFoundation\\Models\\Tenant'),
        'user' => env('SAAS_USER_MODEL', 'SaasFoundation\\Models\\User'),
    ],

    'auth' => [
        'guard' => env('SAAS_AUTH_GUARD', 'web'),
        'redirect_to' => env('SAAS_AUTH_REDIRECT_TO', '/dashboard'),
        'session_key' => env('SAAS_AUTH_SESSION_KEY', 'tenant_id'),
        'registration' => [
            'enabled' => (bool) env('SAAS_REGISTRATION_ENABLED', true),
            'creates_tenant' => (bool) env('SAAS_REGISTRATION_CREATES_TENANT', true),
        ],
        'login' => [
            'attempts' => (int) env('SAAS_LOGIN_ATTEMPTS', 5),
            'lockout_minutes' => (int) env('SAAS_LOGIN_LOCKOUT_MINUTES', 15),
        ],
    ],

    'authorization' => [
        'cache_enabled' => (bool) env('SAAS_AUTHORIZATION_CACHE_ENABLED', true),
        'cache_store' => env('SAAS_AUTHORIZATION_CACHE_STORE'),
        'cache_ttl' => (int) env('SAAS_AUTHORIZATION_CACHE_TTL', 3600),
        'super_admin_bypass' => (bool) env('SAAS_AUTHORIZATION_SUPER_ADMIN_BYPASS', true),
    ],

    'roles_permissions' => [
        'guard' => env('SAAS_ROLES_GUARD', 'web'),
        'default_roles' => [
            'super_admin' => env('SAAS_DEFAULT_ROLE_SUPER_ADMIN', 'super-admin'),
            'admin' => env('SAAS_DEFAULT_ROLE_ADMIN', 'admin'),
            'member' => env('SAAS_DEFAULT_ROLE_MEMBER', 'member'),
        ],
        'cache' => [
            'enabled' => (bool) env('SAAS_ROLES_CACHE_ENABLED', true),
            'ttl' => (int) env('SAAS_ROLES_CACHE_TTL', 3600),
        ],
    ],

    'membership' => [
        'default_status' => env('SAAS_MEMBERSHIP_DEFAULT_STATUS', 'active'),
        'default_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('SAAS_MEMBERSHIP_DEFAULT_ROLES', 'member'))))),
        'statuses' => ['active', 'suspended', 'inactive'],
        'allow_multiple_active' => (bool) env('SAAS_MEMBERSHIP_ALLOW_MULTIPLE_ACTIVE', true),
    ],

    'invitation' => [
        'expiry_hours' => (int) env('SAAS_INVITATION_EXPIRY_HOURS', 168),
        'max_pending' => (int) env('SAAS_INVITATION_MAX_PENDING', 50),
        'allow_resend' => (bool) env('SAAS_INVITATION_ALLOW_RESEND', true),
        'statuses' => ['pending', 'accepted', 'expired', 'revoked'],
    ],

    'billing' => [
        'driver' => env('SAAS_BILLING_DRIVER', 'internal'),
        'currency' => env('SAAS_BILLING_CURRENCY', 'USD'),
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    'subscription' => [
        'grace_days' => (int) env('SAAS_SUBSCRIPTION_GRACE_DAYS', 3),
        'proration' => (bool) env('SAAS_SUBSCRIPTION_PRORATION', true),
        'auto_renew' => (bool) env('SAAS_SUBSCRIPTION_AUTO_RENEW', true),
        'statuses' => ['active', 'trialing', 'past_due', 'cancelled', 'expired', 'paused'],
    ],

    'feature' => [
        'unlimited_value' => env('SAAS_FEATURE_UNLIMITED_VALUE', 'unlimited'),
        'resolver' => env('SAAS_FEATURE_RESOLVER', 'SaasFoundation\\Features\\Resolver'),
    ],

    'usage' => [
        'enabled' => (bool) env('SAAS_USAGE_ENABLED', true),
        'period_format' => env('SAAS_USAGE_PERIOD_FORMAT', 'Y-m'),
        'cache_ttl' => (int) env('SAAS_USAGE_CACHE_TTL', 300),
        'exceeded_policy' => env('SAAS_USAGE_EXCEEDED_POLICY', 'block'),
    ],

    'cache' => [
        'store' => env('SAAS_CACHE_STORE'),
        'tenant_prefix' => env('SAAS_CACHE_TENANT_PREFIX', 'tenant'),
        'lock_seconds' => (int) env('SAAS_CACHE_LOCK_SECONDS', 10),
    ],

    'filesystem' => [
        'disk' => env('SAAS_FILESYSTEM_DISK', 'local'),
        'tenant_root' => env('SAAS_FILESYSTEM_TENANT_ROOT', 'tenants'),
        'visibility' => env('SAAS_FILESYSTEM_VISIBILITY', 'private'),
    ],

    'queue' => [
        'connection' => env('SAAS_QUEUE_CONNECTION'),
        'tenant_prefix' => env('SAAS_QUEUE_TENANT_PREFIX', 'tenant'),
        'tries' => (int) env('SAAS_QUEUE_TRIES', 3),
        'timeout' => (int) env('SAAS_QUEUE_TIMEOUT', 60),
    ],

    'api' => [
        'prefix' => env('SAAS_API_PREFIX', 'api'),
        'version' => env('SAAS_API_VERSION', 'v1'),
        'rate_limit' => [
            'attempts' => (int) env('SAAS_API_RATE_LIMIT_ATTEMPTS', 60),
            'decay_minutes' => (int) env('SAAS_API_RATE_LIMIT_DECAY', 1),
        ],
        'token_lifetime_days' => (int) env('SAAS_API_TOKEN_LIFETIME_DAYS', 365),
    ],

    'auditing' => [
        'enabled' => (bool) env('SAAS_AUDITING_ENABLED', true),
        'driver' => env('SAAS_AUDITING_DRIVER', 'database'),
        'events' => ['created', 'updated', 'deleted', 'restored'],
    ],

    'logging' => [
        'channel' => env('SAAS_LOGGING_CHANNEL', 'tenant'),
        'include_tenant_context' => (bool) env('SAAS_LOGGING_TENANT_CONTEXT', true),
    ],

    'security' => [
        'headers' => [
            'x-frame-options' => env('SAAS_SECURITY_X_FRAME_OPTIONS', 'DENY'),
            'x-content-type-options' => 'nosniff',
            'referrer-policy' => env('SAAS_SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        ],
        'force_https' => (bool) env('SAAS_SECURITY_FORCE_HTTPS', true),
        'middleware' => [
            'guest' => ['web'],
            'authenticated' => ['web', 'auth'],
            'tenant' => ['web', 'auth', 'tenant'],
        ],
    ],

    'two_factor' => [
        'enabled' => (bool) env('SAAS_2FA_ENABLED', true),
        'digits' => (int) env('SAAS_2FA_DIGITS', 6),
        'window' => (int) env('SAAS_2FA_WINDOW', 1),
        'enforced_for' => ['super_admin'],
        'recovery_codes' => (int) env('SAAS_2FA_RECOVERY_CODES', 10),
    ],

    'password_policy' => [
        'min_length' => (int) env('SAAS_PASSWORD_MIN_LENGTH', 12),
        'requires_uppercase' => (bool) env('SAAS_PASSWORD_UPPERCASE', true),
        'requires_lowercase' => (bool) env('SAAS_PASSWORD_LOWERCASE', true),
        'requires_numeric' => (bool) env('SAAS_PASSWORD_NUMERIC', true),
        'requires_symbol' => (bool) env('SAAS_PASSWORD_SYMBOL', true),
        'max_age_days' => (int) env('SAAS_PASSWORD_MAX_AGE_DAYS', 90),
    ],

];
