<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Membership;
use SaasFoundation\Models\User;
use SaasFoundation\Services\Identity\UserService;

class CentralUserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(Request $request)
    {
        $users = User::query()
            ->withCount(['memberships', 'auditLogs'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('super_admin'), function ($query): void {
                $query->where('is_super_admin', true);
            })
            ->when($request->boolean('inactive'), function ($query): void {
                $query->where('is_active', false);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('central.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->loadCount(['memberships', 'auditLogs', 'activities']);

        $memberships = Membership::where('user_id', $user->id)
            ->with(['tenant', 'roles'])
            ->latest()
            ->get();

        $recentActivity = $user->auditLogs()
            ->with('tenant')
            ->latest()
            ->limit(10)
            ->get();

        return view('central.users.show', compact('user', 'memberships', 'recentActivity'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'is_active' => ['boolean'],
        ]);

        $this->userService->update($user, $validated);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->memberships()->detach();
        $user->apiTokens()->delete();
        $user->delete();

        return redirect()
            ->route('central.users.index')
            ->with('success', "User '{$user->name}' deleted.");
    }
}
