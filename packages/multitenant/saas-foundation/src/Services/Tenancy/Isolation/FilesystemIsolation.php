<?php

namespace SaasFoundation\Services\Tenancy\Isolation;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use SaasFoundation\Services\Tenancy\TenantContext;

class FilesystemIsolation
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function disk(?string $disk = null): FilesystemAdapter
    {
        $disk = $disk ?? config('filesystems.default');
        $adapter = Storage::disk($disk);
        $tenantId = $this->context->id();

        if ($tenantId !== null) {
            $adapter->makeDirectory("tenants/{$tenantId}");
        }

        return $adapter;
    }

    public function path(string $path = ''): string
    {
        return $this->context->storagePath($path);
    }

    public function put(string $path, $contents, ?string $disk = null): bool|string
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->put($tenantPath, $contents);
    }

    public function get(string $path, ?string $disk = null): string|false
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->get($tenantPath);
    }

    public function delete(string $path, ?string $disk = null): bool
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->delete($tenantPath);
    }

    public function exists(string $path, ?string $disk = null): bool
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->exists($tenantPath);
    }

    public function url(string $path, ?string $disk = null): string
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->url($tenantPath);
    }

    public function makeDirectory(string $path, ?string $disk = null): bool
    {
        $tenantPath = $this->tenantPath($path);

        return $this->disk($disk)->makeDirectory($tenantPath);
    }

    public function files(?string $disk = null, string $directory = ''): array
    {
        $tenantDirectory = $this->tenantPath($directory);

        return $this->disk($disk)->files($tenantDirectory);
    }

    protected function tenantPath(string $path): string
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return $path;
        }

        return "tenants/{$tenantId}/{$path}";
    }
}
