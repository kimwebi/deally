<?php

use SaasFoundation\Services\Billing\Providers\NullBillingProvider;

return [
    'driver' => env('BILLING_DRIVER', 'null'),

    'providers' => [
        'null' => NullBillingProvider::class,
    ],

    'currency' => env('BILLING_CURRENCY', 'USD'),

    'trials' => [
        'enabled' => env('BILLING_TRIALS_ENABLED', true),
        'default_days' => env('BILLING_TRIALS_DEFAULT_DAYS', 14),
    ],

    'usage' => [
        'period' => 'monthly',
        'prorate_on_upgrade' => true,
    ],
];
