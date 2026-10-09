<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectBlockRequest;
use App\LotStatus;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Support\CodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProjectBlockController extends Controller
{
    public function store(StoreProjectBlockRequest $request, Project $project, CodeGenerator $codeGenerator): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $project, $codeGenerator): void {
            $lockedProject = Project::query()->whereKey($project)->lockForUpdate()->firstOrFail();
            $blockCode = $codeGenerator->nextBlock($lockedProject);
            $block = $lockedProject->blocks()->create([
                'code' => $blockCode['code'],
                'sort_order' => $blockCode['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            $now = now();
            $lots = collect(range(1, $data['lot_count']))->map(fn (int $number): array => [
                'project_id' => $lockedProject->getKey(),
                'project_block_id' => $block->getKey(),
                'number' => $number,
                'code' => $block->code.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => LotStatus::Available->value,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Lot::query()->insert($lots);
        });

        return back()->with('status', 'block-created');
    }

    public function destroy(Project $project, ProjectBlock $block): RedirectResponse
    {
        abort_unless(request()->user()->can('projects.update'), 403);
        abort_unless($block->project_id === $project->getKey(), 404);

        if ($block->lots()->where('status', '!=', LotStatus::Available->value)->exists()) {
            return back()->withErrors(['block' => 'Blok dengan kavling yang sudah diproses tidak dapat dihapus.']);
        }

        $block->delete();

        return back()->with('status', 'block-deleted');
    }
}
