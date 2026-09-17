<?php

namespace SaasFoundation\Services\Tenancy;

use SaasFoundation\Models\TenantSetting;

class TenantSettingsManager
{
    public function get(string $key, mixed $default = null): mixed
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return $default;
        }

        $setting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->first();

        if ($setting === null) {
            return $default;
        }

        return $this->castValue($setting->value, $setting->type);
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return;
        }

        TenantSetting::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'key' => $key,
            ],
            [
                'value' => (string) $value,
                'type' => $type,
            ]
        );
    }

    public function has(string $key): bool
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return false;
        }

        return TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->exists();
    }

    public function forget(string $key): void
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return;
        }

        TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', $key)
            ->delete();
    }

    public function all(): array
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return [];
        }

        $settings = TenantSetting::where('tenant_id', $tenant->id)->get();

        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = $this->castValue($setting->value, $setting->type);
        }

        return $result;
    }

    protected function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            'array' => json_decode($value, true),
            default => $value,
        };
    }
}
