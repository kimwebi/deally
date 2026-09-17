<?php

namespace SaasFoundation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use SaasFoundation\Events\InvitationCreated;
use SaasFoundation\Events\MembershipCreated;
use SaasFoundation\Events\SubscriptionChanged;
use SaasFoundation\Events\TenantCreated;
use SaasFoundation\Events\TenantDeleted;
use SaasFoundation\Events\TenantProvisioned;
use SaasFoundation\Listeners\CleanupTenantData;
use SaasFoundation\Listeners\InitializeTenantDefaults;
use SaasFoundation\Listeners\NotifySubscriptionChanged;
use SaasFoundation\Listeners\RecordAuditLog;
use SaasFoundation\Listeners\RecordSecurityEvent;
use SaasFoundation\Listeners\SendInvitationEmail;
use SaasFoundation\Listeners\SendMembershipNotification;
use SaasFoundation\Listeners\SendTenantWelcomeEmail;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TenantCreated::class => [
            SendTenantWelcomeEmail::class,
            InitializeTenantDefaults::class,
        ],
        TenantProvisioned::class => [
            RecordAuditLog::class,
        ],
        TenantDeleted::class => [
            CleanupTenantData::class,
        ],
        InvitationCreated::class => [
            SendInvitationEmail::class,
            RecordAuditLog::class,
        ],
        MembershipCreated::class => [
            SendMembershipNotification::class,
        ],
        SubscriptionChanged::class => [
            NotifySubscriptionChanged::class,
            RecordAuditLog::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();

        Event::listen(
            'Illuminate\Auth\Events\Login',
            [RecordSecurityEvent::class, 'handle']
        );

        Event::listen(
            'Illuminate\Auth\Events\Logout',
            [RecordSecurityEvent::class, 'handle']
        );

        Event::listen(
            'Illuminate\Auth\Events\Failed',
            [RecordSecurityEvent::class, 'handle']
        );

        Event::listen(
            'Illuminate\Auth\Events\Lockout',
            [RecordSecurityEvent::class, 'handle']
        );
    }
}
