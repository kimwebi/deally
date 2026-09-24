<?php

namespace Deally\Settings\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\AccountSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

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

        return back()->with('toast', 'Profile updated.');
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
