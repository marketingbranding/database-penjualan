<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserAccessRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
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
        $users = User::query()
            ->with(['teams', 'globalRoles'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.users.create');
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
        $teams = Team::query()
            ->with(['users' => fn ($query) => $query->whereKey($user)])
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
            'user' => $user->load(['teams', 'globalRoles']),
            'teams' => $teams,
            'globalRoles' => Role::query()->whereNull('team_id')->orderBy('name')->get(),
            'teamRoles' => Role::query()->whereNotNull('team_id')->orderBy('name')->get()->groupBy('team_id'),
            'teamRoleIds' => $teamRoleIds,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserAccessRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
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

        if ($user->hasGlobalRole('super_admin')
            && ! $globalRoleIds->contains($superAdminRole->getKey())
            && $superAdminRole->globalUsers()->count() <= 1) {
            throw ValidationException::withMessages([
                'global_role_ids' => 'Super admin terakhir tidak dapat diturunkan.',
            ]);
        }

        DB::transaction(function () use ($data, $globalRoleIds, $teamIds, $teamRoleIds, $user): void {
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

        return back()->with('status', 'user-access-updated');
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
