<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): View
    {
        $organizationAccess = $request->user()->hasPermission('audit.view');

        return view('dashboard', [
            'organizationAccess' => $organizationAccess,
            'activeUsers' => $organizationAccess ? User::where('is_active', true)->count() : null,
            'events' => AuditLog::with('user')->when(! $organizationAccess, fn ($q) => $q->where('user_id', $request->user()->id))->latest('id')->limit(5)->get(),
        ]);
    }

    public function audit(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $events = AuditLog::with('user')
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->when($filters['from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->latest('id')->paginate(25)->withQueryString();

        return view('audit', ['events' => $events, 'actions' => AuditLog::distinct()->orderBy('action')->pluck('action')]);
    }

    public function settings(): View
    {
        return view('settings', ['roles' => Role::with('permissions')->get()]);
    }
}
