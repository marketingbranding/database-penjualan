<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesCaseChangeRequest;
use App\LotStatus;
use App\Models\Consumer;
use App\Models\Lot;
use App\Models\PsjbUtjCase;
use App\Models\SalesCase;
use App\Models\SalesCaseChangeRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCaseChangeController extends Controller
{
    public function create(PsjbUtjCase $psjbUtj): View
    {
        $psjbUtj->load(['salesCase.consumer', 'salesCase.lot.project.branch', 'salesCase.changeRequests']);
        $this->ensureAccess($psjbUtj->salesCase, request()->user());

        if ($psjbUtj->process_stage === 'akad_bast') {
            abort(422, 'Perubahan tidak dapat dilakukan setelah Akad/BAST.');
        }

        $lots = Lot::query()
            ->with(['project.branch', 'block'])
            ->where('status', LotStatus::Available)
            ->whereHas('project', fn ($project) => $project->where('branch_id', $psjbUtj->salesCase->lot->project->branch_id))
            ->whereKeyNot($psjbUtj->salesCase->lot_id)
            ->orderBy('project_id')
            ->orderBy('project_block_id')
            ->orderBy('number')
            ->get();

        return view('sales-process.psjb-utj.change', compact('psjbUtj', 'lots'));
    }

    public function store(StoreSalesCaseChangeRequest $request, PsjbUtjCase $psjbUtj): RedirectResponse
    {
        $psjbUtj->load(['salesCase.consumer', 'salesCase.lot.project.branch']);
        $salesCase = $psjbUtj->salesCase;
        $this->ensureAccess($salesCase, $request->user());

        if ($psjbUtj->process_stage === 'akad_bast') {
            throw ValidationException::withMessages(['process_stage' => 'Perubahan tidak dapat dilakukan setelah Akad/BAST.']);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $psjbUtj, $salesCase): void {
            $salesCase->load(['consumer', 'lot.project']);
            $oldConsumerId = $salesCase->consumer_id;
            $oldLotId = $salesCase->lot_id;
            $newLotId = null;
            $newConsumerId = null;

            if ($data['change_type'] === 'pindah_kavling') {
                $newLot = Lot::query()->with('project')->lockForUpdate()->findOrFail($data['new_lot_id']);

                if ($newLot->project->branch_id !== $salesCase->lot->project->branch_id) {
                    throw ValidationException::withMessages(['new_lot_id' => 'Kavling baru harus berada di cabang yang sama.']);
                }

                if ($newLot->status !== LotStatus::Available) {
                    throw ValidationException::withMessages(['new_lot_id' => 'Kavling baru sudah tidak tersedia.']);
                }

                $oldLotId = $salesCase->lot_id;
                $salesCase->lot->statusChangeSource = 'lot_transferred';
                $salesCase->lot->statusChangeReason = $data['reason'];
                $salesCase->lot->update(['status' => LotStatus::Available]);
                $newLot->statusChangeSource = 'lot_transferred';
                $newLot->statusChangeReason = $data['reason'];
                $newLot->update(['status' => LotStatus::Reserved]);
                $salesCase->update(['lot_id' => $newLot->id]);
                $newLotId = $newLot->id;
            } else {
                $consumer = Consumer::query()->create([
                    'no_ktp' => $data['new_no_ktp'],
                    'nama_konsumen' => $data['new_nama_konsumen'],
                    'tanggal_lahir' => $data['new_tanggal_lahir'],
                    'pekerjaan' => $data['new_pekerjaan'],
                    'detail_pekerjaan' => $data['new_detail_pekerjaan'] ?? null,
                    'alamat' => $data['new_alamat'],
                    'kelurahan' => $data['new_kelurahan'],
                    'kecamatan' => $data['new_kecamatan'],
                    'kabupaten_kota' => $data['new_kabupaten_kota'],
                    'no_hp' => $data['new_no_hp'],
                    'nama_kontak_darurat' => $data['new_nama_kontak_darurat'],
                    'no_hp_kontak_darurat' => $data['new_no_hp_kontak_darurat'],
                ]);
                $salesCase->update(['consumer_id' => $consumer->id]);
                $newConsumerId = $consumer->id;
            }

            $restartStage = $data['change_type'] === 'pindah_kavling'
                ? ($data['sp3k_issued'] ? 'proses_bank' : null)
                : $data['restart_stage'];

            $psjbUtj->update([
                'process_stage' => $restartStage ?? $data['process_stage'],
                'sp3k_issued' => false,
            ]);

            SalesCaseChangeRequest::query()->create([
                'sales_case_id' => $salesCase->id,
                'change_type' => $data['change_type'],
                'process_stage' => $data['process_stage'],
                'restart_stage' => $restartStage,
                'sp3k_issued' => (bool) $data['sp3k_issued'],
                'old_consumer_id' => $oldConsumerId,
                'new_consumer_id' => $newConsumerId,
                'old_lot_id' => $oldLotId,
                'new_lot_id' => $newLotId,
                'reason' => $data['reason'],
                'requested_by' => $request->user()->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('sales-process.psjb-utj.edit', $psjbUtj)->with('status', 'sales-case-changed');
    }

    private function ensureAccess(SalesCase $salesCase, User $user): void
    {
        abort_unless(
            $user->can('psjb-utj.update')
                && ($user->canAccessAllBranches() || $salesCase->lot->project->branch_id === $user->branch_id),
            403,
        );
    }
}
