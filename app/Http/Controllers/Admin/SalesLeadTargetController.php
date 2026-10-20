<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesLeadTargetRequest;
use App\Http\Requests\UpdateSalesLeadTargetRequest;
use App\Models\Role;
use App\Models\SalesLeadTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesLeadTargetController extends Controller
{
    public function index(Request $request): View
    {
        $year = min(2100, max(2020, $request->integer('period_year') ?: (int) now()->year));
        $month = min(12, max(1, $request->integer('period_month') ?: (int) now()->month));
        $salesUsers = $this->salesUsers();
        $targets = SalesLeadTarget::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('sales_id', $salesUsers->modelKeys())
            ->get()
            ->keyBy('sales_id');

        return view('admin.sales-lead-targets.index', [
            'salesUsers' => $salesUsers,
            'targets' => $targets,
            'year' => $year,
            'month' => $month,
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $value): array => [$value => CarbonImmutable::create(2026, $value, 1)->locale('id')->translatedFormat('F')]),
        ]);
    }

    public function store(StoreSalesLeadTargetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->ensureSalesUser((int) $data['sales_id']);
        $target = SalesLeadTarget::query()->firstOrNew([
            'sales_id' => $data['sales_id'],
            'period_year' => $data['period_year'],
            'period_month' => $data['period_month'],
        ]);
        $target->fill(['lead_target' => $data['lead_target']]);
        $target->created_by ??= $request->user()->getKey();
        $target->updated_by = $request->user()->getKey();
        $target->save();

        return back()->with('status', 'sales-lead-target-saved');
    }

    public function update(UpdateSalesLeadTargetRequest $request, SalesLeadTarget $salesLeadTarget): RedirectResponse
    {
        $salesLeadTarget->update([
            'lead_target' => $request->validated('lead_target'),
            'updated_by' => $request->user()->getKey(),
        ]);

        return back()->with('status', 'sales-lead-target-saved');
    }

    /** @return Collection<int, User> */
    private function salesUsers(): Collection
    {
        $salesRoleIds = Role::query()->where('name', 'sales')->whereNotNull('team_id')->pluck('id');

        return User::query()
            ->whereNotNull('branch_id')
            ->whereHas('teams', fn ($query) => $query->where('teams.is_active', true))
            ->whereIn('id', DB::table(config('permission.table_names.model_has_roles'))
                ->whereIn('role_id', $salesRoleIds)
                ->where('model_type', (new User)->getMorphClass())
                ->pluck('model_id'))
            ->with('branch')
            ->orderBy('name')
            ->get();
    }

    private function ensureSalesUser(int $userId): void
    {
        abort_unless($this->salesUsers()->contains('id', $userId), 422, 'Target hanya dapat diberikan kepada Sales aktif.');
    }
}
