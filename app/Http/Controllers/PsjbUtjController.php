<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePsjbUtjRequest;
use App\Http\Requests\UpdatePsjbUtjRequest;
use App\Models\PsjbUtjCase;
use App\Models\SalesCase;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PsjbUtjController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $search = trim((string) request('search'));
        $consumerStatus = request('consumer_status');
        $filingStatus = request('filing_status');

        $psjbCases = PsjbUtjCase::query()
            ->with(['salesCase.consumer', 'salesCase.lot.project.branch', 'salesCase.promotion', 'coordinator', 'sales'])
            ->whereHas('salesCase', function ($query) use ($user): void {
                $query->active()
                    ->when(! $user->canAccessAllBranches(), fn ($query) => $query->whereHas(
                        'lot.project',
                        fn ($project) => $project->where('branch_id', $user->branch_id),
                    ));
            })
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('psjb_id', 'like', "%{$search}%")
                    ->orWhereHas('salesCase.consumer', fn ($consumer) => $consumer
                        ->where('nama_konsumen', 'like', "%{$search}%")
                        ->orWhere('no_ktp', 'like', "%{$search}%"))
                    ->orWhereHas('salesCase.lot', fn ($lot) => $lot->where('code', 'like', "%{$search}%"));
            }))
            ->when($consumerStatus, fn ($query) => $query->whereHas('salesCase', fn ($salesCase) => $salesCase->where('consumer_status', $consumerStatus)))
            ->when($filingStatus, fn ($query) => $query->where('filing_status', $filingStatus))
            ->latest('psjb_date')
            ->paginate(20)
            ->withQueryString();

        return view('sales-process.psjb-utj.index', compact(
            'psjbCases', 'search', 'consumerStatus', 'filingStatus',
        ));
    }

    public function create(): View
    {
        return view('sales-process.psjb-utj.create', $this->formData());
    }

    public function store(StorePsjbUtjRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id'], request()->user());

        if ($salesCase->psjbUtj()->exists()) {
            throw ValidationException::withMessages(['sales_case_id' => 'Data konsumen ini sudah memiliki PSJB.']);
        }

        $psjb = DB::transaction(function () use ($data, $salesCase): PsjbUtjCase {
            return $salesCase->psjbUtj()->create([
                ...$this->persistableData($data),
                'psjb_id' => $this->generatePsjbId($data['psjb_date'], $salesCase),
            ]);
        });

        return redirect()->route('sales-process.psjb-utj.edit', $psjb)->with('status', 'psjb-created');
    }

    public function edit(PsjbUtjCase $psjbUtj): View
    {
        $psjbUtj->load(['salesCase.consumer', 'salesCase.lot.project.branch', 'salesCase.promotion']);
        $this->ensureAccess($psjbUtj->salesCase, request()->user());

        return view('sales-process.psjb-utj.edit', [
            ...$this->formData($psjbUtj),
            'psjbUtj' => $psjbUtj,
        ]);
    }

    public function update(UpdatePsjbUtjRequest $request, PsjbUtjCase $psjbUtj): RedirectResponse
    {
        $psjbUtj->load('salesCase');
        $this->ensureAccess($psjbUtj->salesCase, request()->user());
        $psjbUtj->update($this->persistableData($request->validated(), $psjbUtj));

        return back()->with('status', 'psjb-updated');
    }

    /** @return array<string, mixed> */
    private function formData(?PsjbUtjCase $psjbUtj = null): array
    {
        $user = request()->user();
        $salesCases = SalesCase::query()
            ->active()
            ->with(['consumer', 'lot.project.branch', 'promotion'])
            ->where(function ($query) use ($psjbUtj): void {
                $query->whereDoesntHave('psjbUtj');

                if ($psjbUtj) {
                    $query->orWhereKey($psjbUtj->sales_case_id);
                }
            })
            ->when(! $user->canAccessAllBranches(), fn ($query) => $query->whereHas(
                'lot.project',
                fn ($project) => $project->where('branch_id', $user->branch_id),
            ))
            ->orderByDesc('created_at')
            ->get();

        return [
            'salesCases' => $salesCases,
            'users' => User::query()->orderBy('name')->get(),
        ];
    }

    private function salesCaseForUser(int $salesCaseId, User $user): SalesCase
    {
        $salesCase = SalesCase::query()
            ->active()
            ->with(['consumer', 'lot.project.branch', 'promotion'])
            ->findOrFail($salesCaseId);
        $this->ensureAccess($salesCase, $user);

        return $salesCase;
    }

    private function ensureAccess(SalesCase $salesCase, User $user): void
    {
        abort_unless(
            $user->can('sales-process.view')
                && ($user->canAccessAllBranches() || $salesCase->lot->project->branch_id === $user->branch_id),
            403,
        );
    }

    /** @param array<string, mixed> $data */
    private function persistableData(array $data, ?PsjbUtjCase $existing = null): array
    {
        $installmentCount = isset($data['dp_installment_count']) && $data['dp_installment_count'] !== ''
            ? (int) $data['dp_installment_count']
            : null;
        $dpAllIn = (float) $data['dp_all_in'];
        $landArea = (float) $data['land_area_m2'];
        $landPrice = (float) $data['land_price_per_m2'];

        return [
            'psjb_date' => $data['psjb_date'],
            'process_stage' => $data['process_stage'],
            'sp3k_issued' => (bool) $data['sp3k_issued'],
            'coordinator_id' => $data['coordinator_id'] ?? null,
            'sales_id' => $data['sales_id'] ?? null,
            'unit_price' => $data['unit_price'],
            'utj_date' => $data['utj_date'] ?? null,
            'utj_amount' => $data['utj_amount'],
            'dp_all_in' => $dpAllIn,
            'dp_installment_amount' => $installmentCount ? round($dpAllIn / $installmentCount, 2) : 0,
            'dp_installment_count' => $installmentCount,
            'land_area_m2' => $landArea,
            'land_price_per_m2' => $landPrice,
            'land_total' => round($landArea * $landPrice, 2),
            'payment_detail' => $data['payment_detail'] ?? null,
            'external_transaction_id' => $data['external_transaction_id'] ?? null,
            'external_status' => $data['external_status'] ?? null,
            'external_method' => $data['external_method'] ?? null,
            'notes' => $data['notes'] ?? null,
            'delay_category' => $data['delay_category'] ?? null,
            'filing_status' => $data['filing_status'] ?? null,
            'filing_lead_time' => null,
            'filing_lead_time_status' => null,
            'ongoing_delay_category' => $data['ongoing_delay_category'] ?? null,
            'filing_delay_reason' => $data['filing_delay_reason'] ?? null,
        ];
    }

    private function generatePsjbId(string $date, SalesCase $salesCase): string
    {
        $base = date('ymd', strtotime($date)).'-PSJB-'.Str::upper($salesCase->payment_method->value).'-'.$salesCase->consumer->no_ktp;

        return PsjbUtjCase::query()->where('psjb_id', $base)->exists()
            ? $base.'-'.$salesCase->getKey()
            : $base;
    }
}
