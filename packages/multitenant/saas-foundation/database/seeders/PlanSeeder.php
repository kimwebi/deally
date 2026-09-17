<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SaasFoundation\Models\Feature;
use SaasFoundation\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(FeatureSeeder::class);

        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'For exploring a single project with one user.',
                'price' => 0,
                'features' => [
                    'users' => '1',
                    'projects' => '1',
                    'storage' => '100',
                    'api_access' => '0',
                    'advanced_reports' => '0',
                    'custom_domains' => '0',
                    'priority_support' => '0',
                ],
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For small teams that need the API and a custom domain.',
                'price' => 29,
                'features' => [
                    'users' => '5',
                    'projects' => '10',
                    'storage' => '1024',
                    'api_access' => '1',
                    'advanced_reports' => '0',
                    'custom_domains' => '1',
                    'priority_support' => '0',
                ],
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'description' => 'For growing teams with advanced reporting.',
                'price' => 99,
                'features' => [
                    'users' => '50',
                    'projects' => '50',
                    'storage' => '10240',
                    'api_access' => '1',
                    'advanced_reports' => '1',
                    'custom_domains' => '3',
                    'priority_support' => '0',
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'For organizations with unlimited everything and priority support.',
                'price' => 299,
                'features' => [
                    'users' => '-1',
                    'projects' => '-1',
                    'storage' => '-1',
                    'api_access' => '1',
                    'advanced_reports' => '1',
                    'custom_domains' => '-1',
                    'priority_support' => '1',
                ],
            ],
        ];

        foreach ($plans as $index => $plan) {
            $model = Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price' => $plan['price'],
                    'currency' => 'USD',
                    'billing_interval' => 'monthly',
                    'trial_days' => $index === 0 ? 0 : 14,
                    'is_active' => true,
                    'is_default' => $index === 0,
                    'sort_order' => $index,
                ]
            );

            $pivot = [];

            foreach ($plan['features'] as $slug => $value) {
                $feature = Feature::where('slug', $slug)->first();

                if ($feature !== null) {
                    $pivot[$feature->id] = ['value' => $value];
                }
            }

            $model->features()->sync($pivot);
        }
    }
}
