<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Branch;
use App\Models\Team;
use App\Models\User;
use App\Support\CodeGenerator;
use App\Support\Rbac;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $teams = Team::query()
            ->with(['parent', 'branch', 'supervisor', 'coordinator'])
            ->when(! request()->user()->canAccessAllBranches(), fn ($query) => $query->where(
                'branch_id',
                request()->user()->branch_id,
            ))
            ->withCount(['users', 'children'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.teams.index', compact('teams'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.teams.create', $this->formData());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request, CodeGenerator $codeGenerator): RedirectResponse
    {
        $team = DB::transaction(function () use ($request, $codeGenerator): Team {
            $team = Team::query()->create($request->validated() + ['code' => $codeGenerator->placeholder()]);
            $team->update(['code' => $codeGenerator->team($team->getKey())]);
            Rbac::provisionTeam($team);

            return $team;
        });

        return redirect()->route('admin.teams.edit', $team)->with('status', 'team-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Team $team): View
    {
        return view('admin.teams.edit', [
            'team' => $team,
            ...$this->formData($team),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->safe()->only([
            'name', 'parent_id', 'branch_id', 'supervisor_id', 'coordinator_id', 'is_active',
        ]));

        return back()->with('status', 'team-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Team $team): RedirectResponse
    {
        abort_unless(request()->user()->can('teams.delete'), 403);

        if ($team->users()->exists() || $team->children()->exists()) {
            return back()->withErrors([
                'team' => 'Tim yang masih memiliki anggota atau sub-tim tidak dapat dihapus.',
            ]);
        }

        $team->delete();

        return redirect()->route('admin.teams.index')->with('status', 'team-deleted');
    }

    /** @return array<string, mixed> */
    private function formData(?Team $team = null): array
    {
        $user = request()->user();
        $branchId = $user->canAccessAllBranches() ? null : $user->branch_id;

        return [
            'parents' => Team::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when($team, fn ($query) => $query->whereKeyNot($team))
                ->orderBy('name')
                ->get(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'supervisors' => User::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
        ];
    }
}
