<?php

namespace SaasFoundation\Traits;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use SaasFoundation\Models\TenantSetting;

trait HasTenancy
{
    public function cacheKey(string $key = ''): string
    {
        $prefix = "tenant:{$this->id}";

        return $key !== '' ? "{$prefix}:{$key}" : $prefix;
    }

    public function cache(string $key, mixed $value, ?int $ttl = null): mixed
    {
        $cacheKey = $this->cacheKey($key);

        if ($ttl !== null) {
            return Cache::remember($cacheKey, $ttl, fn () => $value);
        }

        Cache::put($cacheKey, $value);

        return $value;
    }

    public function cacheGet(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->cacheKey($key), $default);
    }

    public function cacheForget(string $key): bool
    {
        return Cache::forget($this->cacheKey($key));
    }

    public function storagePath(string $path = ''): string
    {
        return storage_path("tenants/{$this->id}/{$path}");
    }

    public function disk(?string $disk = null): FilesystemAdapter
    {
        $disk = $disk ?? config('filesystems.default');

        $adapter = Storage::disk($disk);

        $adapter->makeDirectory($this->id);

        return $adapter;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $setting = TenantSetting::where('tenant_id', $this->id)
            ->where('key', $key)
            ->first();

        if ($setting === null) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public function setSetting(string $key, mixed $value): void
    {
        TenantSetting::updateOrCreate(
            [
                'tenant_id' => $this->id,
                'key' => $key,
            ],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => is_array($value) ? 'json' : gettype($value),
            ]
        );
    }
}
