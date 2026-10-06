<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Team;
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
            ->with('parent')
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
        return view('admin.teams.create', [
            'parents' => Team::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $team = DB::transaction(function () use ($request): Team {
            $team = Team::query()->create($request->validated());
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
            'parents' => Team::query()->whereKeyNot($team)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

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
}
