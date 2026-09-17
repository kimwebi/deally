<?php

namespace SaasFoundation\Http\Controllers\Tenant;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\Tenant;
use SaasFoundation\Models\TenantSetting;

class TenantSettingController extends Controller
{
    public function index(Tenant $tenant)
    {
        $settings = TenantSetting::forTenant($tenant->id)
            ->latest()
            ->get()
            ->keyBy('key');

        return view('tenant.settings.index', compact('tenant', 'settings'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'timezone' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
            'currency' => ['nullable', 'string', 'max:3'],
            'settings' => ['nullable', 'array'],
            'settings.*' => ['nullable', 'string'],
        ]);

        if (isset($validated['timezone'])) {
            $tenant->update(['timezone' => $validated['timezone']]);
        }

        if (isset($validated['locale'])) {
            $tenant->update(['locale' => $validated['locale']]);
        }

        if (isset($validated['currency'])) {
            $tenant->update(['currency' => $validated['currency']]);
        }

        if (! empty($validated['settings'])) {
            foreach ($validated['settings'] as $key => $value) {
                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'key' => $key],
                    ['value' => $value, 'type' => 'string']
                );
            }
        }

        return back()->with('success', 'Tenant settings updated successfully.');
    }
}
