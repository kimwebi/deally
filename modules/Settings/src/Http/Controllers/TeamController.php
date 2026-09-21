<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Core\Models\Team;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use SaasFoundation\Models\Membership;

class TeamController extends Controller
{
    public function index(): View
    {
        $this->authorizeDeally('deally.team.view');

        $tenantId = $this->deallyMembership()?->tenant_id;

        abort_if($tenantId === null, 403);

        $teams = Team::query()->forTenant($tenantId)->with('members')->latest()->get();

        $members = Membership::query()
            ->forTenant($tenantId)
            ->active()
            ->with('user')
            ->get()
            ->mapWithKeys(fn (Membership $membership): array => [$membership->user_id => $membership->user->name]);

        return view('settings::pages.teams.index', ['teams' => $teams, 'members' => $members]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeDeally('deally.team.manage');

        $data = $this->validated($request);
        $tenantId = $this->deallyMembership()?->tenant_id;

        abort_if($tenantId === null, 403);

        $team = Team::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! empty($data['members'])) {
            $team->members()->sync($data['members']);
        }

        return back()->with('toast', "Team '{$team->name}' created.");
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeDeally('deally.team.manage');
        $this->abortIfNotInTenant($team);

        $data = $this->validated($request);

        $team->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (array_key_exists('members', $data)) {
            $team->members()->sync($data['members'] ?? []);
        }

        return back()->with('toast', "Team '{$team->name}' updated.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorizeDeally('deally.team.manage');
        $this->abortIfNotInTenant($team);

        $team->members()->detach();
        $team->delete();

        return back()->with('toast', 'Team deleted.');
    }

    /**
     * @return array{name: string, description: ?string, members?: array<int, int>}
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
    }

    protected function abortIfNotInTenant(Team $team): void
    {
        abort_if($team->tenant_id !== $this->deallyMembership()?->tenant_id, 404);
    }
}
