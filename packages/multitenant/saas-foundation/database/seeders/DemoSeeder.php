<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Plan;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\SubscriptionItem;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(FeatureSeeder::class);
        $this->call(PlanSeeder::class);

        $alice = $this->user('alice@example.com', 'Alice Johnson');
        $bob = $this->user('bob@example.com', 'Bob Carter');
        $charlie = $this->user('charlie@example.com', 'Charlie Lee');

        $acme = $this->tenant('Acme Corp', 'acme-corp');
        $globex = $this->tenant('Globex', 'globex');

        $this->membership($alice, $acme, 'owner');
        $this->membership($charlie, $acme, 'viewer');

        $this->membership($bob, $globex, 'owner');
        $this->membership($alice, $globex, 'viewer');

        $acme->projects()->create([
            'name' => 'Cloud Dashboard',
            'description' => 'Customer-facing usage dashboard.',
            'status' => 'active',
        ]);

        $acme->projects()->create([
            'name' => 'Mobile App',
            'description' => 'Internal iOS and Android builds.',
            'status' => 'active',
        ]);

        $globex->projects()->create([
            'name' => 'Ecommerce Storefront',
            'description' => 'Public storefront and checkout flow.',
            'status' => 'active',
        ]);

        $this->subscribe($acme, 'professional');
        $this->subscribe($globex, 'starter');
    }

    protected function user(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
            ]
        );
    }

    protected function tenant(string $name, string $slug): Tenant
    {
        return Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'status' => Tenant::STATUS_ACTIVE,
                'timezone' => 'America/New_York',
                'locale' => 'en',
                'currency' => 'USD',
            ]
        );
    }

    protected function membership(User $user, Tenant $tenant, string $roleSlug): Membership
    {
        $membership = Membership::firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $role = Role::whereNull('tenant_id')->where('slug', $roleSlug)->first();

        if ($role !== null) {
            $membership->roles()->syncWithoutDetaching($role->id);
        }

        return $membership;
    }

    protected function subscribe(Tenant $tenant, string $planSlug): void
    {
        $plan = Plan::where('slug', $planSlug)->first();

        if ($plan === null) {
            return;
        }

        $subscription = Subscription::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => now()->subDays(20),
            ]
        );

        $this->items($subscription);
    }

    protected function items(Subscription $subscription): void
    {
        foreach ($subscription->plan?->features ?? [] as $feature) {
            $quantity = $feature->slug === 'users' ? 2 : 1;

            SubscriptionItem::firstOrCreate(
                ['subscription_id' => $subscription->id, 'feature_id' => $feature->id],
                ['quantity' => $quantity]
            );
        }
    }
}
