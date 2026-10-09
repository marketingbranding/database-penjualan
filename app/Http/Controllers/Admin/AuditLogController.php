<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()->can('audit-logs.view'), 403);

        $user = request()->user();
        $branchId = $user->canAccessAllBranches()
            ? (request()->integer('branch_id') ?: null)
            : $user->branch_id;
        $event = request('event');
        $search = trim((string) request('search'));

        $logs = ActivityLog::query()
            ->with(['user', 'branch'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($event, fn ($query) => $query->where('event', $event))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'branchId' => $branchId,
            'event' => $event,
            'search' => $search,
            'events' => ActivityLog::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
                ->distinct()
                ->orderBy('event')
                ->pluck('event'),
        ]);
    }
}
