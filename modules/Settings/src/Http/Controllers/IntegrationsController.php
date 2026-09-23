<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SaasFoundation\Models\Tenant;

class IntegrationsController extends Controller
{
    public function index(): View
    {
        $this->authorizeDeally('deally.integrations.view');

        $tenant = $this->tenant();

        return view('settings::pages.integrations', [
            'catalog' => $this->catalog(),
            'enabled' => $this->enabled($tenant),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeDeally('deally.integrations.manage');

        $tenant = $this->tenant();

        $data = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['string', Rule::in(array_keys($this->catalog()))],
        ]);

        $settings = $tenant->settings ?? [];
        $settings['integrations'] = array_values($data['enabled'] ?? []);
        $tenant->update(['settings' => $settings]);

        return back()->with('toast', 'Integrations updated.');
    }

    protected function tenant(): Tenant
    {
        $tenant = $this->deallyMembership()?->tenant;

        abort_if($tenant === null, 403);

        return $tenant;
    }

    /**
     * @return array<string, array{name: string, description: string, icon: string}>
     */
    protected function catalog(): array
    {
        return [
            'slack' => ['name' => 'Slack', 'description' => 'Push call summaries and coaching flags into your channels.', 'icon' => '💬'],
            'salesforce' => ['name' => 'Salesforce', 'description' => 'Two-way sync of deals, calls and proposals with your CRM.', 'icon' => '☁️'],
            'hubspot' => ['name' => 'HubSpot', 'description' => 'Keep contacts and opportunities aligned with HubSpot.', 'icon' => '🟠'],
            'openai' => ['name' => 'OpenAI live assist', 'description' => 'Real-time AI suggestions during live calls and post-call summaries.', 'icon' => '🤖'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function enabled(Tenant $tenant): array
    {
        return array_values($tenant->settings['integrations'] ?? []);
    }
}
