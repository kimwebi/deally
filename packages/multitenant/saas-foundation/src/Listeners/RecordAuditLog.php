<?php

namespace SaasFoundation\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\InteractsWithQueue;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Auditing\AuditService;

class RecordAuditLog implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function handle(object $event): void
    {
        if (! config('saas.auditing.enabled', true)) {
            return;
        }

        $models = [];

        $reflection = new \ReflectionClass($event);

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($event);

            if ($value instanceof Model) {
                $models[] = $value;
            }
        }

        $model = $models[0] ?? null;

        $tenant = null;

        foreach ($models as $candidate) {
            if ($candidate instanceof Tenant) {
                $tenant = $candidate;
                break;
            }

            if (in_array('tenant_id', $candidate->getFillable(), true) && ! empty($candidate->tenant_id)) {
                $tenant = $candidate;
                break;
            }
        }

        $this->auditService->log(
            action: str(class_basename($event))->snake()->toString(),
            model: $model,
            new: $model?->toArray(),
        );
    }
}
