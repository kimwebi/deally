<?php

namespace SaasFoundation\Http\Controllers\Central;

use Illuminate\Http\Request;
use SaasFoundation\Http\Controllers\Controller;
use SaasFoundation\Models\AuditLog;

class CentralAuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with(['user', 'tenant'])
            ->when($request->filled('action'), function ($query) use ($request): void {
                $query->where('action', $request->string('action'));
            })
            ->when($request->filled('user_id'), function ($query) use ($request): void {
                $query->where('user_id', $request->integer('user_id'));
            })
            ->when($request->filled('tenant_id'), function ($query) use ($request): void {
                $query->where('tenant_id', $request->string('tenant_id'));
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->where('created_at', '>=', $request->date('date_from'));
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->where('created_at', '<=', $request->date('date_to'));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(function ($query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $actions = AuditLog::distinct()->pluck('action')->sort()->values();

        return view('central.audit.index', compact('logs', 'actions'));
    }
}
