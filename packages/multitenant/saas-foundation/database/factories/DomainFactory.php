<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use SaasFoundation\Models\Domain;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    public function definition(): array
    {
        $slug = Str::slug(fake()->domainWord());

        return [
            'tenant_id' => Tenant::factory(),
            'domain' => $slug.'.'.fake()->domainName(),
            'type' => Domain::TYPE_SUBDOMAIN,
            'is_primary' => false,
            'is_verified' => true,
            'is_active' => true,
            'verification_token' => Str::random(64),
            'metadata' => null,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function custom(): static
    {
        return $this->state(fn () => ['type' => Domain::TYPE_CUSTOM]);
    }
}
