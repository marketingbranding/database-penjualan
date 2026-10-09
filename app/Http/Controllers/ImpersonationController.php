<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor->hasGlobalRole('super_admin'), 403);
        abort_if($actor->is($user), 422, 'Super Admin tidak dapat login sebagai dirinya sendiri.');
        abort_if($user->hasGlobalRole('super_admin'), 403, 'Akun Super Admin lain tidak dapat di-impersonate.');
        abort_if($request->session()->has('impersonator_id'), 409, 'Sesi impersonation sedang berjalan.');

        $request->session()->put([
            'impersonator_id' => $actor->getKey(),
            'impersonator_name' => $actor->name,
            'impersonator_active_team_id' => $request->session()->get('active_team_id'),
            'impersonated_user_id' => $user->getKey(),
        ]);
        $request->session()->forget('active_team_id');
        $request->session()->regenerate();

        AuditLogger::recordAs($actor, $user, 'impersonation_started', [], [
            'impersonated_user_id' => $user->getKey(),
        ], 'Super Admin login sebagai pengguna lain');

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalUserId = $request->session()->get('impersonator_id');
        $currentUser = $request->user();

        abort_unless($originalUserId && $currentUser, 403);

        $originalUser = User::query()->findOrFail($originalUserId);
        abort_unless($originalUser->hasGlobalRole('super_admin'), 403);

        AuditLogger::recordAs($originalUser, $currentUser, 'impersonation_stopped', [
            'impersonated_user_id' => $currentUser->getKey(),
        ], [], 'Super Admin kembali dari sesi impersonation');

        Auth::login($originalUser);
        $originalTeamId = $request->session()->get('impersonator_active_team_id');
        $request->session()->forget([
            'impersonator_id',
            'impersonator_name',
            'impersonator_active_team_id',
            'impersonated_user_id',
        ]);
        $request->session()->put('active_team_id', $originalTeamId);
        $request->session()->regenerate();

        return redirect()->route('admin.users.index')->with('status', 'impersonation-stopped');
    }
}
