<?php

namespace Deally\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use SaasFoundation\Models\Activity;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeDeally('deally.activity.view');

        $membership = $this->deallyMembership();

        $query = Activity::query();

        if ($membership !== null) {
            $query->forTenant($membership->tenant_id);
        }

        if ($request->filled('level')) {
            $query->where('properties->level', $request->query('level'));
        }

        $activities = $query->with('user')->latest()->take(100)->get();

        return view('core::pages.activity.index', [
            'activities' => $activities,
            'activeLevel' => $request->query('level', 'all'),
        ]);
    }
}
