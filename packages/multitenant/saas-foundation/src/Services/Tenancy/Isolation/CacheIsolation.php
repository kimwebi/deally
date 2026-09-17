<?php

namespace SaasFoundation\Services\Tenancy\Isolation;

use Illuminate\Support\Facades\Cache;
use SaasFoundation\Services\Tenancy\TenantContext;

class CacheIsolation
{
    public function __construct(
        protected TenantContext $context
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefix($key), $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        if ($ttl !== null) {
            return Cache::put($this->prefix($key), $value, $ttl);
        }

        return Cache::put($this->prefix($key), $value);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix($key));
    }

    public function has(string $key): bool
    {
        return Cache::has($this->prefix($key));
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return Cache::remember($this->prefix($key), $ttl, $callback);
    }

    public function flush(): bool
    {
        $prefix = $this->context->cachePrefix();

        $store = Cache::getStore();

        if (method_exists($store, 'prefix')) {
            $store->prefix($prefix)->flush();

            return true;
        }

        return false;
    }

    protected function prefix(string $key): string
    {
        return $this->context->cachePrefix().$key;
    }
}
