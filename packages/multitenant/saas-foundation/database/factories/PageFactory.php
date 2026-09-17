<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use SaasFoundation\Models\Page;
use SaasFoundation\Models\Tenant;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(3),
            'content' => fake()->paragraphs(3, true),
            'status' => Page::STATUS_DRAFT,
            'published_at' => null,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Page::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Page::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }
}
