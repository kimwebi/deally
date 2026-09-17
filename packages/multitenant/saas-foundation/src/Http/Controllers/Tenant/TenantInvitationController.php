<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Http\Requests\StoreInvitationRequest;
use SaasFoundation\Models\Invitation;
use SaasFoundation\Models\Role;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Services\Authentication\InvitationService;

class TenantInvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    public function index(Tenant $tenant)
    {
        $invitations = Invitation::where('tenant_id', $tenant->id)
            ->with(['role', 'inviter'])
            ->latest()
            ->paginate(15);

        return view('tenant.invitations.index', compact('tenant', 'invitations'));
    }

    public function create(Tenant $tenant)
    {
        $roles = Role::where('tenant_id', $tenant->id)->get();

        return view('tenant.invitations.create', compact('tenant', 'roles'));
    }

    public function store(StoreInvitationRequest $request, Tenant $tenant)
    {
        $role = $request->filled('role_id')
            ? Role::findOrFail($request->integer('role_id'))
            : null;

        $invitation = $this->invitationService->invite(
            $request->string('email'),
            $tenant,
            $role,
            auth()->user()
        );

        return redirect()
            ->route('tenant.invitations.index', $tenant)
            ->with('success', "Invitation sent to {$invitation->email}.");
    }

    public function show(Tenant $tenant, Invitation $invitation)
    {
        abort_if($invitation->tenant_id !== $tenant->id, 404);

        $invitation->load(['role', 'inviter']);

        return view('tenant.invitations.show', compact('tenant', 'invitation'));
    }

    public function resend(Tenant $tenant, Invitation $invitation)
    {
        abort_if($invitation->tenant_id !== $tenant->id, 404);

        $this->invitationService->resend($invitation);

        return back()->with('success', 'Invitation resent.');
    }

    public function revoke(Tenant $tenant, Invitation $invitation)
    {
        abort_if($invitation->tenant_id !== $tenant->id, 404);

        $this->invitationService->revoke($invitation);

        return back()->with('success', 'Invitation revoked.');
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->invitationService->getByToken($token);

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->route('login')
                ->with('error', 'This invitation is invalid or has expired.');
        }

        $user = $request->user();

        if (! $user) {
            session(['invitation_token' => $invitation->token]);

            return redirect()->route('login')->with('warning', 'Please sign in to accept this invitation.');
        }

        $this->invitationService->accept($invitation, $user);

        session(['tenant_id' => $invitation->tenant_id]);

        return redirect()
            ->route('tenant.dashboard', $invitation->tenant)
            ->with('success', 'Invitation accepted. Welcome to the tenant!');
    }
}
