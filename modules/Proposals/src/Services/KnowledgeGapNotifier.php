<?php

namespace Deally\Proposals\Services;

use Deally\Core\Models\User;
use Deally\Core\Services\Notifier;
use Deally\Proposals\Models\KnowledgeGap;
use Illuminate\Notifications\DatabaseNotification;
use SaasFoundation\Models\Membership;
use SaasFoundation\Services\Tenancy\TenantContext;

/**
 * Surfaces unresolved knowledge gaps to the people who answer them.
 *
 * Every newly-created pending gap lands in the Solutions Lead queue, so the
 * queue members get a notification while it stays unresolved and the
 * notification leaves their inbox the moment the gap leaves the queue
 * (approved or rejected) — never while an edit keeps it pending.
 */
class KnowledgeGapNotifier
{
    /**
     * Roles that see the Solutions queue.
     *
     * @var list<string>
     */
    public const QUEUE_ROLES = ['solutions-lead', 'owner', 'admin'];

    public function __construct(
        protected Notifier $notifier,
        protected TenantContext $tenantContext,
    ) {}

    public function notifyUnresolved(KnowledgeGap $gap): void
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return;
        }

        /* Notification categories are decided at the company level; if the
           account turned the Expert Answers queue off, nobody is pinged. */
        if (! ($tenant->settings['notifications']['expert_gaps'] ?? true)) {
            return;
        }

        $users = User::query()
            ->whereIn('id', $this->queueUserIds($tenant->id))
            ->get();

        foreach ($users as $user) {
            $this->notifier->notify(
                $user,
                'Unanswered question',
                $gap->text,
                'gap',
                route('deally.solutions.index'),
                [
                    'gap_id' => $gap->getKey(),
                    'tenant_id' => $tenant->id,
                    'unresolved' => true,
                    'type' => 'question',
                ]
            );
        }
    }

    public function clearResolved(KnowledgeGap $gap): void
    {
        $tenant = $this->tenantContext->tenant();

        if ($tenant === null) {
            return;
        }

        $userIds = $this->queueUserIds($tenant->id);

        if ($userIds === []) {
            return;
        }

        // Notifications live on the central database, gap ids are per-tenant.
        // The `data` column is a text/JSON blob, so match on `gap_id` and
        // `tenant_id` in PHP rather than with database JSON paths.
        DatabaseNotification::query()
            ->where('type', 'gap')
            ->whereIn('notifiable_id', $userIds)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => ($notification->data['gap_id'] ?? null) === $gap->getKey()
                && ($notification->data['tenant_id'] ?? null) === $tenant->id)
            ->each->delete();
    }

    /**
     * Active members of the tenant that can act on the gap queue.
     *
     * @return array<int, int>
     */
    protected function queueUserIds(string $tenantId): array
    {
        return Membership::query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', self::QUEUE_ROLES))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
