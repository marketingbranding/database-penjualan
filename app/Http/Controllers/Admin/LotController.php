<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLotStatusRequest;
use App\LotStatus;
use App\Models\Branch;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\User;
use App\Notifications\LotStatusChangedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LotController extends Controller
{
    public function index(): View
    {
        $branchId = request()->integer('branch_id') ?: null;
        $projects = Project::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        $projectId = request()->integer('project_id') ?: null;
        if ($projectId && ! $projects->contains('id', $projectId)) {
            $projectId = null;
        }

        $blocks = ProjectBlock::query()
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when(! $projectId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $blockId = request()->integer('block_id') ?: null;
        if ($blockId && ! $blocks->contains('id', $blockId)) {
            $blockId = null;
        }

        $status = LotStatus::tryFrom((string) request('status'));

        $lots = Lot::query()
            ->with(['project.branch', 'block'])
            ->when($branchId, fn ($query) => $query->whereHas('project', fn ($project) => $project->where('branch_id', $branchId)))
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId))
            ->when($blockId, fn ($query) => $query->where('project_block_id', $blockId))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderBy('project_id')
            ->orderBy('project_block_id')
            ->orderBy('number')
            ->paginate(30)
            ->withQueryString();

        return view('admin.lots.index', [
            'branches' => Branch::query()->orderBy('name')->get(),
            'projects' => $projects,
            'blocks' => $blocks,
            'lots' => $lots,
            'statuses' => LotStatus::cases(),
            'branchId' => $branchId,
            'projectId' => $projectId,
            'blockId' => $blockId,
            'status' => $status,
        ]);
    }

    public function update(UpdateLotStatusRequest $request, Lot $lot): RedirectResponse
    {
        $previousStatus = $lot->status;
        $newStatus = LotStatus::from($request->validated('status'));
        $lot->update(['status' => $newStatus]);

        if ($previousStatus !== $newStatus) {
            $lot->load('project');
            User::query()
                ->where('branch_id', $lot->project->branch_id)
                ->get()
                ->each(fn (User $user) => $user->notify(
                    new LotStatusChangedNotification($lot, $previousStatus, $newStatus),
                ));
        }

        return back()->with('status', 'lot-updated');
    }
}
