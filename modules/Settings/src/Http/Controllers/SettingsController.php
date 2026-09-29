<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\AccountSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SaasFoundation\Models\Membership;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $membership = $user->currentMembership;

        return view('settings::pages.settings', [
            'user' => $user,
            'membership' => $membership,
            'timezones' => $this->timezones(),
            'locales' => $this->locales(),
            'accountSetting' => AccountSetting::current(),
            'notificationSettings' => $this->notificationSettings($membership),
            'canManageAccount' => $this->deallyCan('deally.settings.manage'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'in:en,es,fr,de'],
        ]);

        $request->user()->update($data);

        if ($this->deallyCan('deally.settings.manage') && $request->has('demand_pipeline_threshold')) {
            $threshold = (int) $request->validate([
                'demand_pipeline_threshold' => ['required', 'integer', 'min:0', 'max:1000000000'],
            ])['demand_pipeline_threshold'];

            AccountSetting::current()->update(['demand_pipeline_threshold' => $threshold]);
        }

        if ($this->deallyCan('deally.settings.manage') && $request->has('notifications')) {
            /* The toggles are backed by hidden 0s, so an unchecked box still
               submits and is persisted as off. Absent entirely (a request that
               did not carry the section) leaves the settings untouched. */
            $prefs = $request->validate([
                'notifications' => ['nullable', 'array'],
                'notifications.*' => ['boolean'],
            ])['notifications'] ?? [];

            $tenant = $this->deallyMembership()?->tenant;

            if ($tenant !== null) {
                $settings = $tenant->settings ?? [];
                $settings['notifications'] = [
                    'expert_gaps' => (bool) ($prefs['expert_gaps'] ?? false),
                    'call_reports' => (bool) ($prefs['call_reports'] ?? false),
                ];
                $tenant->update(['settings' => $settings]);
            }
        }

        return back()->with('toast', 'Profile updated.');
    }

    /**
     * Which notification categories the company has decided to receive.
     *
     * @return array{expert_gaps: bool, call_reports: bool}
     */
    protected function notificationSettings(?Membership $membership): array
    {
        $configured = $membership?->tenant?->settings['notifications'] ?? [];

        return [
            'expert_gaps' => (bool) ($configured['expert_gaps'] ?? true),
            'call_reports' => (bool) ($configured['call_reports'] ?? true),
        ];
    }

    /** @return array<string, string> */
    protected function timezones(): array
    {
        $zones = [];
        foreach (timezone_identifiers_list() as $zone) {
            if (! Str::startsWith($zone, ['Etc/', 'SystemV/', 'Factory'])) {
                $zones[$zone] = $zone;
            }
        }

        return $zones;
    }

    /** @return array<string, string> */
    protected function locales(): array
    {
        $names = [
            'en' => 'English',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
        ];

        return collect($names)->mapWithKeys(
            fn (string $name, string $code): array => [$code => "{$name} ({$code})"]
        )->all();
    }
}
