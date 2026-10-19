<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSalesAgendaRequest;
use App\Http\Requests\RedirectSalesAgendaRequest;
use App\Http\Requests\StoreSalesAgendaRequest;
use App\Http\Requests\UpdateSalesAgendaRequest;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesAgendaAssignmentHistory;
use App\Models\SalesCase;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SalesAgendaAssignmentNotification;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesAgendaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalesAgenda::class);

        $user = $request->user();
        $branchId = $user->canAccessAllBranches()
            ? ($request->integer('branch_id') ?: null)
            : $user->branch_id;
        $status = in_array($request->string('status')->toString(), SalesAgenda::STATUSES, true)
            ? $request->string('status')->toString()
            : null;
        $category = in_array($request->string('activity_category')->toString(), SalesAgenda::CATEGORIES, true)
            ? $request->string('activity_category')->toString()
            : null;
        $ownerId = $request->integer('owner_id') ?: null;
        $view = $request->string('view')->toString() === 'redirected' ? 'redirected' : 'active';

        $agendas = SalesAgenda::query()
            ->with(['creator', 'owner', 'branch', 'lead', 'salesCase.consumer', 'salesCase.lot.project', 'evidence', 'assignmentHistories.fromUser', 'assignmentHistories.toUser', 'assignmentHistories.byUser'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($view === 'redirected', function ($query) use ($user): void {
                $query->whereHas('assignmentHistories', fn ($history) => $history
                    ->where('action', 'redirected')
                    ->where('from_user_id', $user->getKey()));
            })
            ->when($view === 'active', function ($query) use ($user): void {
                if ($user->can('sales.view-all') || $user->can('leads.view-branch') || $user->can('leads.monitor')) {
                    return;
                }

                if ($user->can('sales.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('owner_id', $user->getKey());
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($category, fn ($query) => $query->where('activity_category', $category))
            ->when($ownerId, fn ($query, int $ownerId) => $query->where('owner_id', $ownerId))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('agenda_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('agenda_date', '<=', $request->date('date_to')))
            ->orderByDesc('agenda_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('sales-agendas.index', [
            'agendas' => $agendas,
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'branchId' => $branchId,
            'status' => $status,
            'category' => $category,
            'ownerId' => $ownerId,
            'view' => $view,
            'categories' => SalesAgenda::CATEGORIES,
            'statuses' => SalesAgenda::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesAgenda::class);
        $team = $this->resolveAgendaTeam($request->user());

        return view('sales-agendas.create', [
            'salesCases' => $this->availableSalesCases($request->user()),
            'leads' => $this->availableLeads($request->user()),
            'categories' => SalesAgenda::CATEGORIES,
            'agenda' => null,
            'owners' => $team ? $this->availableAgendaOwners($team) : collect([$request->user()]),
            'canChooseOwner' => $team !== null && $this->isTeamRole($request->user(), $team, 'koordinator_sales'),
            'readonly' => false,
        ]);
    }

    public function store(StoreSalesAgendaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureSalesCaseBelongsToUserBranch($request->user(), $data['sales_case_id'] ?? null);
        $this->ensureLeadBelongsToUserScope($request->user(), $data['lead_id'] ?? null);
        $agendaTeam = $this->resolveAgendaTeam($request->user());
        $owner = $this->resolveAgendaOwner($request->user(), $agendaTeam, $data['owner_id'] ?? null);

        if ($owner->getKey() !== $request->user()->getKey() && $request->hasFile('evidence')) {
            throw ValidationException::withMessages(['evidence' => 'Bukti foto hanya dapat diunggah oleh pemilik agenda.']);
        }

        $agenda = DB::transaction(function () use ($data, $request, $agendaTeam, $owner): SalesAgenda {
            $agenda = SalesAgenda::query()->create([
                ...collect($data)->except('evidence')->all(),
                'branch_id' => $agendaTeam?->branch_id ?? $request->user()->branch_id,
                'team_id' => $agendaTeam?->getKey(),
                'created_by' => $request->user()->getKey(),
                'owner_id' => $owner->getKey(),
                'status' => 'planned',
            ]);

            $this->storeEvidence($agenda, $request);

            if ($owner->isNot($request->user())) {
                SalesAgendaAssignmentHistory::query()->create([
                    'sales_agenda_id' => $agenda->getKey(),
                    'action' => 'created_for',
                    'to_user_id' => $owner->getKey(),
                    'by_user_id' => $request->user()->getKey(),
                ]);
                AuditLogger::record($agenda, 'agenda_created_for', newValues: ['owner_id' => $owner->getKey()], description: 'Agenda dibuat untuk Sales lain');
            }

            return $agenda;
        });

        if ($owner->isNot($request->user())) {
            $owner->notify(new SalesAgendaAssignmentNotification($agenda, 'created_for', $request->user()->name));
        }

        return redirect()->route('sales-agendas.index')->with('status', 'agenda-created');
    }

    public function edit(SalesAgenda $salesAgenda): View
    {
        $this->authorize('update', $salesAgenda);

        return $this->agendaView($salesAgenda, false);
    }

    public function show(SalesAgenda $salesAgenda): View
    {
        $this->authorize('view', $salesAgenda);

        return $this->agendaView($salesAgenda, true);
    }

    public function redirectForm(SalesAgenda $salesAgenda): View
    {
        $this->authorize('redirect', $salesAgenda);

        return view('sales-agendas.redirect', [
            'agenda' => $salesAgenda->load(['owner', 'lead', 'team']),
            'owners' => $this->availableAgendaOwners($salesAgenda->team),
        ]);
    }

    public function redirect(RedirectSalesAgendaRequest $request, SalesAgenda $salesAgenda): RedirectResponse
    {
        $data = $request->validated();
        $previousOwner = null;
        $newOwner = null;

        DB::transaction(function () use ($data, $request, $salesAgenda, &$previousOwner, &$newOwner): void {
            $agenda = SalesAgenda::query()->lockForUpdate()->with(['team', 'owner', 'evidence'])->findOrFail($salesAgenda->getKey());
            $this->authorize('redirect', $agenda);
            $this->ensurePlannedWithoutEvidence($agenda);
            $newOwner = $this->activeSalesInTeam((int) $data['owner_id'], $agenda->team_id);

            if ($newOwner->getKey() === $agenda->owner_id) {
                throw ValidationException::withMessages(['owner_id' => 'Pilih Sales lain sebagai pemilik agenda.']);
            }

            $previousOwner = $agenda->owner;
            $agenda->update([
                'owner_id' => $newOwner->getKey(),
                'agenda_date' => $data['agenda_date'] ?? $agenda->agenda_date,
            ]);
            SalesAgendaAssignmentHistory::query()->create([
                'sales_agenda_id' => $agenda->getKey(),
                'action' => 'redirected',
                'from_user_id' => $previousOwner?->getKey(),
                'to_user_id' => $newOwner->getKey(),
                'by_user_id' => $request->user()->getKey(),
                'reason' => $data['reason'],
            ]);
            AuditLogger::record($agenda, 'agenda_redirected', ['owner_id' => $previousOwner?->getKey()], ['owner_id' => $newOwner->getKey()], 'Agenda dialihkan oleh koordinator');
        });

        $agenda = $salesAgenda->fresh(['owner']);
        $newOwner?->notify(new SalesAgendaAssignmentNotification($agenda, 'redirected_new_owner', $request->user()->name));
        $previousOwner?->notify(new SalesAgendaAssignmentNotification($agenda, 'redirected_previous_owner', $request->user()->name, $newOwner?->name, $data['reason']));

        return redirect()->route('sales-agendas.show', $salesAgenda)->with('status', 'agenda-redirected');
    }

    public function cancelAsCoordinator(CancelSalesAgendaRequest $request, SalesAgenda $salesAgenda): RedirectResponse
    {
        $data = $request->validated();
        $owner = null;

        DB::transaction(function () use ($data, $request, $salesAgenda, &$owner): void {
            $agenda = SalesAgenda::query()->lockForUpdate()->with(['team', 'owner', 'evidence'])->findOrFail($salesAgenda->getKey());
            $this->authorize('cancelAsCoordinator', $agenda);
            $this->ensurePlannedWithoutEvidence($agenda);
            $owner = $agenda->owner;
            $agenda->update(['status' => 'cancelled']);
            SalesAgendaAssignmentHistory::query()->create([
                'sales_agenda_id' => $agenda->getKey(),
                'action' => 'cancelled_by_coordinator',
                'from_user_id' => $owner?->getKey(),
                'by_user_id' => $request->user()->getKey(),
                'reason' => $data['reason'],
            ]);
            AuditLogger::record($agenda, 'agenda_cancelled_by_coordinator', ['owner_id' => $owner?->getKey()], ['status' => 'cancelled'], 'Agenda dibatalkan oleh koordinator');
        });

        if ($owner !== null) {
            $owner->notify(new SalesAgendaAssignmentNotification($salesAgenda->fresh(), 'cancelled', $request->user()->name, null, $data['reason']));
        }

        return redirect()->route('sales-agendas.show', $salesAgenda)->with('status', 'agenda-cancelled');
    }

    public function destroy(SalesAgenda $salesAgenda): RedirectResponse
    {
        $this->authorize('delete', $salesAgenda);
        $salesAgenda->delete();

        return redirect()->route('sales-agendas.index')->with('status', 'agenda-deleted');
    }

    private function agendaView(SalesAgenda $salesAgenda, bool $readonly): View
    {
        $salesAgenda->load(['evidence', 'owner', 'creator', 'lead', 'assignmentHistories.fromUser', 'assignmentHistories.toUser', 'assignmentHistories.byUser']);

        return view('sales-agendas.create', [
            'salesCases' => $this->availableSalesCases(request()->user()),
            'leads' => $this->availableLeads(request()->user()),
            'categories' => SalesAgenda::CATEGORIES,
            'agenda' => $salesAgenda->load('evidence'),
            'owners' => $salesAgenda->team ? $this->availableAgendaOwners($salesAgenda->team) : collect([$salesAgenda->owner]),
            'canChooseOwner' => false,
            'readonly' => $readonly,
        ]);
    }

    public function update(UpdateSalesAgendaRequest $request, SalesAgenda $salesAgenda): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureSalesCaseBelongsToUserBranch($request->user(), $data['sales_case_id'] ?? null);
        $this->ensureLeadBelongsToUserScope($request->user(), $data['lead_id'] ?? null);
        $this->ensureEvidenceLimit($salesAgenda, $request);

        DB::transaction(function () use ($data, $request, $salesAgenda): void {
            $salesAgenda->update(collect($data)->except('evidence')->all());
            $this->storeEvidence($salesAgenda, $request);
        });

        return redirect()->route('sales-agendas.index')->with('status', 'agenda-updated');
    }

    public function complete(Request $request, SalesAgenda $salesAgenda): RedirectResponse
    {
        $data = $request->validate(['activity_result' => ['required', 'string', 'max:5000']]);

        DB::transaction(function () use ($data, $salesAgenda): void {
            $agenda = SalesAgenda::query()->lockForUpdate()->findOrFail($salesAgenda->getKey());
            $this->authorize('update', $agenda);
            $this->ensurePlanned($agenda);

            $agenda->update([
                'activity_result' => $data['activity_result'],
                'status' => 'done',
                'completed_at' => now(),
            ]);
        });

        return back()->with('status', 'agenda-completed');
    }

    public function cancel(SalesAgenda $salesAgenda): RedirectResponse
    {
        DB::transaction(function () use ($salesAgenda): void {
            $agenda = SalesAgenda::query()->lockForUpdate()->with('evidence')->findOrFail($salesAgenda->getKey());
            $this->authorize('update', $agenda);
            $this->ensurePlannedWithoutEvidence($agenda);
            $agenda->update(['status' => 'cancelled']);
        });

        return back()->with('status', 'agenda-cancelled');
    }

    private function availableSalesCases(User $user): Collection
    {
        return SalesCase::query()
            ->active()
            ->with(['consumer', 'lot.project'])
            ->when(! $user->canAccessAllBranches(), fn ($query) => $query->whereHas('lot.project', fn ($project) => $project->where('branch_id', $user->branch_id)))
            ->when(! $user->canAccessAllBranches() && ! $user->branch_id, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();
    }

    private function ensureSalesCaseBelongsToUserBranch(User $user, ?int $salesCaseId): void
    {
        if ($salesCaseId === null || $user->canAccessAllBranches()) {
            return;
        }

        $belongsToBranch = SalesCase::query()
            ->whereKey($salesCaseId)
            ->whereHas('lot.project', fn ($project) => $project->where('branch_id', $user->branch_id))
            ->exists();

        if (! $belongsToBranch) {
            throw ValidationException::withMessages(['sales_case_id' => 'Sales case tidak berada di cabang Anda.']);
        }
    }

    private function availableLeads(User $user): Collection
    {
        return Lead::query()
            ->with('salesPic')
            ->when(! $user->canAccessAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->when(! $user->canAccessAllBranches() && ! $user->branch_id, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('leads.view-all') && ! $user->can('leads.view-branch') && ! $user->can('leads.monitor'), function ($query) use ($user): void {
                if ($user->can('leads.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('sales_pic_id', $user->getKey());
            })
            ->latest('tanggal_lead')
            ->limit(100)
            ->get();
    }

    private function ensureLeadBelongsToUserScope(User $user, ?int $leadId): void
    {
        if ($leadId === null) {
            return;
        }

        $lead = Lead::query()->findOrFail($leadId);

        abort_unless($user->can('view', $lead), 403);
    }

    private function storeEvidence(SalesAgenda $agenda, Request $request): void
    {
        foreach ($request->file('evidence', []) as $file) {
            if ($agenda->evidence()->count() >= 2) {
                break;
            }

            $agenda->evidence()->create([
                'file_path' => $file->store('sales-agendas', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }
    }

    private function ensureEvidenceLimit(SalesAgenda $agenda, Request $request): void
    {
        $fileCount = count($request->file('evidence', []));

        if ($agenda->evidence()->count() + $fileCount > 2) {
            throw ValidationException::withMessages(['evidence' => 'Bukti foto maksimal 2 file per agenda.']);
        }
    }

    private function resolveAgendaTeam(User $user): ?Team
    {
        $hasTeamSalesRole = DB::table(config('permission.table_names.model_has_roles').' as model_roles')
            ->join(config('permission.table_names.roles').' as roles', 'roles.id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', $user->getMorphClass())
            ->where('model_roles.model_id', $user->getKey())
            ->whereNotNull('roles.team_id')
            ->whereIn('roles.name', ['sales', 'koordinator_sales'])
            ->exists();

        if (! $hasTeamSalesRole) {
            return null;
        }

        $activeTeams = $user->teams()->where('teams.is_active', true)->get();

        if ($activeTeams->isEmpty()) {
            throw ValidationException::withMessages([
                'team_id' => 'Anda belum memiliki tim aktif. Hubungi admin untuk ditugaskan ke tim Sales.',
            ]);
        }

        if ($activeTeams->count() === 1) {
            return $activeTeams->first();
        }

        $requestedActiveTeamId = request()->attributes->get('requested_active_team_id');
        $activeTeamId = (int) $requestedActiveTeamId;
        $activeTeam = $activeTeams->firstWhere('id', $activeTeamId);

        if ($activeTeam === null) {
            throw ValidationException::withMessages([
                'team_id' => 'Pilih tim aktif terlebih dahulu sebelum membuat agenda.',
            ]);
        }

        return $activeTeam;
    }

    private function resolveAgendaOwner(User $creator, ?Team $team, mixed $ownerId): User
    {
        $ownerId = (int) $ownerId;

        if ($team === null || $ownerId <= 0 || $ownerId === $creator->getKey()) {
            return $creator;
        }

        if (! $this->isTeamRole($creator, $team, 'koordinator_sales')) {
            throw ValidationException::withMessages(['owner_id' => 'Sales hanya dapat membuat agenda untuk dirinya sendiri.']);
        }

        return $this->activeSalesInTeam($ownerId, $team->getKey());
    }

    private function activeSalesInTeam(int $userId, ?int $teamId): User
    {
        $team = $teamId !== null ? Team::query()->findOrFail($teamId) : null;
        $role = $team?->getKey() !== null
            ? Role::query()->where('team_id', $team->getKey())->where('name', 'sales')->first()
            : null;

        $user = User::query()->whereKey($userId)->whereNotNull('branch_id')->first();
        $hasRole = $role !== null && DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', (new User)->getMorphClass())
            ->where('model_id', $userId)
            ->exists();

        if ($user === null || $team === null || $user->branch_id !== $team->branch_id
            || ! $team->is_active || ! $user->teams()->whereKey($team)->exists() || ! $hasRole) {
            throw ValidationException::withMessages(['owner_id' => 'Pemilik agenda harus merupakan Sales aktif dalam tim yang sama.']);
        }

        return $user;
    }

    private function availableAgendaOwners(Team $team): Collection
    {
        return $team->users()
            ->whereNotNull('users.branch_id')
            ->orderBy('users.name')
            ->get()
            ->filter(fn (User $user): bool => $this->isTeamRole($user, $team, 'sales'))
            ->values();
    }

    private function isTeamRole(User $user, Team $team, string $roleName): bool
    {
        $role = Role::query()->where('team_id', $team->getKey())->where('name', $roleName)->first();

        return $role !== null && DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->getKey())
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', $user->getKey())
            ->exists();
    }

    private function ensurePlannedWithoutEvidence(SalesAgenda $agenda): void
    {
        $this->ensurePlanned($agenda);

        if ($agenda->evidence()->exists()) {
            throw ValidationException::withMessages(['agenda' => 'Agenda tidak dapat dialihkan atau dibatalkan karena sudah memiliki bukti foto.']);
        }
    }

    private function ensurePlanned(SalesAgenda $agenda): void
    {
        if ($agenda->status !== 'planned') {
            throw ValidationException::withMessages(['status' => 'Agenda hanya dapat diubah saat masih direncanakan.']);
        }
    }
}
