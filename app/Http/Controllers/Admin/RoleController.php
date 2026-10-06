<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Models\Team;
use App\Support\Rbac;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $roles = Role::query()
            ->with(['team', 'permissions'])
            ->withCount(['users', 'globalUsers'])
            ->orderByRaw('team_id IS NOT NULL')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.roles.create', [
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->validated('name'),
            'team_id' => $request->validated('team_id'),
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles.edit', $role)->with('status', 'role-created');
    }

    /**
     * Display the specified resource.
     */
    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role->load(['team', 'permissions']),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if (array_key_exists($role->name, Rbac::GLOBAL_ROLES + Rbac::TEAM_ROLES)
            && $request->validated('name') !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'Role bawaan sistem tidak dapat diubah namanya.',
            ]);
        }

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('status', 'role-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(request()->user()->can('roles.delete'), 403);

        if (array_key_exists($role->name, Rbac::GLOBAL_ROLES + Rbac::TEAM_ROLES)) {
            throw ValidationException::withMessages([
                'role' => 'Role bawaan sistem tidak dapat dihapus.',
            ]);
        }

        $hasGlobalAssignments = DB::table('global_role_user')->where('role_id', $role->getKey())->exists();

        if ($hasGlobalAssignments || $role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Role yang masih digunakan tidak dapat dihapus.',
            ]);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'role-deleted');
    }
}
