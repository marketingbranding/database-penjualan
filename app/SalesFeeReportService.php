<?php

namespace App;

use App\Models\Lead;
use App\Models\Project;
use App\Models\SalesAgenda;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SalesFeeReportService
{
    /**
     * @param  array{branch_id?: int|null, date_from: string, date_to: string, project_id?: int|null, coordinator_id?: int|null, sales_pic_id?: int|null}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, int>, leads: Collection<int, Lead>, agendas: Collection<int, SalesAgenda>}
     */
    public function build(User $viewer, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['date_from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['date_to'])->endOfDay();

        $leads = Lead::query()
            ->with(['project', 'salesPic', 'team.coordinator', 'activities'])
            ->whereBetween('tanggal_lead', [$from->toDateString(), $to->toDateString()])
            ->when($filters['branch_id'], fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['sales_pic_id'], fn ($query, int $salesPicId) => $query->where('sales_pic_id', $salesPicId))
            ->when($filters['coordinator_id'], fn ($query, int $coordinatorId) => $query->whereHas('team', fn ($team) => $team->where('coordinator_id', $coordinatorId)))
            ->when(! $viewer->canAccessAllBranches() && $viewer->can('leads.view-team'), fn ($query) => $query->whereIn('team_id', $viewer->teams()->pluck('teams.id')->all()))
            ->get()
            ->filter(fn (Lead $lead): bool => $this->matchesProject($lead->project, $filters['project_id'] ?? null))
            ->values();

        $agendas = SalesAgenda::query()
            ->with(['owner', 'creator', 'team.coordinator', 'lead.project', 'salesCase.lot.project'])
            ->whereDate('agenda_date', '>=', $from->toDateString())
            ->whereDate('agenda_date', '<=', $to->toDateString())
            ->when($filters['branch_id'], fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['sales_pic_id'], fn ($query, int $salesPicId) => $query->where('owner_id', $salesPicId))
            ->when($filters['coordinator_id'], fn ($query, int $coordinatorId) => $query->whereHas('team', fn ($team) => $team->where('coordinator_id', $coordinatorId)))
            ->when(! $viewer->canAccessAllBranches() && $viewer->can('sales.view-team'), fn ($query) => $query->whereIn('team_id', $viewer->teams()->pluck('teams.id')->all()))
            ->when(! $viewer->canAccessAllBranches() && ! $viewer->can('sales.view-team') && ! $viewer->can('sales.view-all'), fn ($query) => $query->where('owner_id', $viewer->getKey()))
            ->get()
            ->filter(fn (SalesAgenda $agenda): bool => $this->matchesProject($this->projectForAgenda($agenda), $filters['project_id'] ?? null))
            ->values();

        $rows = [];

        foreach ($leads as $lead) {
            $project = $lead->project;
            $key = $this->rowKey($lead->sales_pic_id, $project?->getKey());
            $row = $rows[$key] ?? $this->newRow($lead->salesPic, $lead->team?->coordinator?->name, $project);
            $row['total_lead']++;
            $row['tatap_muka'] += $this->activityStatusCount($lead, 'Tatap Muka', $from, $to);
            $row['cek_lokasi'] += $this->activityStatusCount($lead, 'Cek Lokasi', $from, $to);
            $row['utj'] += $this->activityStatusCount($lead, 'UTJ', $from, $to);
            $rows[$key] = $row;
        }

        foreach ($agendas as $agenda) {
            $project = $this->projectForAgenda($agenda);
            $key = $this->rowKey($agenda->owner?->getKey(), $project?->getKey());
            $row = $rows[$key] ?? $this->newRow($agenda->owner, $agenda->team?->coordinator?->name, $project);
            $row['total_agenda']++;
            $row['agenda_selesai'] += $agenda->status === 'done' ? 1 : 0;
            $rows[$key] = $row;
        }

        $rows = collect($rows)->sortBy(fn (array $row): string => $row['sales_name'].'-'.$row['project_name'])->values();

        return [
            'rows' => $rows,
            'totals' => [
                'total_agenda' => $rows->sum('total_agenda'),
                'agenda_selesai' => $rows->sum('agenda_selesai'),
                'total_lead' => $rows->sum('total_lead'),
                'tatap_muka' => $rows->sum('tatap_muka'),
                'cek_lokasi' => $rows->sum('cek_lokasi'),
                'utj' => $rows->sum('utj'),
            ],
            'leads' => $leads,
            'agendas' => $agendas,
        ];
    }

    private function matchesProject(?Project $project, ?int $projectId): bool
    {
        return $projectId === null || $project?->getKey() === $projectId;
    }

    private function projectForAgenda(SalesAgenda $agenda): ?Project
    {
        return $agenda->lead?->project ?? $agenda->salesCase?->lot?->project;
    }

    private function rowKey(?int $salesId, ?int $projectId): string
    {
        return ($salesId ?? 0).'|'.($projectId ?? 0);
    }

    /** @return array<string, mixed> */
    private function newRow(?User $sales, ?string $coordinatorName, ?Project $project): array
    {
        return [
            'sales_id' => $sales?->getKey(),
            'sales_name' => $sales?->name ?? '-',
            'coordinator_name' => $coordinatorName ?? '-',
            'project_id' => $project?->getKey(),
            'project_name' => $project?->name ?? '-',
            'total_agenda' => 0,
            'agenda_selesai' => 0,
            'total_lead' => 0,
            'tatap_muka' => 0,
            'cek_lokasi' => 0,
            'utj' => 0,
        ];
    }

    private function activityStatusCount(Lead $lead, string $status, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $lead->activities->filter(fn ($activity): bool => $activity->status_to === $status
            && $activity->happened_at->betweenIncluded($from, $to))->count();
    }
}
