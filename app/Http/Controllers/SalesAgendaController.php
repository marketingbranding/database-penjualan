<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesAgendaRequest;
use App\Http\Requests\UpdateSalesAgendaRequest;
use App\Models\Branch;
use App\Models\Lead;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\User;
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

        $agendas = SalesAgenda::query()
            ->with(['creator', 'branch', 'lead', 'salesCase.consumer', 'salesCase.lot.project', 'evidence'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(! $user->can('sales.view-all'), function ($query) use ($user): void {
                if ($user->can('sales.view-team')) {
                    $teamIds = $user->teams()->pluck('teams.id')->all();
                    $query->where(function ($query) use ($user, $teamIds): void {
                        $query->where('created_by', $user->getKey())
                            ->orWhereIn('team_id', $teamIds);
                    });
                } else {
                    $query->where('created_by', $user->getKey());
                }
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($category, fn ($query) => $query->where('activity_category', $category))
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
            'categories' => SalesAgenda::CATEGORIES,
            'statuses' => SalesAgenda::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SalesAgenda::class);

        return view('sales-agendas.create', [
            'salesCases' => $this->availableSalesCases($request->user()),
            'leads' => $this->availableLeads($request->user()),
            'categories' => SalesAgenda::CATEGORIES,
            'agenda' => null,
        ]);
    }

    public function store(StoreSalesAgendaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureSalesCaseBelongsToUserBranch($request->user(), $data['sales_case_id'] ?? null);
        $this->ensureLeadBelongsToUserScope($request->user(), $data['lead_id'] ?? null);

        $agenda = DB::transaction(function () use ($data, $request): SalesAgenda {
            $agenda = SalesAgenda::query()->create([
                ...collect($data)->except('evidence')->all(),
                'branch_id' => $request->user()->branch_id,
                'team_id' => $request->session()->get('active_team_id') ?: null,
                'created_by' => $request->user()->getKey(),
                'status' => 'planned',
            ]);

            $this->storeEvidence($agenda, $request);

            return $agenda;
        });

        return redirect()->route('sales-agendas.index')->with('status', 'agenda-created');
    }

    public function edit(SalesAgenda $salesAgenda): View
    {
        $this->authorize('update', $salesAgenda);

        return view('sales-agendas.create', [
            'salesCases' => $this->availableSalesCases(request()->user()),
            'leads' => $this->availableLeads(request()->user()),
            'categories' => SalesAgenda::CATEGORIES,
            'agenda' => $salesAgenda->load('evidence'),
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
        $this->authorize('update', $salesAgenda);
        $data = $request->validate(['activity_result' => ['required', 'string', 'max:5000']]);

        $salesAgenda->update([
            'activity_result' => $data['activity_result'],
            'status' => 'done',
            'completed_at' => now(),
        ]);

        return back()->with('status', 'agenda-completed');
    }

    public function cancel(SalesAgenda $salesAgenda): RedirectResponse
    {
        $this->authorize('update', $salesAgenda);
        $salesAgenda->update(['status' => 'cancelled']);

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
                'file_path' => $file->store('sales-agendas'),
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
}
