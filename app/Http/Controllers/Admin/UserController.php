<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BranchAssignmentChangedNotification;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $search = trim((string) request('search'));
        $branchId = request()->integer('branch_id') ?: null;
        $globalRoleId = request()->integer('global_role_id') ?: null;

        $users = User::query()
            ->with(['branch', 'teams', 'globalRoles'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($globalRoleId, fn ($query) => $query->whereHas('globalRoles', fn ($query) => $query->whereKey($globalRoleId)))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'branches' => Branch::query()->orderBy('name')->get(),
            'globalRoles' => Role::query()->whereNull('team_id')->orderBy('name')->get(),
            'search' => $search,
            'branchId' => $branchId,
            'globalRoleId' => $globalRoleId,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.users.create', [
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());

        return redirect()->route('admin.users.edit', $user)->with('status', 'user-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(User $user): View
    {
        $actor = request()->user();
        $branchId = $actor->canAccessAllBranches() ? null : $actor->branch_id;

        $teams = Team::query()
            ->with(['users' => fn ($query) => $query->whereKey($user)])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $teamRoleIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->get()
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('role_id')->map(fn ($id) => (int) $id)->all());

        return view('admin.users.edit', [
            'user' => $user->load(['teams', 'globalRoles', 'reportsTo']),
            'teams' => $teams,
            'globalRoles' => Role::query()->whereNull('team_id')->orderBy('name')->get(),
            'teamRoles' => Role::query()->whereNotNull('team_id')->orderBy('name')->get()->groupBy('team_id'),
            'teamRoleIds' => $teamRoleIds,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
            'supervisors' => User::query()
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereKeyNot($user)
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $previousBranchId = $user->branch_id;
        $previousAccess = [
            'branch_id' => $user->branch_id,
            'reports_to_id' => $user->reports_to_id,
            'global_role_ids' => $user->globalRoles->modelKeys(),
            'team_ids' => $user->teams()->pluck('teams.id')->all(),
        ];
        $teamIds = collect($data['team_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $globalRoleIds = collect($data['global_role_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $teamRoleIds = collect($data['team_role_ids'] ?? []);

        foreach ($teamRoleIds as $teamId => $roleIds) {
            $roleIds = collect($roleIds)->map(fn ($id) => (int) $id)->unique();

            if (! $teamIds->contains((int) $teamId)
                || Role::query()->where('team_id', $teamId)->whereKey($roleIds)->count() !== $roleIds->count()) {
                throw ValidationException::withMessages([
                    'team_role_ids' => 'Role harus berasal dari tim yang dipilih.',
                ]);
            }
        }

        $superAdminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $branchRoleIds = Role::query()->whereNull('team_id')->where('name', '!=', 'super_admin')->pluck('id');

        if ($globalRoleIds->contains(fn ($id) => $branchRoleIds->contains($id)) && empty($data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Role cabang wajib ditugaskan ke satu cabang.',
            ]);
        }

        if (! empty($data['reports_to_id'])) {
            $supervisor = User::query()->findOrFail((int) $data['reports_to_id']);

            if (! empty($data['branch_id']) && $supervisor->branch_id !== (int) $data['branch_id']) {
                throw ValidationException::withMessages([
                    'reports_to_id' => 'Atasan harus berada di cabang yang sama.',
                ]);
            }
        }

        if ($user->hasGlobalRole('super_admin')
            && ! $globalRoleIds->contains($superAdminRole->getKey())
            && $superAdminRole->globalUsers()->count() <= 1) {
            throw ValidationException::withMessages([
                'global_role_ids' => 'Super admin terakhir tidak dapat diturunkan.',
            ]);
        }

        DB::transaction(function () use ($data, $globalRoleIds, $teamIds, $teamRoleIds, $user): void {
            $user->update([
                'branch_id' => $data['branch_id'] ?? null,
                'reports_to_id' => $data['reports_to_id'] ?? null,
            ]);
            $user->teams()->sync($teamIds->mapWithKeys(fn (int $teamId): array => [
                $teamId => ['is_default' => $teamId === (int) ($data['default_team_id'] ?? 0)],
            ])->all());
            $user->globalRoles()->sync($globalRoleIds->all());

            DB::table(config('permission.table_names.model_has_roles'))
                ->where('model_type', $user->getMorphClass())
                ->where('model_id', $user->getKey())
                ->delete();

            foreach ($teamRoleIds as $teamId => $roleIds) {
                setPermissionsTeamId((int) $teamId);
                $user->unsetRelation('roles')->syncRoles($roleIds);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        setPermissionsTeamId(session('active_team_id'));
        $user->unsetRelation('globalRoles')->unsetRelation('roles')->unsetRelation('permissions');

        if ($previousBranchId !== $user->fresh()->branch_id) {
            $user->notify(new BranchAssignmentChangedNotification($user->branch));
        }

        AuditLogger::record($user, 'access_updated', $previousAccess, [
            'branch_id' => $user->branch_id,
            'reports_to_id' => $user->reports_to_id,
            'global_role_ids' => $globalRoleIds->all(),
            'team_ids' => $teamIds->all(),
        ], 'Akses pengguna diperbarui');

        return back()->with('status', 'user-access-updated');
    }

    /**
     * Apply a bulk action to the selected users.
     */
    public function bulk(BulkUserRequest $request): RedirectResponse
    {
        $action = $request->validated('action');
        $current = request()->user();
        $users = User::query()
            ->with('globalRoles')
            ->whereKey(collect($request->validated('ids'))->map(fn ($id) => (int) $id)->unique())
            ->get();

        if ($action === 'assign-branch') {
            abort_unless($current->can('users.update'), 403);

            $branchId = (int) $request->validated('branch_id');
            $assigned = 0;

            foreach ($users as $user) {
                if ($user->branch_id !== $branchId) {
                    $user->update(['branch_id' => $branchId]);
                    $assigned++;
                }
            }

            return back()->with('status', 'users-branch-assigned')->with('count', $assigned);
        }

        abort_unless($current->can('users.delete'), 403);

        $superAdminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $superAdminCount = $superAdminRole->globalUsers()->count();
        $deleted = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ($user->is($current)
                || ($user->hasGlobalRole('super_admin') && $superAdminCount <= 1)) {
                $skipped++;

                continue;
            }

            $user->delete();
            $deleted++;
        }

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'users-deleted')
            ->with('count', $deleted)
            ->with('skipped', $skipped);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_unless(request()->user()->can('users.delete'), 403);
        abort_if(request()->user()->is($user), 422, 'Akun sendiri tidak dapat dihapus.');

        $superAdminRole = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();

        if ($user->hasGlobalRole('super_admin') && $superAdminRole->globalUsers()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'Super admin terakhir tidak dapat dihapus.',
            ]);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }
}
