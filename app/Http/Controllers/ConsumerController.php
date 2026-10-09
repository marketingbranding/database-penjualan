<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSalesCaseRequest;
use App\Http\Requests\StoreConsumerRequest;
use App\Http\Requests\UpdateConsumerRequest;
use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lot;
use App\Models\Project;
use App\Models\Promotion;
use App\Models\SalesCase;
use App\Models\User;
use App\Notifications\ConsumerCreatedNotification;
use App\Notifications\LotStatusChangedNotification;
use App\Notifications\ReservationCancelledNotification;
use App\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsumerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', SalesCase::class);

        $user = request()->user();
        $branchId = $user->canAccessAllBranches()
            ? (request()->integer('branch_id') ?: null)
            : $user->branch_id;
        $projectId = request()->integer('project_id') ?: null;
        $paymentMethod = PaymentMethod::tryFrom((string) request('payment_method'));
        $search = trim((string) request('search'));

        $projects = Project::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();

        if ($projectId && ! $projects->contains('id', $projectId)) {
            $projectId = null;
        }

        $salesCases = SalesCase::query()
            ->active()
            ->with(['consumer', 'lot.block', 'lot.project.branch', 'promotion'])
            ->when($branchId, fn ($query) => $query->whereHas('lot.project', fn ($project) => $project->where('branch_id', $branchId)))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($projectId, fn ($query) => $query->whereHas('lot', fn ($lot) => $lot->where('project_id', $projectId)))
            ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod->value))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereHas('consumer', fn ($consumer) => $consumer
                    ->where('nama_konsumen', 'like', "%{$search}%")
                    ->orWhere('no_ktp', 'like', "%{$search}%"))
                    ->orWhereHas('lot', fn ($lot) => $lot->where('code', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('consumers.index', [
            'salesCases' => $salesCases,
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'projects' => $projects,
            'branchId' => $branchId,
            'projectId' => $projectId,
            'paymentMethod' => $paymentMethod,
            'search' => $search,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->authorize('create', SalesCase::class);

        return view('consumers.create', $this->formData(request()->user()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConsumerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $salesCase = DB::transaction(function () use ($data, $request): SalesCase {
            $lot = Lot::query()->with('project')->lockForUpdate()->findOrFail($data['lot_id']);
            $this->ensureLotBelongsToBranch($request->user(), $lot, $data['branch_id'] ?? null);

            if ($lot->status !== LotStatus::Available) {
                throw ValidationException::withMessages(['lot_id' => 'Kavling sudah tidak tersedia.']);
            }

            $this->ensurePromotionApplies($data['promotion_id'] ?? null, $lot);

            $consumer = Consumer::query()->where('no_ktp', $data['no_ktp'])->lockForUpdate()->first();
            $method = PaymentMethod::from($data['metode_pembayaran']);

            if ($consumer) {
                $this->ensurePaymentMethodAllowed($consumer, $method);
                $consumer->update($this->consumerData($data));
            } else {
                $consumer = Consumer::query()->create($this->consumerData($data));
            }

            $salesCase = $consumer->salesCases()->create([
                'lot_id' => $lot->getKey(),
                'payment_method' => $method,
                'promotion_id' => $data['promotion_id'] ?? null,
                'notes' => $data['keterangan'] ?? null,
                'created_by' => $request->user()->getKey(),
            ]);

            $lot->statusChangeSource = 'consumer_created';
            $lot->update(['status' => LotStatus::Reserved]);

            return $salesCase;
        });

        $this->notifyBranchUsers($salesCase->load(['consumer', 'lot.project']));

        return redirect()->route('consumers.edit', $salesCase)->with('status', 'consumer-created');
    }

    public function show(SalesCase $salesCase): View
    {
        $this->authorize('view', $salesCase);
        $salesCase->load([
            'consumer', 'lot.block', 'lot.project.branch', 'creator', 'cancelledBy', 'promotion',
            'psjbUtj.coordinator', 'psjbUtj.sales', 'filings.bank', 'filings.bankUnit',
            'bankProcesses.bank', 'bankProcesses.bankUnit', 'ppjbDeveloper.bankProcess',
            'akad.ppjb', 'bast.akad', 'warranty.assignee', 'statusHistories.user',
        ]);

        $relatedSalesCases = $salesCase->consumer->salesCases()
            ->with(['lot.project.branch'])
            ->when(! request()->user()->canAccessAllBranches(), fn ($query) => $query
                ->whereHas('lot.project', fn ($project) => $project->where('branch_id', request()->user()->branch_id)))
            ->latest()
            ->get();

        $timeline = collect([
            ['label' => 'PSJB / UTJ', 'status' => $salesCase->psjbUtj?->consumer_status ?? 'Belum ada', 'date' => $salesCase->psjbUtj?->psjb_date, 'route' => 'sales-process.psjb-utj'],
            ['label' => 'Pemberkasan', 'status' => $salesCase->filings->first()?->status ?? 'Belum ada', 'date' => $salesCase->filings->first()?->started_at, 'route' => 'sales-process.pemberkasan'],
            ['label' => 'Proses Bank', 'status' => $salesCase->bankProcesses->first()?->status ?? 'Belum ada', 'date' => $salesCase->bankProcesses->first()?->submitted_at, 'route' => 'sales-process.bank'],
            ['label' => 'PPJB Developer', 'status' => $salesCase->ppjbDeveloper?->status ?? 'Belum ada', 'date' => $salesCase->ppjbDeveloper?->started_at, 'route' => 'sales-process.ppjb-developer'],
            ['label' => 'Akad', 'status' => $salesCase->akad?->status ?? 'Belum ada', 'date' => $salesCase->akad?->started_at, 'route' => 'sales-process.akad-bast'],
            ['label' => 'BAST', 'status' => $salesCase->bast?->status ?? 'Belum ada', 'date' => $salesCase->bast?->started_at, 'route' => 'sales-process.akad-bast'],
            ['label' => 'Garansi', 'status' => $salesCase->warranty?->status ?? 'Belum ada', 'date' => $salesCase->warranty?->reported_at, 'route' => 'warranties.index'],
        ]);

        return view('consumers.show', compact('salesCase', 'relatedSalesCases', 'timeline'));
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', SalesCase::class);
        abort_unless(request()->user()->can('consumers.export'), 403);

        $user = request()->user();
        $branchId = $user->canAccessAllBranches()
            ? (request()->integer('branch_id') ?: null)
            : $user->branch_id;
        $projectId = request()->integer('project_id') ?: null;
        $paymentMethod = PaymentMethod::tryFrom((string) request('payment_method'));
        $search = trim((string) request('search'));

        $salesCases = SalesCase::query()
            ->active()
            ->with(['consumer', 'lot.project.branch', 'promotion'])
            ->when($branchId, fn ($query) => $query->whereHas('lot.project', fn ($project) => $project->where('branch_id', $branchId)))
            ->when(! $user->canAccessAllBranches() && ! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($projectId, fn ($query) => $query->whereHas('lot', fn ($lot) => $lot->where('project_id', $projectId)))
            ->when($paymentMethod, fn ($query) => $query->where('payment_method', $paymentMethod->value))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->whereHas('consumer', fn ($consumer) => $consumer
                    ->where('nama_konsumen', 'like', "%{$search}%")
                    ->orWhere('no_ktp', 'like', "%{$search}%"))
                    ->orWhereHas('lot', fn ($lot) => $lot->where('code', 'like', "%{$search}%"));
            }))
            ->orderBy('id')
            ->get();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'id_kavling', 'no_ktp', 'nama_konsumen', 'tanggal_lahir', 'pekerjaan',
            'detail_pekerjaan', 'umur', 'alamat', 'kelurahan', 'kecamatan',
            'kabupaten_kota', 'no_hp', 'nama_kontak_darurat', 'no_hp_kontak_darurat',
            'metode_pembayaran', 'promo', 'keterangan',
        ], null, 'A1');

        foreach ($salesCases as $index => $case) {
            $sheet->fromArray([
                $case->lot->project->name.'-'.$case->lot->code,
                $case->consumer->no_ktp,
                $case->consumer->nama_konsumen,
                $case->consumer->tanggal_lahir->format('d M Y'),
                $case->consumer->pekerjaan,
                $case->consumer->detail_pekerjaan,
                $case->consumer->age.' tahun',
                $case->consumer->alamat,
                $case->consumer->kelurahan,
                $case->consumer->kecamatan,
                $case->consumer->kabupaten_kota,
                $case->consumer->no_hp,
                $case->consumer->nama_kontak_darurat,
                $case->consumer->no_hp_kontak_darurat,
                $case->payment_method->value,
                $case->promotion ? $case->promotion->promo_id.' '.$case->promotion->name : null,
                $case->notes,
            ], null, 'A'.($index + 2));
        }

        foreach (range('A', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'data-konsumen-'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Display the specified resource.
     */
    public function edit(SalesCase $salesCase): View
    {
        $this->authorize('update', $salesCase);

        return view('consumers.edit', [
            ...$this->formData(request()->user(), $salesCase),
            'salesCase' => $salesCase->load(['consumer', 'lot.project.branch']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConsumerRequest $request, SalesCase $salesCase): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $salesCase): void {
            $salesCase->load(['consumer', 'lot']);
            $method = PaymentMethod::from($data['metode_pembayaran']);
            $this->ensurePaymentMethodAllowed($salesCase->consumer, $method, $salesCase);

            $finalLotId = (int) $data['lot_id'];
            $newLot = null;

            if ($salesCase->lot_id !== $finalLotId) {
                if ($salesCase->lot->status === LotStatus::Sold) {
                    throw ValidationException::withMessages(['lot_id' => 'Kavling yang sudah terjual tidak dapat diganti.']);
                }

                $newLot = Lot::query()->with('project')->lockForUpdate()->findOrFail($finalLotId);
                $this->ensureLotBelongsToBranch($request->user(), $newLot, $data['branch_id'] ?? null);

                if ($newLot->status !== LotStatus::Available) {
                    throw ValidationException::withMessages(['lot_id' => 'Kavling sudah tidak tersedia.']);
                }

                if ($salesCase->lot->status === LotStatus::Reserved) {
                    $salesCase->lot->statusChangeSource = 'consumer_lot_changed';
                    $salesCase->lot->update(['status' => LotStatus::Available]);
                }

                $newLot->statusChangeSource = 'consumer_lot_changed';
                $newLot->update(['status' => LotStatus::Reserved]);
            }

            $promotionId = $data['promotion_id'] ?? null;

            if ($promotionId !== null && $promotionId !== (int) $salesCase->promotion_id) {
                $promoLot = $newLot ?? $salesCase->lot;
                $promoLot->loadMissing('project');
                $this->ensurePromotionApplies($promotionId, $promoLot);
            }

            $salesCase->consumer->update($this->consumerData($data));
            $salesCase->update([
                'lot_id' => $finalLotId,
                'payment_method' => $method,
                'promotion_id' => $promotionId,
                'notes' => $data['keterangan'] ?? null,
            ]);
        });

        return back()->with('status', 'consumer-updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SalesCase $salesCase): RedirectResponse
    {
        $this->authorize('delete', $salesCase);

        DB::transaction(function () use ($salesCase): void {
            $salesCase->load(['consumer', 'lot']);

            if ($salesCase->lot->status === LotStatus::Reserved) {
                $salesCase->lot->statusChangeSource = 'sales_case_deleted';
                $salesCase->lot->update(['status' => LotStatus::Available]);
            }

            $consumer = $salesCase->consumer;
            $salesCase->delete();

            if (! $consumer->salesCases()->exists()) {
                $consumer->delete();
            }
        });

        return redirect()->route('consumers.index')->with('status', 'consumer-deleted');
    }

    public function cancel(CancelSalesCaseRequest $request, SalesCase $salesCase): RedirectResponse
    {
        DB::transaction(function () use ($request, $salesCase): void {
            $salesCase = SalesCase::query()
                ->with(['consumer', 'lot.project'])
                ->lockForUpdate()
                ->findOrFail($salesCase->getKey());

            if ($salesCase->isCancelled()) {
                throw ValidationException::withMessages(['reason' => 'Reservasi ini sudah dibatalkan.']);
            }

            if ($salesCase->lot->status !== LotStatus::Reserved) {
                throw ValidationException::withMessages([
                    'reason' => 'Hanya kavling berstatus Dipesan yang dapat dibatalkan.',
                ]);
            }

            $salesCase->update([
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->getKey(),
                'cancellation_reason' => $request->validated('reason'),
            ]);

            $salesCase->lot->statusChangeSource = 'reservation_cancelled';
            $salesCase->lot->statusChangeReason = $request->validated('reason');
            $salesCase->lot->update(['status' => LotStatus::Available]);

            User::query()
                ->where('branch_id', $salesCase->lot->project->branch_id)
                ->get()
                ->each(function (User $user) use ($salesCase): void {
                    $user->notify(new ReservationCancelledNotification($salesCase));
                    $user->notify(new LotStatusChangedNotification(
                        $salesCase->lot,
                        LotStatus::Reserved,
                        LotStatus::Available,
                    ));
                });
        });

        return redirect()->route('consumers.show', $salesCase)->with('status', 'reservation-cancelled');
    }

    /** @return array<string, mixed> */
    private function formData(User $user, ?SalesCase $salesCase = null): array
    {
        $projects = Project::query()
            ->with(['branch', 'lots' => fn ($query) => $query
                ->with('block')
                ->where(function ($query) use ($salesCase): void {
                    $query->where('status', LotStatus::Available->value);

                    if ($salesCase) {
                        $query->orWhereKey($salesCase->lot_id);
                    }
                })
                ->orderBy('project_block_id')
                ->orderBy('number')])
            ->where('is_active', true)
            ->when(! $user->canAccessAllBranches(), fn ($query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get();

        $promotions = Promotion::query()
            ->with('targets')
            ->where(function ($query) use ($salesCase): void {
                $query->active();

                if ($salesCase?->promotion_id) {
                    $query->orWhereKey($salesCase->promotion_id);
                }
            })
            ->orderBy('name')
            ->get();

        return [
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'projects' => $projects,
            'promotions' => $promotions,
            'paymentMethods' => PaymentMethod::cases(),
            'assignedBranch' => $user->branch,
        ];
    }

    /** @param array<string, mixed> $data */
    private function consumerData(array $data): array
    {
        return collect($data)->only([
            'no_ktp', 'nama_konsumen', 'tanggal_lahir', 'pekerjaan', 'detail_pekerjaan',
            'alamat', 'kelurahan', 'kecamatan', 'kabupaten_kota', 'no_hp',
            'nama_kontak_darurat', 'no_hp_kontak_darurat',
        ])->all();
    }

    private function ensurePaymentMethodAllowed(
        Consumer $consumer,
        PaymentMethod $method,
        ?SalesCase $except = null,
    ): void {
        $salesCases = $consumer->salesCases()
            ->active()
            ->when($except, fn ($query) => $query->whereKeyNot($except))
            ->get();

        if (($method === PaymentMethod::Kpr && $salesCases->isNotEmpty())
            || ($method === PaymentMethod::Cash && $salesCases->contains('payment_method', PaymentMethod::Kpr))) {
            throw ValidationException::withMessages([
                'metode_pembayaran' => 'Konsumen KPR hanya dapat memiliki satu kavling.',
            ]);
        }
    }

    private function ensureLotBelongsToBranch(User $user, Lot $lot, ?int $requestedBranchId): void
    {
        $branchId = $user->canAccessAllBranches() ? $requestedBranchId : $user->branch_id;

        if ($branchId === null || $lot->project->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'lot_id' => 'Kavling tidak berasal dari cabang admin.',
            ]);
        }
    }

    private function ensurePromotionApplies(?int $promotionId, Lot $lot): void
    {
        if ($promotionId === null) {
            return;
        }

        $promotion = Promotion::query()
            ->active()
            ->matching($lot->project->branch_id, $lot->project_id, $lot->project_block_id)
            ->find($promotionId);

        if (! $promotion) {
            throw ValidationException::withMessages([
                'promotion_id' => 'Promo tidak berlaku untuk kavling ini atau sudah tidak aktif.',
            ]);
        }
    }

    private function notifyBranchUsers(SalesCase $salesCase): void
    {
        User::query()
            ->where('branch_id', $salesCase->lot->project->branch_id)
            ->get()
            ->each(function (User $user) use ($salesCase): void {
                $user->notify(new ConsumerCreatedNotification($salesCase));
                $user->notify(new LotStatusChangedNotification(
                    $salesCase->lot,
                    LotStatus::Available,
                    LotStatus::Reserved,
                ));
            });
    }
}
