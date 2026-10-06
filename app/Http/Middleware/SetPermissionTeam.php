<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeam
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            View::share('availableTeams', collect());
            View::share('activeTeam', null);

            return $next($request);
        }

        /** @var Collection<int, Team> $availableTeams */
        $availableTeams = $user->hasGlobalRole('super_admin')
            ? Team::query()->where('is_active', true)->orderBy('name')->get()
            : $user->teams()->where('is_active', true)->orderBy('name')->get();

        $activeTeam = $availableTeams->firstWhere('id', $request->session()->get('active_team_id'));

        if ($activeTeam === null) {
            $activeTeam = $availableTeams->first(fn (Team $team): bool => (bool) $team->pivot?->is_default)
                ?? $availableTeams->first();
        }

        $request->session()->put('active_team_id', $activeTeam?->id);
        setPermissionsTeamId($activeTeam?->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        View::share('availableTeams', $availableTeams);
        View::share('activeTeam', $activeTeam);

        return $next($request);
    }
}
