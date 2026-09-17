<?php

namespace SaasFoundation\Listeners;

use Illuminate\Database\Eloquent\Model;
use SaasFoundation\Services\Auditing\AuditService;

class RecordSecurityEvent
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function handle(object $event): void
    {
        $eventName = str(class_basename($event))->snake()->toString();

        $model = $this->extractModel($event);

        $this->auditService->log(
            action: 'security.'.$eventName,
            model: $model,
            new: $this->extractPayload($event),
        );
    }

    protected function extractModel(object $event): ?Model
    {
        $reflection = new \ReflectionClass($event);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $value = $property->getValue($event);
            } else {
                $property->setAccessible(true);
                $value = $property->getValue($event);
            }

            if ($value instanceof Model) {
                return $value;
            }
        }

        return null;
    }

    protected function extractPayload(object $event): array
    {
        $payload = [];

        $reflection = new \ReflectionClass($event);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $value = $property->getValue($event);
            } else {
                $property->setAccessible(true);
                $value = $property->getValue($event);
            }

            if (is_scalar($value) || $value === null) {
                $payload[$property->getName()] = $value;
            }
        }

        return $payload;
    }
}
