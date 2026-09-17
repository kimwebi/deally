<?php

namespace SaasFoundation\Listeners;

use SaasFoundation\Events\TenantDeleted;
use SaasFoundation\Models\Activity;
use SaasFoundation\Models\AuditLog;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Subscription;
use SaasFoundation\Models\UsageRecord;

class CleanupTenantData
{
    public function handle(TenantDeleted $event): void
    {
        $tenantId = $event->tenant->id;

        Invitation::where('tenant_id', $tenantId)->delete();
        Subscription::where('tenant_id', $tenantId)->delete();
        UsageRecord::where('tenant_id', $tenantId)->delete();
        AuditLog::where('tenant_id', $tenantId)->delete();
        Activity::where('tenant_id', $tenantId)->delete();
    }
}
