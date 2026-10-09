<?php

namespace App\Http\Controllers;

use App\Http\Requests\WithdrawSalesCaseRequest;
use App\LotStatus;
use App\Models\SalesCase;
use App\Models\SalesCaseStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCaseStatusController extends Controller
{
    public function withdraw(WithdrawSalesCaseRequest $request, SalesCase $salesCase): RedirectResponse
    {
        $salesCase->load(['lot.project', 'psjbUtj']);
        abort_unless(
            $request->user()->can('sales-process.update')
                && ($request->user()->canAccessAllBranches() || $salesCase->lot->project->branch_id === $request->user()->branch_id),
            403,
        );

        if ($salesCase->consumer_status === 'MUNDUR') {
            throw ValidationException::withMessages(['reason' => 'Konsumen sudah berstatus mundur.']);
        }

        DB::transaction(function () use ($request, $salesCase): void {
            $fromStatus = $salesCase->consumer_status;
            $stage = $salesCase->psjbUtj?->process_stage ?? 'psjb_utj';
            $salesCase->update([
                'consumer_status' => 'MUNDUR',
                'consumer_status_date' => today(),
                'consumer_status_reason' => $request->validated('reason'),
                'consumer_status_changed_by' => $request->user()->id,
            ]);

            SalesCaseStatusHistory::query()->create([
                'sales_case_id' => $salesCase->id,
                'from_status' => $fromStatus,
                'to_status' => 'MUNDUR',
                'reason' => $request->validated('reason'),
                'process_stage' => $stage,
                'user_id' => $request->user()->id,
            ]);

            if ($stage !== 'akad_bast' && $salesCase->lot->status === LotStatus::Reserved) {
                $salesCase->lot->statusChangeSource = 'consumer_withdrawn';
                $salesCase->lot->statusChangeReason = $request->validated('reason');
                $salesCase->lot->update(['status' => LotStatus::Available]);
            }
        });

        return back()->with('status', 'consumer-withdrawn');
    }
}
