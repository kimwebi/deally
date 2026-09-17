<?php

namespace SaasFoundation\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class TenantContext
{
    protected ?Tenant $tenant = null;

    protected ?User $user = null;

    protected bool $initialized = false;

    protected array $originalConfig = [];

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->initialized && $this->tenant !== null;
    }

    public function initialize(Tenant $tenant, ?User $user = null): void
    {
        $this->storeOriginalConfig();

        $this->tenant = $tenant;
        $this->user = $user;
        $this->initialized = true;

        $this->applyTimezone();

        Config::set('tenancy.current_tenant_id', $tenant->id);

        if ($user !== null) {
            Request::setUserResolver(fn () => $user);
        }
    }

    public function end(): void
    {
        $this->restoreTimezone();
        $this->restoreOriginalConfig();

        $this->tenant = null;
        $this->user = null;
        $this->initialized = false;
    }

    public function switch(Tenant $tenant): void
    {
        $this->end();
        $this->initialize($tenant);
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousUser = $this->user;
        $wasInitialized = $this->initialized;

        try {
            $this->initialize($tenant);

            return $callback();
        } finally {
            if ($wasInitialized && $previousTenant !== null) {
                $this->end();
                $this->initialize($previousTenant, $previousUser);
            } else {
                $this->end();
            }
        }
    }

    public function runForEachTenant(Closure $callback): void
    {
        $tenants = Tenant::query()->active()->get();

        foreach ($tenants as $tenant) {
            $this->run($tenant, $callback);
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function feature(string $featureSlug): FeatureChecker
    {
        return new FeatureChecker($featureSlug);
    }

    public function limit(string $featureSlug): LimitChecker
    {
        return new LimitChecker($featureSlug);
    }

    public function settings(): TenantSettingsManager
    {
        return new TenantSettingsManager;
    }

    public function subscription(): ?SubscriptionManager
    {
        if ($this->tenant === null) {
            return null;
        }

        return new SubscriptionManager;
    }

    public function cachePrefix(): string
    {
        $tenantId = $this->id();

        return $tenantId ? "tenant:{$tenantId}:" : 'tenant:global:';
    }

    public function storagePath(string $path = ''): string
    {
        $tenantId = $this->id();

        if ($tenantId === null) {
            return storage_path($path);
        }

        return storage_path("tenants/{$tenantId}/{$path}");
    }

    protected function applyTimezone(): void
    {
        if ($this->tenant !== null && ! empty($this->tenant->timezone)) {
            Config::set('app.timezone', $this->tenant->timezone);
            date_default_timezone_set($this->tenant->timezone);
        }
    }

    protected function restoreTimezone(): void
    {
        if (isset($this->originalConfig['timezone'])) {
            Config::set('app.timezone', $this->originalConfig['timezone']);
            date_default_timezone_set($this->originalConfig['timezone']);
        }
    }

    protected function storeOriginalConfig(): void
    {
        if (empty($this->originalConfig)) {
            $this->originalConfig = [
                'timezone' => Config::get('app.timezone'),
                'locale' => Config::get('app.locale'),
            ];
        }
    }

    protected function restoreOriginalConfig(): void
    {
        if (! empty($this->originalConfig)) {
            Config::set('app.timezone', $this->originalConfig['timezone']);
            Config::set('app.locale', $this->originalConfig['locale']);
            $this->originalConfig = [];
        }
    }
}
