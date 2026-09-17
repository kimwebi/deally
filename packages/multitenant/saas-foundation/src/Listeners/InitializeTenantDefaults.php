<?php

namespace SaasFoundation\Listeners;

use SaasFoundation\Events\TenantCreated;
use SaasFoundation\Models\TenantSetting;

class InitializeTenantDefaults
{
    public function handle(TenantCreated $event): void
    {
        $tenant = $event->tenant;

        $defaults = [
            'app.name' => ['value' => $tenant->name, 'type' => 'string'],
            'app.timezone' => ['value' => $tenant->timezone ?? 'UTC', 'type' => 'string'],
            'app.locale' => ['value' => $tenant->locale ?? 'en', 'type' => 'string'],
            'app.currency' => ['value' => $tenant->currency ?? 'USD', 'type' => 'string'],
            'notifications.email_enabled' => ['value' => 'true', 'type' => 'boolean'],
            'features.api_access' => ['value' => 'true', 'type' => 'boolean'],
        ];

        foreach ($defaults as $key => $config) {
            TenantSetting::create([
                'tenant_id' => $tenant->id,
                'key' => $key,
                'value' => $config['value'],
                'type' => $config['type'],
            ]);
        }
    }
}
