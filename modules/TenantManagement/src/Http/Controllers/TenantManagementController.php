<?php

namespace Deally\TenantManagement\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\TenantManagement\Contracts\TenantInstanceManager;
use Deally\TenantManagement\Http\Requests\StoreTenantRequest;
use Deally\TenantManagement\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TenantManagementController extends Controller
{
    public function __construct(private readonly TenantInstanceManager $tenantInstanceManager) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $tenantQuery = Tenant::query();

        if (! request()->user()->is_superadmin) {
            $tenantQuery->where('user_id', request()->user()->id);
        }

        return response()->json($tenantQuery->get());
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        [$dbName, $slug] = $this->uniqueIdentifiers(Str::slug($request->name));

        $tenant = Tenant::create([
            'name' => $request->name,
            'slug' => $slug,
            'db_name' => $dbName,
            'user_id' => $request->user()->id,
        ]);

        try {
            $this->tenantInstanceManager->createInstance($tenant->name, $tenant->db_name);
        } catch (\Throwable $e) {
            $tenant->delete();

            throw $e;
        }

        return response()->json($tenant, 201);
    }

    public function clone(Tenant $tenant): JsonResponse
    {
        $this->authorize('clone', $tenant);

        $cloneDbName = $this->uniqueDbName($tenant->db_name.'_backup');
        $this->tenantInstanceManager->cloneInstance($tenant->db_name, $cloneDbName);

        return response()->json(['db_name' => $cloneDbName]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function uniqueIdentifiers(string $base): array
    {
        $slug = $base;
        $dbName = $base;

        while (Tenant::where('slug', $slug)->orWhere('db_name', $dbName)->exists()) {
            $suffix = Str::lower(Str::random(4));
            $slug = $base.'_'.$suffix;
            $dbName = $base.'_'.$suffix;
        }

        return [$dbName, $slug];
    }

    private function uniqueDbName(string $base): string
    {
        $dbName = $base;

        while (Tenant::where('db_name', $dbName)->exists()) {
            $dbName = $base.'_'.Str::lower(Str::random(4));
        }

        return $dbName;
    }
}
