<?php

namespace App\Services;

use App\LotStatus;
use App\Models\Lead;
use App\Models\Project;
use App\Models\SalesAgenda;
use App\Models\User;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PersonalSalesDashboardService
{
    public function __construct(private LeadMovementService $leadMovementService) {}

    public function isPersonalSales(User $user): bool
    {
        return $user->can('sales.view-own')
            && $user->can('leads.view-own')
            && ! $user->can('sales.view-team')
            && ! $user->can('sales.view-all')
            && ! $user->can('leads.view-team')
            && ! $user->can('leads.view-branch')
            && ! $user->can('leads.view-all');
    }

    /** @return array<string, mixed> */
    public function build(User $user): array
    {
        $user->loadMissing('branch');

        $now = BusinessTime::now();
        $today = $now->toDateString();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $lastSevenStart = $now->subDays(6)->toDateString();

        $ownLeads = Lead::query()->where('sales_pic_id', $user->getKey());
        $stageRows = (clone $ownLeads)
            ->select('status_lead')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status_lead')
            ->get()
            ->keyBy('status_lead');

        $actionMetrics = (clone $ownLeads)
            ->selectRaw('SUM(CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at < ? AND status_lead NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue_count', [$now->utc(), 'Akad', 'Tidak Lolos BI Checking'])
            ->selectRaw('SUM(CASE WHEN next_follow_up_at IS NOT NULL AND next_follow_up_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as today_count', [BusinessTime::utcStartOfDay($today), BusinessTime::utcEndOfDay($today)])
            ->selectRaw('SUM(CASE WHEN status_lead = ? AND tanggal_lead BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM lead_activities recent_lead_activity WHERE recent_lead_activity.lead_id = leads.id AND recent_lead_activity.activity_type = ?) AND NOT EXISTS (SELECT 1 FROM lead_activities responded_lead_activity WHERE responded_lead_activity.lead_id = leads.id AND responded_lead_activity.activity_type <> ?) THEN 1 ELSE 0 END) as new_unresponded_count', ['No Respon', $lastSevenStart, $today, 'Lead Baru', 'Lead Baru'])
            ->first();

        $desktopItems = [
            'overdue' => $this->leadItems((clone $ownLeads)->with('project')->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', $now->utc())->whereNotIn('status_lead', ['Akad', 'Tidak Lolos BI Checking'])->orderBy('next_follow_up_at')->limit(3)->get(), 'Follow-up terlambat'),
            'today' => $this->leadItems((clone $ownLeads)->with('project')->whereNotNull('next_follow_up_at')->whereBetween('next_follow_up_at', [BusinessTime::utcStartOfDay($today), BusinessTime::utcEndOfDay($today)])->orderBy('next_follow_up_at')->limit(3)->get(), 'Follow-up hari ini'),
            'new' => $this->leadItems((clone $ownLeads)->with('project')->where('status_lead', 'No Respon')->whereBetween('tanggal_lead', [$lastSevenStart, $today])->whereHas('activities', fn ($query) => $query->where('activity_type', 'Lead Baru'))->whereDoesntHave('activities', fn ($query) => $query->where('activity_type', '<>', 'Lead Baru'))->oldest('tanggal_lead')->oldest('id')->limit(3)->get(), 'Belum direspons'),
        ];

        $todayAgendas = SalesAgenda::query()
            ->where('owner_id', $user->getKey())
            ->whereDate('agenda_date', $today)
            ->whereIn('status', ['planned', 'done'])
            ->with(['lead.project', 'salesCase.consumer', 'salesCase.lot.project'])
            ->orderBy('agenda_date')
            ->orderBy('id')
            ->get();
        $nextAgenda = $todayAgendas->firstWhere('status', 'planned');

        if ($nextAgenda === null) {
            $nextAgenda = SalesAgenda::query()
                ->where('owner_id', $user->getKey())
                ->where('status', 'planned')
                ->whereDate('agenda_date', '>', $today)
                ->with(['lead.project', 'salesCase.consumer', 'salesCase.lot.project'])
                ->orderBy('agenda_date')
                ->orderBy('id')
                ->first();
        }

        $monthFilters = ['date_from' => $monthStart->toDateString(), 'date_to' => $monthEnd->toDateString(), 'branch_id' => $user->branch_id];
        $movementRows = $this->leadMovementService->rows($user, $monthFilters, $user->teams()->pluck('teams.id')->all());
        $projects = Project::query()
            ->where('is_active', true)
            ->whereHas('teams', fn ($query) => $query->where('is_active', true)->whereHas('users', fn ($users) => $users->whereKey($user->getKey())))
            ->with('lots')
            ->orderBy('name')
            ->get();

        return [
            'personalSalesDashboard' => true,
            'header' => ['date' => $this->formatFullDate($now), 'branch' => $user->branch?->name ?? 'Cabang belum diatur', 'greeting' => $this->greeting($now).', '.str($user->name)->before(' ')],
            'nextAgenda' => $nextAgenda ? $this->agendaCard($nextAgenda, $todayAgendas->where('status', 'planned')->values(), $today) : null,
            'todayAgendas' => $todayAgendas->map(fn (SalesAgenda $agenda): array => $this->agendaCard($agenda, collect(), $today))->all(),
            'actions' => [
                'overdue' => (int) ($actionMetrics->overdue_count ?? 0),
                'today' => (int) ($actionMetrics->today_count ?? 0),
                'new' => (int) ($actionMetrics->new_unresponded_count ?? 0),
                'items' => $desktopItems,
                'urls' => [
                    'overdue' => route('leads.index', ['follow_up' => 'overdue']),
                    'today' => route('leads.index', ['follow_up' => 'today']),
                    'new' => route('leads.index', ['status_lead' => 'No Respon', 'date_from' => $lastSevenStart, 'date_to' => $today]),
                ],
            ],
            'month' => ['activeLeads' => $this->activeLeadCount($stageRows), 'utjMovement' => (int) $movementRows->where('status_to', 'UTJ')->sum('total'), 'akadMovement' => (int) $movementRows->where('status_to', 'Akad')->sum('total'), 'stages' => $this->stageCounts($stageRows), 'label' => $this->formatMonth($now)],
            'stock' => $this->stockByProject($projects),
        ];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : '62'.$digits;
    }

    /** @return array<int, array<string, mixed>> */
    private function leadItems(Collection $leads, string $reason): array
    {
        return $leads->map(fn (Lead $lead): array => ['lead' => $lead, 'reason' => $reason])->all();
    }

    /** @param Collection<int, object> $stageRows */
    private function activeLeadCount(Collection $stageRows): int
    {
        return (int) $stageRows->reject(fn ($row): bool => in_array($row->status_lead, ['Akad', 'Tidak Lolos BI Checking'], true))->sum('total');
    }

    /** @param Collection<int, object> $stageRows */
    private function stageCounts(Collection $stageRows): array
    {
        return collect(Lead::STATUSES)->mapWithKeys(fn (string $status): array => [$status => (int) ($stageRows[$status]->total ?? 0)])->all();
    }

    /** @return array<string, mixed> */
    private function agendaCard(SalesAgenda $agenda, Collection $todayPlanned, string $today): array
    {
        $agendaDate = CarbonImmutable::instance($agenda->agenda_date);
        $phone = $agenda->lead?->no_hp ?? $agenda->salesCase?->consumer?->no_hp;
        $normalizedPhone = self::normalizePhone($phone);
        $position = $agenda->status === 'planned' && $agendaDate->toDateString() === $today ? $todayPlanned->search(fn (SalesAgenda $planned): bool => $planned->is($agenda)) + 1 : null;

        return [
            'model' => $agenda,
            'dateLabel' => $this->relativeDate($agendaDate, BusinessTime::now()),
            'dateUrl' => route('sales-agendas.index', ['date_from' => $agendaDate->toDateString(), 'date_to' => $agendaDate->toDateString()]),
            'detailUrl' => route('sales-agendas.edit', $agenda),
            'consumer' => $agenda->lead?->nama_konsumen ?? $agenda->salesCase?->consumer?->nama_konsumen,
            'project' => $agenda->lead?->project?->name ?? $agenda->salesCase?->lot?->project?->name,
            'lot' => $agenda->salesCase?->lot?->code,
            'stage' => $agenda->lead?->status_lead,
            'position' => $position,
            'todayCount' => $agendaDate->toDateString() === $today ? $todayPlanned->count() : 0,
            'whatsappUrl' => $normalizedPhone ? 'https://wa.me/'.$normalizedPhone : null,
            'telUrl' => $normalizedPhone ? 'tel:+'.$normalizedPhone : null,
        ];
    }

    private function relativeDate(CarbonImmutable $date, CarbonImmutable $now): string
    {
        return match ($date->toDateString()) {
            $now->toDateString() => 'Hari ini',
            $now->addDay()->toDateString() => 'Besok',
            default => $date->locale('id')->translatedFormat('l, d M'),
        };
    }

    private function formatFullDate(CarbonImmutable $date): string
    {
        return $date->locale('id')->translatedFormat('l, d F Y');
    }

    private function formatMonth(CarbonImmutable $date): string
    {
        return $date->locale('id')->translatedFormat('F Y');
    }

    private function greeting(CarbonImmutable $date): string
    {
        return match (true) {
            $date->hour < 11 => 'Selamat pagi',
            $date->hour < 15 => 'Selamat siang',
            $date->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /** @param Collection<int, Project> $projects */
    private function stockByProject(Collection $projects): array
    {
        return $projects->map(function (Project $project): array {
            $counts = collect(LotStatus::cases())->mapWithKeys(fn (LotStatus $status): array => [$status->value => $project->lots->where('status', $status)->count()]);

            return ['project' => $project, 'available' => $counts[LotStatus::Available->value], 'reserved' => $counts[LotStatus::Reserved->value], 'sold' => $counts[LotStatus::Sold->value]];
        })->values()->all();
    }
}
