<?php

namespace SaasFoundation\Services\Tenancy;

class FeatureChecker
{
    public function __construct(protected string $featureSlug) {}

    public function enabled(): bool
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($tenant === null) {
            return false;
        }

        return $tenant->hasFeature($this->featureSlug);
    }

    public function disabled(): bool
    {
        return ! $this->enabled();
    }
}
