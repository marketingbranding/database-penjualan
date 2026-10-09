<?php

namespace App\Http\Controllers;

use App\Http\Requests\AkadCaseRequest;
use App\Http\Requests\BankProcessRequest;
use App\Http\Requests\BastCaseRequest;
use App\Http\Requests\PpjbDeveloperCaseRequest;
use App\Http\Requests\SalesCaseFilingRequest;
use App\Http\Requests\WarrantyRequest;
use App\Models\AkadCase;
use App\Models\Bank;
use App\Models\BankProcess;
use App\Models\BankUnit;
use App\Models\BastCase;
use App\Models\LeadTimeTarget;
use App\Models\PpjbDeveloperCase;
use App\Models\SalesCase;
use App\Models\SalesCaseFiling;
use App\Models\User;
use App\Models\Warranty;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SalesProcessController extends Controller
{
    public function filingIndex(): View
    {
        $records = SalesCaseFiling::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch', 'bank', 'bankUnit'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('started_at')->paginate(20)->withQueryString();

        return view('sales-process.filing.index', [...$this->formData(), 'records' => $records]);
    }

    public function filingStore(SalesCaseFilingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);

        $filing = DB::transaction(function () use ($data, $salesCase, $request): SalesCaseFiling {
            $filing = $salesCase->filings()->create([
                ...$this->withoutKey($data, 'sales_case_id'),
                'filing_id' => $this->nextIdentifier('BRK', $salesCase, SalesCaseFiling::class, 'filing_id'),
                'created_by' => $request->user()->id,
                'deadline_at' => $this->deadline('pemberkasan', $data['started_at']),
            ]);
            $this->syncProcessStage($salesCase, 'pemberkasan');

            return $filing;
        });

        return redirect()->route('sales-process.pemberkasan')->with('status', 'filing-created')->with('record', $filing->filing_id);
    }

    public function filingUpdate(SalesCaseFilingRequest $request, SalesCaseFiling $filing): RedirectResponse
    {
        $this->ensureAccess($filing->salesCase);
        $data = $request->validated();
        $filing->update([...$this->withoutKey($data, 'sales_case_id'), 'deadline_at' => $this->deadline('pemberkasan', $data['started_at'])]);
        $this->syncProcessStage($filing->salesCase, 'pemberkasan');

        return back()->with('status', 'filing-updated');
    }

    public function bankIndex(): View
    {
        $records = BankProcess::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch', 'bank', 'bankUnit'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('submitted_at')->paginate(20)->withQueryString();

        return view('sales-process.bank.index', [...$this->formData(), 'records' => $records]);
    }

    public function bankStore(BankProcessRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);
        $this->ensureBankUnit((int) $data['bank_id'], (int) $data['bank_unit_id']);

        $record = DB::transaction(function () use ($data, $salesCase): BankProcess {
            $attempt = ((int) $salesCase->bankProcesses()->max('attempt_number')) + 1;
            $status = $this->bankStatus($data['result'] ?? null, $data['status']);
            $record = $salesCase->bankProcesses()->create([
                ...$this->withoutKey($data, 'sales_case_id'),
                'attempt_number' => $attempt,
                'status' => $status,
                'started_at' => $data['submitted_at'],
                'sp3k_number' => $data['sp3k_number'] ?? ($status === 'approved' ? 'SP3K-'.$salesCase->id.'-'.$attempt : null),
            ]);
            $this->syncProcessStage($salesCase, 'proses_bank');

            return $record;
        });

        return redirect()->route('sales-process.bank')->with('status', 'bank-created')->with('record', $record->sp3k_number);
    }

    public function bankUpdate(BankProcessRequest $request, BankProcess $bankProcess): RedirectResponse
    {
        $this->ensureAccess($bankProcess->salesCase);
        $data = $request->validated();
        $this->ensureBankUnit((int) $data['bank_id'], (int) $data['bank_unit_id']);
        $status = $this->bankStatus($data['result'] ?? null, $data['status']);
        $bankProcess->update([...$this->withoutKey($data, 'sales_case_id'), 'status' => $status]);
        $this->syncProcessStage($bankProcess->salesCase, 'proses_bank');

        return back()->with('status', 'bank-updated');
    }

    public function ppjbIndex(): View
    {
        $records = PpjbDeveloperCase::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('started_at')->paginate(20)->withQueryString();

        return view('sales-process.ppjb.index', [...$this->formData(), 'records' => $records]);
    }

    public function ppjbStore(PpjbDeveloperCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);
        $record = DB::transaction(function () use ($data, $salesCase): PpjbDeveloperCase {
            $record = $salesCase->ppjbDeveloper()->create([
                ...$this->withoutKey($data, 'sales_case_id'),
                'ppjb_number' => $this->nextIdentifier('PPJB', $salesCase, PpjbDeveloperCase::class, 'ppjb_number'),
                'deadline_at' => $this->deadline('ppjb_dev', $data['started_at']),
            ]);
            $this->syncProcessStage($salesCase, 'ppjb');

            return $record;
        });

        return redirect()->route('sales-process.ppjb-developer')->with('status', 'ppjb-created')->with('record', $record->ppjb_number);
    }

    public function ppjbUpdate(PpjbDeveloperCaseRequest $request, PpjbDeveloperCase $ppjbDeveloperCase): RedirectResponse
    {
        $this->ensureAccess($ppjbDeveloperCase->salesCase);
        $data = $request->validated();
        $ppjbDeveloperCase->update([...$this->withoutKey($data, 'sales_case_id'), 'deadline_at' => $this->deadline('ppjb_dev', $data['started_at'])]);
        $this->syncProcessStage($ppjbDeveloperCase->salesCase, 'ppjb');

        return back()->with('status', 'ppjb-updated');
    }

    public function akadIndex(): View
    {
        $records = AkadCase::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('started_at')->paginate(20)->withQueryString();
        $bastRecords = BastCase::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('started_at')->get();

        return view('sales-process.akad.index', [...$this->formData(), 'records' => $records, 'bastRecords' => $bastRecords]);
    }

    public function akadStore(AkadCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);
        $record = DB::transaction(function () use ($data, $salesCase): AkadCase {
            $record = $salesCase->akad()->create([
                ...$this->withoutKey($data, 'sales_case_id'),
                'akad_number' => $this->nextIdentifier('AKD', $salesCase, AkadCase::class, 'akad_number'),
                'deadline_at' => $this->deadline('akad', $data['started_at']),
            ]);
            $this->syncProcessStage($salesCase, 'akad');

            return $record;
        });

        return redirect()->route('sales-process.akad-bast')->with('status', 'akad-created')->with('record', $record->akad_number);
    }

    public function akadUpdate(AkadCaseRequest $request, AkadCase $akadCase): RedirectResponse
    {
        $this->ensureAccess($akadCase->salesCase);
        $data = $request->validated();
        $akadCase->update([...$this->withoutKey($data, 'sales_case_id'), 'deadline_at' => $this->deadline('akad', $data['started_at'])]);
        $this->syncProcessStage($akadCase->salesCase, 'akad');

        return back()->with('status', 'akad-updated');
    }

    public function bastIndex(): View
    {
        $records = BastCase::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('started_at')->paginate(20)->withQueryString();

        return view('sales-process.bast.index', [...$this->formData(), 'records' => $records]);
    }

    public function bastStore(BastCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);
        $record = DB::transaction(function () use ($data, $salesCase): BastCase {
            $record = $salesCase->bast()->create([
                ...$this->withoutKey($data, 'sales_case_id'),
                'bast_number' => $this->nextIdentifier('BAST', $salesCase, BastCase::class, 'bast_number'),
                'deadline_at' => $this->deadline('bast', $data['started_at']),
            ]);
            $this->syncProcessStage($salesCase, 'bast');

            return $record;
        });

        return redirect()->route('sales-process.akad-bast')->with('status', 'bast-created')->with('record', $record->bast_number);
    }

    public function bastUpdate(BastCaseRequest $request, BastCase $bastCase): RedirectResponse
    {
        $this->ensureAccess($bastCase->salesCase);
        $data = $request->validated();
        $bastCase->update([...$this->withoutKey($data, 'sales_case_id'), 'deadline_at' => $this->deadline('bast', $data['started_at'])]);
        $this->syncProcessStage($bastCase->salesCase, 'bast');

        return back()->with('status', 'bast-updated');
    }

    public function warrantyIndex(): View
    {
        $records = Warranty::query()->with(['salesCase.consumer', 'salesCase.lot.project.branch', 'assignee'])
            ->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))
            ->latest('reported_at')->paginate(20)->withQueryString();

        return view('warranties.index', [...$this->formData(), 'records' => $records]);
    }

    public function warrantyStore(WarrantyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesCase = $this->salesCaseForUser((int) $data['sales_case_id']);
        $record = $salesCase->warranty()->create([
            ...$this->withoutKey($data, 'sales_case_id'),
            'warranty_number' => $this->nextIdentifier('GAR', $salesCase, Warranty::class, 'warranty_number'),
            'consumer_id' => $data['consumer_id'] ?? $salesCase->consumer_id,
            'lot_id' => $data['lot_id'] ?? $salesCase->lot_id,
        ]);

        return redirect()->route('warranties.index')->with('status', 'warranty-created')->with('record', $record->warranty_number);
    }

    public function warrantyUpdate(WarrantyRequest $request, Warranty $warranty): RedirectResponse
    {
        $this->ensureAccess($warranty->salesCase);
        $data = $request->validated();
        $warranty->update($this->withoutKey($data, 'sales_case_id'));

        return back()->with('status', 'warranty-updated');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $user = request()->user();

        return [
            'salesCases' => $this->salesCasesForUser($user),
            'filings' => SalesCaseFiling::query()->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))->latest('started_at')->get(),
            'bankProcesses' => BankProcess::query()->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))->latest('submitted_at')->get(),
            'ppjbs' => PpjbDeveloperCase::query()->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))->latest('started_at')->get(),
            'akads' => AkadCase::query()->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))->latest('started_at')->get(),
            'basts' => BastCase::query()->whereHas('salesCase', fn (Builder $query) => $this->scopeSalesCases($query))->latest('started_at')->get(),
            'banks' => Bank::query()->with(['units' => fn ($query) => $query->where('is_active', true)->orderBy('name')])->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->get(),
        ];
    }

    private function salesCaseForUser(int $id): SalesCase
    {
        $salesCase = SalesCase::query()->active()->with(['consumer', 'lot.project.branch'])->findOrFail($id);
        $this->ensureAccess($salesCase);

        return $salesCase;
    }

    private function ensureAccess(SalesCase $salesCase): void
    {
        abort_unless(
            request()->user()->can('sales-process.view')
                && (request()->user()->canAccessAllBranches() || $salesCase->lot->project->branch_id === request()->user()->branch_id),
            403,
        );
    }

    private function scopeSalesCases(Builder $query): Builder
    {
        $user = request()->user();

        return $query->active()->when(! $user->canAccessAllBranches(), fn (Builder $query) => $query->whereHas(
            'lot.project', fn (Builder $project) => $project->where('branch_id', $user->branch_id),
        ));
    }

    private function salesCasesForUser(User $user)
    {
        return $this->scopeSalesCases(SalesCase::query())->with(['consumer', 'lot.project.branch'])->latest('created_at')->get();
    }

    private function ensureBankUnit(int $bankId, int $unitId): void
    {
        abort_unless(BankUnit::query()->whereKey($unitId)->where('bank_id', $bankId)->where('is_active', true)->exists(), 422);
    }

    private function bankStatus(?string $result, string $fallback): string
    {
        return match ($result) {
            'APPROVE' => 'approved',
            'REJECT' => 'reject',
            'REVISI' => 'revisi',
            default => $fallback,
        };
    }

    private function deadline(string $process, string $startedAt): ?string
    {
        $days = LeadTimeTarget::query()->where('process', $process)->where('is_active', true)->value('target_days');

        return $days === null ? null : Carbon::parse($startedAt)->addDays((int) $days)->toDateString();
    }

    private function syncProcessStage(SalesCase $salesCase, string $stage): void
    {
        $salesCase->psjbUtj()->update(['process_stage' => $stage]);
    }

    /** @param array<string, mixed> $data */
    private function withoutKey(array $data, string $key): array
    {
        unset($data[$key]);

        return $data;
    }

    /** @param class-string<Model> $model */
    private function nextIdentifier(string $prefix, SalesCase $salesCase, string $model, string $column): string
    {
        $base = $prefix.'-'.$salesCase->getKey();
        $number = 1;

        do {
            $identifier = $base.'-'.$number;
            $number++;
        } while ($model::query()->where($column, $identifier)->exists());

        return $identifier;
    }
}
