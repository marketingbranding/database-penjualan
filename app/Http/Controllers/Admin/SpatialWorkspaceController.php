<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSpatialWorkspaceAssignmentRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SpatialWorkspaceController extends Controller
{
    public function update(UpdateSpatialWorkspaceAssignmentRequest $request, User $user): JsonResponse
    {
        $team = Team::query()->findOrFail($request->integer('team_id'));
        $actor = $request->user();

        abort_unless($actor->canAccessAllBranches() || $team->branch_id === $actor->branch_id, 403);
        abort_unless($actor->canAccessAllBranches() || $user->branch_id === $actor->branch_id, 403);

        if ($request->filled('reports_to_id')) {
            $reportsTo = User::query()->findOrFail($request->integer('reports_to_id'));
            abort_if($reportsTo->is($user) || $reportsTo->branch_id !== $team->branch_id, 422, 'Atasan harus berada di branch yang sama dan tidak boleh dirinya sendiri.');
        }

        $previous = [
            'branch_id' => $user->branch_id,
            'reports_to_id' => $user->reports_to_id,
            'team_ids' => $user->teams()->pluck('teams.id')->all(),
        ];

        DB::transaction(function () use ($request, $team, $user): void {
            $user->update([
                'branch_id' => $team->branch_id,
                'reports_to_id' => $request->integer('reports_to_id') ?: null,
            ]);
            $user->teams()->sync([$team->getKey() => ['is_default' => true]]);
        });

        AuditLogger::record($user, 'spatial_assignment_updated', $previous, [
            'branch_id' => $user->branch_id,
            'reports_to_id' => $user->reports_to_id,
            'team_ids' => [$team->getKey()],
        ], 'Assignment user diperbarui melalui Spatial Workspace');

        return response()->json([
            'message' => 'Assignment user berhasil diperbarui.',
            'user' => [
                'id' => $user->getKey(),
                'branchId' => $user->branch_id,
                'teamId' => $team->getKey(),
                'reportsToId' => $user->reports_to_id,
            ],
        ]);
    }

    public function __invoke(Request $request): View
    {
        $actor = $request->user();
        $branchId = $actor->canAccessAllBranches() ? null : $actor->branch_id;
        $branches = Branch::query()
            ->where('is_active', true)
            ->when($branchId, fn ($query) => $query->whereKey($branchId))
            ->with([
                'users' => fn ($query) => $query
                    ->with(['teams', 'globalRoles', 'reportsTo'])
                    ->orderBy('name'),
                'teams' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['parent', 'supervisor', 'coordinator', 'users'])
                    ->withCount('users')
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();

        $users = $branches->flatMap->users->unique('id')->values();
        $roleAssignments = $this->roleAssignments($users);

        return view('admin.spatial-workspace', [
            'workspace' => [
                'branches' => $branches->map(fn (Branch $branch): array => [
                    'id' => $branch->getKey(),
                    'code' => $branch->code,
                    'name' => $branch->name,
                    'teams' => $branch->teams->map(fn ($team): array => [
                        'id' => $team->getKey(),
                        'code' => $team->code,
                        'name' => $team->name,
                        'parentId' => $team->parent_id,
                        'supervisorId' => $team->supervisor_id,
                        'coordinatorId' => $team->coordinator_id,
                        'userIds' => $team->users->modelKeys(),
                        'userCount' => $team->users_count,
                    ])->values()->all(),
                    'userIds' => $branch->users->modelKeys(),
                ])->values()->all(),
                'users' => $users->map(fn (User $user): array => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'branchId' => $user->branch_id,
                    'reportsToId' => $user->reports_to_id,
                    'teamIds' => $user->teams->modelKeys(),
                    'teamNames' => $user->teams->pluck('name')->values()->all(),
                    'globalRoles' => $user->globalRoles->pluck('name')->values()->all(),
                    'roles' => $roleAssignments->get($user->getKey(), collect())->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }

    /** @param Collection<int, User> $users */
    private function roleAssignments(Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        $modelHasRoles = config('permission.table_names.model_has_roles');
        $roles = (new Role)->getTable();

        return DB::table($modelHasRoles.' as model_roles')
            ->join($roles.' as roles', 'roles.id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_roles.model_id', $users->pluck('id'))
            ->select('model_roles.model_id', 'model_roles.team_id', 'roles.name')
            ->orderBy('roles.name')
            ->get()
            ->groupBy('model_id')
            ->map(fn (Collection $assignments): Collection => $assignments->map(fn ($assignment): array => [
                'name' => $assignment->name,
                'teamId' => $assignment->team_id,
            ]));
    }
}
