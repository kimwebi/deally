<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SaasFoundation\Models\Feature;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            [
                'slug' => 'users',
                'name' => 'Users',
                'description' => 'Number of active members allowed in the tenant.',
            ],
            [
                'slug' => 'projects',
                'name' => 'Projects',
                'description' => 'Number of projects the tenant can manage.',
            ],
            [
                'slug' => 'storage',
                'name' => 'Storage',
                'description' => 'Amount of storage allocated to the tenant.',
            ],
            [
                'slug' => 'api_access',
                'name' => 'API Access',
                'description' => 'Whether the tenant can use the public API.',
            ],
            [
                'slug' => 'advanced_reports',
                'name' => 'Advanced Reports',
                'description' => 'Enables advanced reporting and exports.',
            ],
            [
                'slug' => 'custom_domains',
                'name' => 'Custom Domains',
                'description' => 'Number of custom domains the tenant can attach.',
            ],
            [
                'slug' => 'priority_support',
                'name' => 'Priority Support',
                'description' => 'Grants access to priority support channels.',
            ],
        ];

        foreach ($features as $feature) {
            Feature::updateOrCreate(
                ['slug' => $feature['slug']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                ]
            );
        }
    }
}
