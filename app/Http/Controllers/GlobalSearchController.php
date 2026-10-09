<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim($request->string('q')->toString());
        $user = $request->user();
        $branchId = $user->canAccessAllBranches() ? null : $user->branch_id;

        $leads = collect();
        $agendas = collect();
        $consumers = collect();
        $salesCases = collect();

        if (mb_strlen($query) >= 2) {
            if ($user->can('leads.view-own') || $user->can('leads.view-team') || $user->can('leads.view-branch') || $user->can('leads.view-all') || $user->can('leads.monitor')) {
                $leads = Lead::query()
                    ->with('salesPic')
                    ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
                    ->when(! $user->can('leads.view-all') && ! $user->can('leads.view-branch') && ! $user->can('leads.monitor'), function ($builder) use ($user): void {
                        if ($user->can('leads.view-team')) {
                            $builder->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                            return;
                        }

                        $builder->where('sales_pic_id', $user->getKey());
                    })
                    ->where(function ($builder) use ($query): void {
                        $builder->where('id_lead', 'like', "%{$query}%")
                            ->orWhere('nama_konsumen', 'like', "%{$query}%")
                            ->orWhere('no_hp', 'like', "%{$query}%");
                    })
                    ->latest('tanggal_lead')
                    ->limit(8)
                    ->get();
            }

            if ($user->can('sales.view-own') || $user->can('sales.view-team') || $user->can('sales.view-all')) {
                $agendas = SalesAgenda::query()
                    ->with('lead')
                    ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
                    ->when(! $user->can('sales.view-all'), function ($builder) use ($user): void {
                        if ($user->can('sales.view-team')) {
                            $builder->whereIn('team_id', $user->teams()->pluck('teams.id')->all());

                            return;
                        }

                        $builder->where('created_by', $user->getKey());
                    })
                    ->where(function ($builder) use ($query): void {
                        $builder->where('title', 'like', "%{$query}%")
                            ->orWhere('location', 'like', "%{$query}%")
                            ->orWhere('activity_result', 'like', "%{$query}%");
                    })
                    ->latest('agenda_date')
                    ->limit(8)
                    ->get();
            }

            if ($user->can('consumers.view')) {
                $salesCases = SalesCase::query()
                    ->with(['consumer', 'lot.project'])
                    ->when($branchId, fn ($builder) => $builder->whereHas('lot.project', fn ($project) => $project->where('branch_id', $branchId)))
                    ->whereHas('consumer', function ($builder) use ($query): void {
                        $builder->where('nama_konsumen', 'like', "%{$query}%")
                            ->orWhere('no_hp', 'like', "%{$query}%");
                    })
                    ->latest()
                    ->limit(8)
                    ->get();
                $consumers = $salesCases->pluck('consumer')->unique('id')->values();
            }
        }

        return view('search.index', compact('query', 'leads', 'agendas', 'consumers', 'salesCases'));
    }
}
