<?php

namespace SaasFoundation\Services\Auditing;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Tenancy\TenantContext;

class AuditService
{
    public function log(string $action, ?Model $model = null, ?array $old = null, ?array $new = null, ?User $user = null, ?Request $request = null): AuditLog
    {
        $metadata = $this->getRequestMetadata($request);
        $currentUser = $user ?? $this->getCurrentUser();
        $tenantId = $this->resolveTenantId($model);

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $currentUser?->id,
            'action' => $action,
            'auditable_type' => $model ? $model::class : null,
            'auditable_id' => $model ? $model->getKey() : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $metadata['ip_address'],
            'user_agent' => $metadata['user_agent'],
            'request_id' => $metadata['request_id'],
        ]);
    }

    public function activity(string $event, ?Model $subject = null, ?string $description = null, ?array $properties = null, ?User $user = null): Activity
    {
        $currentUser = $user ?? $this->getCurrentUser();

        $tenantId = $this->resolveTenantId($subject);

        if ($tenantId === null && $currentUser !== null) {
            $tenantId = $currentUser->metadata['current_tenant_id'] ?? null;
        }

        return Activity::create([
            'tenant_id' => $tenantId,
            'user_id' => $currentUser?->id,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject ? $subject->getKey() : null,
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
        ]);
    }

    public function getAuditLogs(?string $tenantId = null, int $limit = 50): Collection
    {
        $query = AuditLog::query()->with('user', 'tenant');

        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function getActivities(?string $tenantId = null, int $limit = 50): Collection
    {
        $query = Activity::query()->with('user', 'tenant');

        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        return $query->latest()->limit($limit)->get();
    }

    public function cleanup(int $daysToKeep = 365): int
    {
        $cutoff = now()->subDays($daysToKeep);

        $auditCleanup = AuditLog::where('created_at', '<', $cutoff)->delete();
        $activityCleanup = Activity::where('created_at', '<', $cutoff)->delete();

        return $auditCleanup + $activityCleanup;
    }

    protected function getCurrentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected function getRequestMetadata(?Request $request = null): array
    {
        if ($request === null) {
            $request = request();
        }

        return [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-ID') ?? (string) Str::uuid(),
        ];
    }

    protected function resolveTenantId(?Model $model = null): ?string
    {
        if ($model === null) {
            $tenant = app(TenantContext::class)->tenant();

            return $tenant?->id;
        }

        if (in_array('tenant_id', $model->getFillable(), true) && ! empty($model->tenant_id)) {
            return $model->tenant_id;
        }

        if ($model instanceof Tenant) {
            return $model->id;
        }

        return app(TenantContext::class)->tenant()?->id;
    }
}
