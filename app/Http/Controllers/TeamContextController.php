<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamContextController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->is_active, 404);
        abort_unless(
            $request->user()->hasGlobalRole('super_admin')
                || $request->user()->teams()->whereKey($team->getKey())->exists(),
            403,
        );

        $request->session()->put('active_team_id', $team->getKey());
        setPermissionsTeamId($team->getKey());
        $request->user()->unsetRelation('roles')->unsetRelation('permissions');

        return back()->with('status', 'team-updated');
    }
}
