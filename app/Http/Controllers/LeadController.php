<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewLeadHandoffRequest;
use App\Http\Requests\StoreLeadActivityRequest;
use App\Http\Requests\StoreLeadHandoffRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadFollowUpRequest;
use App\Http\Requests\UpdateLeadQualificationRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadHandoff;
use App\Models\Project;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\BusinessTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lead::class);

        $user = $request->user();
        $branchId = $user->canAccessAllBranches()
            ? ($request->integer('branch_id') ?: null)
            : $user->branch_id;
        $salesPicId = $request->integer('sales_pic_id') ?: null;
        $active = $request->boolean('active');
        $status = in_array($request->string('status_lead')->toString(), Lead::STATUSES, true)
            ? $request->string('status_lead')->toString()
            : null;
        $source = in_array($request->string('sumber_lead')->toString(), Lead::SOURCES, true)
            ? $request->string('sumber_lead')->toString()
            : null;
        $channel = in_array($request->string('kanal_masuk')->toString(), Lead::CHANNELS, true)
            ? $request->string('kanal_masuk')->toString()
            : null;
        $followUp = in_array($request->string('follow_up')->toString(), ['today', 'overdue', 'upcoming'], true)
            ? $request->string('follow_up')->toString()
            : null;
        $search = trim($request->string('search')->toString());
        $activeStatuses = array_values(array_diff(Lead::STATUSES, ['Tidak Lolos BI Checking']));

        $leadsQuery = Lead::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('leads.view-all'), function ($query) use ($user): void {
                if ($user->can('leads.view-branch') || $user->can('leads.monitor')) {
                    return;
                }

                if ($user->can('leads.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('sales_pic_id', $user->getKey());
            })
            ->when($salesPicId, fn ($query) => $query->where('sales_pic_id', $salesPicId))
            ->when($active, fn ($query) => $query->whereIn('status_lead', $activeStatuses))
            ->when($status, fn ($query) => $query->where('status_lead', $status))
            ->when($source, fn ($query) => $query->where('sumber_lead', $source))
            ->when($channel, fn ($query) => $query->where('kanal_masuk', $channel))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('tanggal_lead', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('tanggal_lead', '<=', $request->date('date_to')))
            ->when($request->filled('project_id'), fn ($query) => $query->where('project_id', $request->integer('project_id')))
            ->when($followUp === 'today', fn ($query) => $query->whereBetween('next_follow_up_at', [BusinessTime::utcStartOfDay(BusinessTime::now()->toDateString()), BusinessTime::utcEndOfDay(BusinessTime::now()->toDateString())]))
            ->when($followUp === 'overdue', fn ($query) => $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', BusinessTime::now()->utc()))
            ->when($followUp === 'upcoming', fn ($query) => $query->where('next_follow_up_at', '>', BusinessTime::now()->utc()))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('id_lead', 'like', "%{$search}%")
                    ->orWhere('nama_konsumen', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%");
            }));

        $leads = (clone $leadsQuery)
            ->with(['project', 'salesPic', 'team.coordinator', 'creator', 'latestHandoff'])
            ->latest('tanggal_lead')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'view' => 'table',
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'salesUsers' => $this->availableSalesUsers($user),
            'projects' => $this->availableProjects($user),
            'branchId' => $branchId,
            'salesPicId' => $salesPicId,
            'active' => $active,
            'status' => $status,
            'source' => $source,
            'channel' => $channel,
            'followUp' => $followUp,
            'statuses' => Lead::STATUSES,
            'sources' => Lead::SOURCES,
            'channels' => Lead::CHANNELS,
        ]);
    }

    public function monitoring(Request $request): View
    {
        $this->authorize('monitor', Lead::class);

        $user = $request->user();
        $branchId = $user->canAccessAllBranches()
            ? ($request->integer('branch_id') ?: null)
            : $user->branch_id;
        $filters = [
            'branch_id' => $branchId,
            'date_from' => $request->input('date_from', today()->startOfMonth()->toDateString()),
            'date_to' => $request->input('date_to', today()->toDateString()),
            'project_id' => $request->integer('project_id') ?: null,
            'coordinator_id' => $request->integer('coordinator_id') ?: null,
            'sales_pic_id' => $request->integer('sales_pic_id') ?: null,
            'sumber_lead' => in_array($request->string('sumber_lead')->toString(), Lead::SOURCES, true)
                ? $request->string('sumber_lead')->toString()
                : null,
            'kanal_masuk' => in_array($request->string('kanal_masuk')->toString(), Lead::CHANNELS, true)
                ? $request->string('kanal_masuk')->toString()
                : null,
            'status_lead' => in_array($request->string('status_lead')->toString(), Lead::STATUSES, true)
                ? $request->string('status_lead')->toString()
                : null,
        ];
        $query = $this->monitoringQuery($user, $filters);
        $statusTotals = (clone $query)
            ->selectRaw('status_lead, COUNT(*) AS total')
            ->groupBy('status_lead')
            ->pluck('total', 'status_lead');

        return view('leads.monitoring', [
            'leads' => (clone $query)
                ->with(['project', 'salesPic', 'team.coordinator', 'branch', 'latestActivity'])
                ->latest('tanggal_lead')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'summary' => [
                'total' => (clone $query)->count(),
                'new' => (int) ($statusTotals['No Respon'] ?? 0),
                'discussion' => (int) ($statusTotals['Diskusi'] ?? 0),
                'faceToFace' => (int) ($statusTotals['Tatap Muka'] ?? 0),
                'siteVisit' => (int) ($statusTotals['Cek Lokasi'] ?? 0),
                'utj' => (int) ($statusTotals['UTJ'] ?? 0),
            ],
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'projects' => $this->availableProjects($user),
            'coordinators' => $this->availableCoordinators($user, $branchId),
            'salesUsers' => $this->availableMonitoringSales($user, $branchId),
            'sources' => Lead::SOURCES,
            'channels' => Lead::CHANNELS,
            'statuses' => Lead::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Lead::class);

        $originAgenda = $this->originAgendaForUser($request);
        $prefill = $originAgenda !== null ? $this->prefillFromAgenda($originAgenda, $request->user()) : [];

        return view('leads.create', [
            ...$this->formData($request->user()),
            'originAgenda' => $originAgenda,
            'prefill' => $prefill,
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $data = $request->validated();
        [$branchId, $teamId] = $this->resolveOwnership($request->user(), (int) $data['sales_pic_id']);
        $this->ensureProjectBelongsToBranch($data['project_id'] ?? null, $branchId, $teamId);
        $originAgenda = $this->resolveOriginAgenda($request->user(), $data['origin_agenda_id'] ?? null);

        $lead = DB::transaction(function () use ($data, $request, $branchId, $teamId, $originAgenda): Lead {
            $lead = Lead::query()->create([
                ...$data,
                'id_lead' => Lead::generateLeadId($data['tanggal_lead'], $data['sumber_lead'], $data['kanal_masuk']),
                'branch_id' => $branchId,
                'team_id' => $teamId,
                'created_by' => $request->user()->getKey(),
                'origin_agenda_id' => $originAgenda?->getKey(),
            ]);

            $lead->activities()->create([
                'user_id' => $request->user()->getKey(),
                'activity_type' => 'Lead Baru',
                'description' => 'Lead dibuat.',
                'status_to' => $lead->status_lead,
                'happened_at_source' => 'system',
                'happened_at' => now(),
            ]);

            return $lead;
        });

        return redirect()->route('leads.show', $lead)->with('status', 'lead-created');
    }

    public function show(Lead $lead): View
    {
        $this->authorize('view', $lead);

        return view('leads.show', ['lead' => $lead->load([
            'branch', 'team', 'project', 'salesPic', 'creator', 'activities.user',
            'salesAgendas.creator', 'originAgenda', 'latestHandoff.requester', 'latestHandoff.reviewer',
        ])]);
    }

    public function updateFollowUp(UpdateLeadFollowUpRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $lead, $request): void {
            $lead->update($data);
            $followUpNote = $data['next_follow_up_note'] ?? null;

            if (($data['next_follow_up_at'] ?? null) !== null || $followUpNote !== null) {
                $lead->activities()->create([
                    'user_id' => $request->user()->getKey(),
                    'activity_type' => 'Follow Up',
                    'description' => 'Follow-up berikutnya dijadwalkan'.($followUpNote ? ': '.$followUpNote : '.'),
                    'happened_at_source' => 'system',
                    'happened_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'lead-follow-up-updated');
    }

    public function updateQualification(UpdateLeadQualificationRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        $isQualified = $data['qualification_status'] === 'qualified';

        DB::transaction(function () use ($data, $lead, $request, $isQualified): void {
            $lead->update([
                ...$data,
                'qualified_at' => $isQualified ? now() : null,
                'qualified_by' => $isQualified ? $request->user()->getKey() : null,
            ]);
            $lead->activities()->create([
                'user_id' => $request->user()->getKey(),
                'activity_type' => 'Status Update',
                'description' => 'Kualifikasi Buku Saku diubah menjadi '.str($data['qualification_status'])->replace('_', ' ')->title().'.',
                'status_to' => $lead->status_lead,
                'happened_at_source' => 'system',
                'happened_at' => now(),
            ]);
        });

        return back()->with('status', 'lead-qualification-updated');
    }

    public function storeHandoff(StoreLeadHandoffRequest $request, Lead $lead): RedirectResponse
    {
        if ($lead->qualification_status !== 'qualified') {
            throw ValidationException::withMessages(['note' => 'Lead harus berstatus Qualified sebelum diserahkan.']);
        }

        if ($lead->handoffs()->whereIn('status', ['submitted', 'accepted'])->exists()) {
            throw ValidationException::withMessages(['note' => 'Lead sudah memiliki handoff aktif.']);
        }

        $lead->handoffs()->create([
            ...$request->validated(),
            'requested_by' => $request->user()->getKey(),
            'status' => 'submitted',
        ]);

        return back()->with('status', 'lead-handoff-created');
    }

    public function reviewHandoff(ReviewLeadHandoffRequest $request, LeadHandoff $leadHandoff): RedirectResponse
    {
        $lead = $leadHandoff->lead;
        $this->authorize('view', $lead);

        $leadHandoff->update([
            ...$request->validated(),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'lead-handoff-reviewed');
    }

    public function edit(Lead $lead): View
    {
        $this->authorize('update', $lead);

        return view('leads.create', [
            ...$this->formData(request()->user()),
            'lead' => $lead,
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        [$branchId, $teamId] = $this->resolveOwnership($request->user(), (int) $data['sales_pic_id']);
        $this->ensureProjectBelongsToBranch($data['project_id'] ?? null, $branchId, $teamId);

        DB::transaction(function () use ($data, $branchId, $teamId, $lead, $request): void {
            $statusFrom = $lead->status_lead;
            $lead->update([
                ...$data,
                'branch_id' => $branchId,
                'team_id' => $teamId,
            ]);

            if ($statusFrom !== $lead->status_lead) {
                $lead->activities()->create([
                    'user_id' => $request->user()->getKey(),
                    'activity_type' => 'Status Update',
                    'description' => 'Status lead diubah dari '.$statusFrom.' menjadi '.$lead->status_lead.'.',
                    'status_from' => $statusFrom,
                    'status_to' => $lead->status_lead,
                    'happened_at_source' => 'system',
                    'happened_at' => now(),
                ]);
            }
        });

        return redirect()->route('leads.show', $lead)->with('status', 'lead-updated');
    }

    public function storeActivity(StoreLeadActivityRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        $statusFrom = $lead->status_lead;
        $statusTo = $data['status_lead'] ?? null;

        DB::transaction(function () use ($data, $lead, $request, $statusFrom, $statusTo): void {
            if ($statusTo !== null && $statusTo !== $statusFrom) {
                $lead->update(['status_lead' => $statusTo]);
            }

            $lead->activities()->create([
                'user_id' => $request->user()->getKey(),
                'activity_type' => $data['activity_type'],
                'description' => $data['description'],
                'status_from' => $statusTo !== null && $statusTo !== $statusFrom ? $statusFrom : null,
                'status_to' => $statusTo !== null && $statusTo !== $statusFrom ? $statusTo : null,
                'happened_at_source' => 'user',
                'happened_at' => $data['happened_at'],
            ]);
        });

        return back()->with('status', 'lead-activity-created');
    }

    /** @return array{projects: Collection<int, Project>, salesUsers: Collection<int, User>, sources: list<string>, channels: list<string>, activities: list<string>, statuses: list<string>, lead: ?Lead} */
    private function formData(User $user): array
    {
        return [
            'projects' => $this->availableProjects($user),
            'salesUsers' => $this->availableSalesUsers($user),
            'sources' => Lead::SOURCES,
            'channels' => Lead::CHANNELS,
            'activities' => Lead::ACTIVITIES,
            'statuses' => Lead::STATUSES,
            'lead' => null,
            'originAgenda' => null,
            'prefill' => [],
        ];
    }

    /** @var array<string, array{sumber: string, kanal: string, aktivitas: string}> */
    private const AGENDA_PREFILL = [
        'TikTok Live' => ['sumber' => 'Online', 'kanal' => 'TikTok', 'aktivitas' => 'Live'],
        'Canvassing' => ['sumber' => 'Offline', 'kanal' => 'Canvassing', 'aktivitas' => 'Sebar Brosur'],
        'Event/Pameran' => ['sumber' => 'Offline', 'kanal' => 'Pameran', 'aktivitas' => 'Event'],
        'Telepon/WhatsApp' => ['sumber' => 'Online', 'kanal' => 'WhatsApp', 'aktivitas' => 'Broadcast WhatsApp'],
    ];

    private function originAgendaForUser(Request $request): ?SalesAgenda
    {
        $agendaId = $request->integer('from_agenda') ?: null;

        if ($agendaId === null) {
            return null;
        }

        $agenda = SalesAgenda::query()->findOrFail($agendaId);
        $this->authorize('view', $agenda);

        return $agenda;
    }

    private function resolveOriginAgenda(User $user, mixed $agendaId): ?SalesAgenda
    {
        $agendaId = (int) $agendaId;

        if ($agendaId <= 0) {
            return null;
        }

        $agenda = SalesAgenda::query()->findOrFail($agendaId);
        $this->authorize('view', $agenda);

        return $agenda;
    }

    /** @return array<string, mixed> */
    private function prefillFromAgenda(SalesAgenda $agenda, User $user): array
    {
        $mapped = self::AGENDA_PREFILL[$agenda->activity_category] ?? [
            'sumber' => 'Offline', 'kanal' => 'Tidak Diketahui', 'aktivitas' => 'Tidak Diketahui',
        ];

        return [
            'origin_agenda_id' => $agenda->getKey(),
            'tanggal_lead' => today()->toDateString(),
            'sumber_lead' => $mapped['sumber'],
            'kanal_masuk' => $mapped['kanal'],
            'aktivitas_lead' => $mapped['aktivitas'],
            'status_lead' => 'No Respon',
            'sales_pic_id' => $agenda->owner_id,
            'keterangan' => 'Berasal dari agenda: '.$agenda->title.' ('.$agenda->agenda_date->format('d M Y').').',
        ];
    }

    /** @return Collection<int, Project> */
    private function availableProjects(User $user): Collection
    {
        return Project::query()
            ->where('is_active', true)
            ->when(! $user->canAccessAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    private function availableSalesUsers(User $user): Collection
    {
        if ($user->can('leads.view-own') && ! $user->can('leads.view-team')) {
            return collect([$user]);
        }

        if ($user->can('leads.view-branch') || $user->can('leads.monitor')) {
            return User::query()
                ->where('branch_id', $user->branch_id)
                ->orderBy('name')
                ->get();
        }

        if ($user->canAccessAllBranches()) {
            return User::query()->whereNotNull('branch_id')->orderBy('name')->get();
        }

        $teamId = (int) request()->session()->get('active_team_id');

        if ($teamId > 0 && $user->teams()->whereKey($teamId)->exists()) {
            return Team::query()->findOrFail($teamId)->users()->orderBy('name')->get();
        }

        return $user->teams()->where('branch_id', $user->branch_id)->with('users')->get()->flatMap->users->unique('id')->sortBy('name')->values();
    }

    /** @param array<string, int|string|null> $filters */
    private function monitoringQuery(User $user, array $filters): Builder
    {
        return Lead::query()
            ->when($filters['branch_id'], fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $filters['branch_id'], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('leads.view-all'), function (Builder $query) use ($user): void {
                if ($user->can('leads.view-branch') || $user->can('leads.monitor')) {
                    return;
                }

                if ($user->can('leads.view-team')) {
                    $query->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                    return;
                }

                $query->where('sales_pic_id', $user->getKey());
            })
            ->when($filters['date_from'], fn (Builder $query, string $date) => $query->whereDate('tanggal_lead', '>=', $date))
            ->when($filters['date_to'], fn (Builder $query, string $date) => $query->whereDate('tanggal_lead', '<=', $date))
            ->when($filters['project_id'], fn (Builder $query, int $projectId) => $query->where('project_id', $projectId))
            ->when($filters['coordinator_id'], fn (Builder $query, int $coordinatorId) => $query->whereHas('team', fn (Builder $team) => $team->where('coordinator_id', $coordinatorId)))
            ->when($filters['sales_pic_id'], fn (Builder $query, int $salesPicId) => $query->where('sales_pic_id', $salesPicId))
            ->when($filters['sumber_lead'], fn (Builder $query, string $source) => $query->where('sumber_lead', $source))
            ->when($filters['kanal_masuk'], fn (Builder $query, string $channel) => $query->where('kanal_masuk', $channel))
            ->when($filters['status_lead'], fn (Builder $query, string $status) => $query->where('status_lead', $status));
    }

    /** @return Collection<int, User> */
    private function availableCoordinators(User $user, ?int $branchId): Collection
    {
        return Team::query()
            ->where('is_active', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNotNull('coordinator_id')
            ->with('coordinator')
            ->get()
            ->pluck('coordinator')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /** @return Collection<int, User> */
    private function availableMonitoringSales(User $user, ?int $branchId): Collection
    {
        return User::query()
            ->whereNotNull('branch_id')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereHas('leads')
            ->orderBy('name')
            ->get();
    }

    /** @return array{0: int, 1: ?int} */
    private function resolveOwnership(User $actor, int $salesPicId): array
    {
        $salesPic = User::query()->with('teams')->findOrFail($salesPicId);

        if (! $actor->canAccessAllBranches() && $salesPic->branch_id !== $actor->branch_id) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales PIC harus berasal dari cabang Anda.']);
        }

        if ($actor->can('leads.view-own') && ! $actor->can('leads.view-team') && $salesPic->isNot($actor)) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales hanya dapat membuat lead untuk dirinya sendiri.']);
        }

        $actorTeamIds = $actor->teams()->where('is_active', true)->pluck('teams.id')->all();
        $salesTeams = $salesPic->teams->where('is_active', true);
        $sessionTeamId = (int) request()->session()->get('active_team_id');
        $preferredTeam = $sessionTeamId > 0
            ? $salesTeams->firstWhere('id', $sessionTeamId)
            : null;
        $sharedTeamId = $preferredTeam?->getKey() ?? ($actor->canAccessAllBranches()
            ? $salesTeams->first()?->getKey()
            : $salesTeams->first(fn (Team $team): bool => in_array($team->getKey(), $actorTeamIds, true))?->getKey());

        if (! $actor->canAccessAllBranches() && $actor->can('leads.view-team') && $sharedTeamId === null) {
            throw ValidationException::withMessages(['sales_pic_id' => 'Sales PIC harus berada di tim Anda.']);
        }

        return [$salesPic->branch_id, $sharedTeamId];
    }

    private function ensureProjectBelongsToBranch(?int $projectId, int $branchId, ?int $teamId = null): void
    {
        if ($projectId === null) {
            if ($teamId !== null && Team::query()->whereKey($teamId)->whereNotNull('project_id')->exists()) {
                throw ValidationException::withMessages(['project_id' => 'Lead untuk tim ini wajib memilih proyek tim.']);
            }

            return;
        }

        if (! Project::query()->whereKey($projectId)->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['project_id' => 'Proyek harus berada di cabang Sales PIC.']);
        }

        if ($teamId === null) {
            return;
        }

        $teamProjectId = Team::query()->whereKey($teamId)->value('project_id');

        if ($teamProjectId !== null && $teamProjectId !== $projectId) {
            throw ValidationException::withMessages(['project_id' => 'Proyek Lead harus sesuai dengan proyek tim Sales.']);
        }
    }
}
