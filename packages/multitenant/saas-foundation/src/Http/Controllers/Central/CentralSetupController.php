<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantDatabaseManager;
use SaasFoundation\Services\Tenancy\TenantProvisioner;

/**
 * Platform operations console. Lists every customer with its provisioning
 * status, provisions tenant databases, and creates a new customer (tenant +
 * owner + provisioning) in one action. Gated by the platform.operator
 * middleware, so it is available to the super-admin and users flagged as
 * platform support.
 */
class CentralSetupController extends Controller
{
    /**
     * The celebratory tenant milestone. Reaching this many customers shows
     * the milestone panel on the setup console and the central dashboard.
     */
    public const MILESTONE_TARGET = 10;

    public function __construct(
        protected TenantProvisioner $provisioner,
        protected TenantDatabaseManager $databaseManager,
    ) {}

    public function index(): View
    {
        $tenants = Tenant::query()->orderBy('created_at')->get();

        $tenants->each(function (Tenant $tenant): void {
            $tenant->provisioned = $this->isProvisioned($tenant);
            $tenant->database = $this->databaseManager->getTenantDatabaseName($tenant);
        });

        $provisionedCount = $tenants->where('provisioned', true)->count();

        return view('central.setup.index', [
            'tenants' => $tenants,
            'provisionedCount' => $provisionedCount,
            'customerCount' => $tenants->count(),
            'pendingCount' => $tenants->count() - $provisionedCount,
            'milestone' => self::MILESTONE_TARGET,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
        ]);

        $slug = Str::slug($data['name']);

        if (Tenant::query()->withTrashed()->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => 'A customer with that name already exists.']);
        }

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        $this->addOwner($tenant, $data['email']);

        try {
            $this->provisionTenant($tenant);
        } catch (\Throwable $e) {
            report($e);

            $tenant->update(['provisioning_status' => 'failed']);

            return back()->with('error', "Customer created but provisioning failed: {$e->getMessage()}. Retry from the console.");
        }

        return back()->with('success', "Customer '{$tenant->name}' created and provisioned.");
    }

    public function provision(Request $request, Tenant $tenant): RedirectResponse
    {
        try {
            $this->provisionTenant($tenant);
        } catch (\Throwable $e) {
            report($e);

            $tenant->update(['provisioning_status' => 'failed']);

            return back()->with('error', "Provisioning failed: {$e->getMessage()}");
        }

        return back()->with('success', "{$tenant->name} provisioned.");
    }

    protected function provisionTenant(Tenant $tenant): void
    {
        $this->provisioner->migrate($tenant);
        $this->provisioner->seed($tenant);

        $tenant->update(['provisioning_status' => 'ready']);

        $tenant->forceFill(['provisioned_at' => now()])->save();
    }

    protected function addOwner(Tenant $tenant, string $email): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => ucfirst(Str::before($email, '@')),
                'password' => Hash::make(Str::password(16)),
                'is_active' => true,
            ]
        );

        Membership::firstOrCreate(
            ['user_id' => $user->id, 'tenant_id' => $tenant->id],
            ['status' => Membership::STATUS_ACTIVE, 'joined_at' => now()]
        );

        $owner = Role::query()->whereNull('tenant_id')->where('slug', 'owner')->first();

        if ($owner !== null) {
            $membership = $tenant->memberships()->where('user_id', $user->id)->first();
            $membership?->roles()->syncWithoutDetaching($owner->id);
        }
    }

    protected function isProvisioned(Tenant $tenant): bool
    {
        $connection = $this->databaseManager->getTenantConnectionName($tenant);

        if (config("database.connections.{$connection}") === null) {
            try {
                $this->databaseManager->createConnection($tenant);
            } catch (\Throwable) {
                return false;
            }
        }

        try {
            return DB::connection($connection)->getSchemaBuilder()->hasTable('migrations');
        } catch (\Throwable) {
            return false;
        }
    }
}
