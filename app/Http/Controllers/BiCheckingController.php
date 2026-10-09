<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBiCheckingRequest;
use App\Http\Requests\UpdateBiCheckingRequest;
use App\Models\Bank;
use App\Models\BankUnit;
use App\Models\BiChecking;
use App\Models\LeadTimeTarget;
use App\Models\SalesCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BiCheckingController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $search = trim((string) request('search'));
        $decision = request('bank_decision');
        $result = request('slik_result');

        $checkings = BiChecking::query()
            ->with(['salesCase.consumer', 'salesCase.lot.project.branch', 'bank', 'bankUnit'])
            ->whereHas('salesCase', fn ($query) => $this->scopeSalesCases($query, $user))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereHas('salesCase.consumer', fn ($consumer) => $consumer
                    ->where('nama_konsumen', 'like', "%{$search}%")
                    ->orWhere('no_ktp', 'like', "%{$search}%"))
                    ->orWhereHas('salesCase.lot', fn ($lot) => $lot->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('bank', fn ($bank) => $bank->where('name', 'like', "%{$search}%"));
            }))
            ->when($decision, fn ($query) => $query->where('bank_decision', $decision))
            ->when($result, fn ($query) => $query->where('slik_result', $result))
            ->latest('checking_date')
            ->paginate(20)
            ->withQueryString();

        return view('sales-process.bi-checking.index', compact('checkings', 'search', 'decision', 'result'));
    }

    public function create(): View
    {
        return view('sales-process.bi-checking.create', $this->formData());
    }

    public function store(StoreBiCheckingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id'], $request->user());
        $this->ensureBankUnit((int) $data['bank_id'], (int) $data['bank_unit_id']);

        if ($salesCase->consumer_status === 'MUNDUR') {
            throw ValidationException::withMessages(['sales_case_id' => 'Konsumen yang sudah mundur tidak dapat diproses BI Checking.']);
        }

        BiChecking::query()->create([
            ...$data,
            ...$this->leadTimeData($salesCase, $data['checking_date']),
        ]);

        return redirect()->route('sales-process.bi-checking')->with('status', 'bi-created');
    }

    public function edit(BiChecking $biChecking): View
    {
        $biChecking->load(['salesCase.consumer', 'salesCase.lot.project.branch', 'bank', 'bankUnit']);
        $this->ensureAccess($biChecking->salesCase, request()->user());

        return view('sales-process.bi-checking.edit', [
            ...$this->formData(),
            'biChecking' => $biChecking,
        ]);
    }

    public function update(UpdateBiCheckingRequest $request, BiChecking $biChecking): RedirectResponse
    {
        $biChecking->load('salesCase');
        $this->ensureAccess($biChecking->salesCase, $request->user());
        $data = $request->validated();
        $this->ensureBankUnit((int) $data['bank_id'], (int) $data['bank_unit_id']);

        $biChecking->update([
            ...$data,
            ...$this->leadTimeData($biChecking->salesCase, $data['checking_date']),
        ]);

        return back()->with('status', 'bi-updated');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $user = request()->user();
        $salesCases = SalesCase::query()
            ->active()
            ->where('consumer_status', '!=', 'MUNDUR')
            ->with(['consumer', 'lot.project.branch', 'promotion'])
            ->whereHas('lot.project', fn ($project) => $user->canAccessAllBranches()
                ? $project
                : $project->where('branch_id', $user->branch_id))
            ->orderByDesc('created_at')
            ->get();

        return [
            'salesCases' => $salesCases,
            'banks' => Bank::query()->with(['units' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function salesCaseForUser(int $id, User $user): SalesCase
    {
        $salesCase = SalesCase::query()->active()->with(['consumer', 'lot.project.branch'])->findOrFail($id);
        $this->ensureAccess($salesCase, $user);

        return $salesCase;
    }

    private function ensureAccess(SalesCase $salesCase, User $user): void
    {
        abort_unless(
            $user->can('bi-checking.view')
                && ($user->canAccessAllBranches() || $salesCase->lot->project->branch_id === $user->branch_id),
            403,
        );
    }

    private function ensureBankUnit(int $bankId, int $unitId): void
    {
        abort_unless(BankUnit::query()->whereKey($unitId)->where('bank_id', $bankId)->where('is_active', true)->exists(), 422);
    }

    /** @return array{lead_time_days: int, lead_time_status: string} */
    private function leadTimeData(SalesCase $salesCase, string $checkingDate): array
    {
        $days = max(0, Carbon::parse($salesCase->created_at)->startOfDay()->diffInDays(Carbon::parse($checkingDate)->startOfDay()));
        $target = LeadTimeTarget::query()->where('process', 'psjb')->where('is_active', true)->first()?->target_days;

        return [
            'lead_time_days' => $days,
            'lead_time_status' => $target === null ? 'NON-SLA' : ($days <= $target ? 'SLA' : 'NON-SLA'),
        ];
    }

    private function scopeSalesCases($query, User $user)
    {
        return $query->active()->when(! $user->canAccessAllBranches(), fn ($query) => $query->whereHas(
            'lot.project',
            fn ($project) => $project->where('branch_id', $user->branch_id),
        ));
    }
}
