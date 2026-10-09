<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\StoreBankUnitRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Requests\UpdateBankUnitRequest;
use App\Models\Bank;
use App\Models\BankUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BankController extends Controller
{
    public function index(): View
    {
        return view('admin.banks.index', ['banks' => Bank::query()->with('units')->orderBy('name')->get()]);
    }

    public function store(StoreBankRequest $request): RedirectResponse
    {
        $bank = Bank::query()->create($request->validated());

        return back()->with('status', 'bank-created')->with('bank_id', $bank->id);
    }

    public function update(UpdateBankRequest $request, Bank $bank): RedirectResponse
    {
        $bank->update($request->validated());

        return back()->with('status', 'bank-updated');
    }

    public function destroy(Bank $bank): RedirectResponse
    {
        abort_unless(request()->user()->can('banks.delete'), 403);

        if ($bank->units()->exists()) {
            return back()->withErrors(['bank' => 'Bank yang memiliki KC/unit tidak dapat dihapus. Nonaktifkan bank jika sudah tidak digunakan.']);
        }

        $bank->delete();

        return back()->with('status', 'bank-deleted');
    }

    public function storeUnit(StoreBankUnitRequest $request, Bank $bank): RedirectResponse
    {
        if (! $bank->is_active) {
            throw ValidationException::withMessages(['bank' => 'Bank nonaktif tidak dapat menerima KC/unit baru.']);
        }

        $this->ensureUniqueUnit($bank, $request->validated('name'));
        $bank->units()->create($request->validated());

        return back()->with('status', 'bank-unit-created');
    }

    public function updateUnit(UpdateBankUnitRequest $request, BankUnit $bankUnit): RedirectResponse
    {
        $this->ensureUniqueUnit($bankUnit->bank, $request->validated('name'), $bankUnit);
        $bankUnit->update($request->validated());

        return back()->with('status', 'bank-unit-updated');
    }

    public function destroyUnit(BankUnit $bankUnit): RedirectResponse
    {
        abort_unless(request()->user()->can('banks.delete'), 403);
        $bankUnit->delete();

        return back()->with('status', 'bank-unit-deleted');
    }

    private function ensureUniqueUnit(Bank $bank, string $name, ?BankUnit $ignore = null): void
    {
        $query = $bank->units()->where('name', $name);

        if ($ignore) {
            $query->whereKeyNot($ignore->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'KC/unit tersebut sudah terdaftar pada bank ini.']);
        }
    }
}
