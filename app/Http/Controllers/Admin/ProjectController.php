<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Branch;
use App\Models\Project;
use App\Support\CodeGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $branchId = request()->integer('branch_id') ?: null;
        $projects = Project::query()
            ->with('branch')
            ->withCount(['blocks', 'lots'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'branches' => Branch::query()->orderBy('name')->get(),
            'branchId' => $branchId,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.projects.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request, CodeGenerator $codeGenerator): RedirectResponse
    {
        $project = DB::transaction(function () use ($request, $codeGenerator): Project {
            $project = Project::query()->create($request->validated() + ['code' => $codeGenerator->placeholder()]);
            $project->update(['code' => $codeGenerator->project($project->getKey())]);

            return $project;
        });

        return redirect()->route('admin.projects.edit', $project)->with('status', 'project-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Project $project): View
    {
        return view('admin.projects.edit', [
            'project' => $project->load([
                'branch',
                'blocks' => fn ($query) => $query->withCount('lots'),
            ])->loadCount('lots'),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->safe()->only(['branch_id', 'name', 'is_active']));

        return back()->with('status', 'project-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        abort_unless(request()->user()->can('projects.delete'), 403);

        if ($project->blocks()->exists()) {
            return back()->withErrors(['project' => 'Proyek yang memiliki blok dan kavling tidak dapat dihapus.']);
        }

        $project->delete();

        return redirect()->route('admin.projects.index')->with('status', 'project-deleted');
    }
}
