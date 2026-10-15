<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\SalesFeeReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesFeeReportController extends Controller
{
    public function __construct(private readonly SalesFeeReportService $reportService) {}

    public function index(Request $request): View
    {
        $this->authorizeReport($request->user());
        $filters = $this->filters($request, $request->user());

        return view('sales-fee-reports.index', [
            ...$this->viewData($request->user(), $filters),
            'report' => $this->reportService->build($request->user(), $filters),
        ]);
    }

    public function show(Request $request, User $salesUser, ?Project $project = null): View
    {
        $this->authorizeReport($request->user());
        $filters = $this->filters($request, $request->user());
        $filters['sales_pic_id'] = $salesUser->getKey();
        $filters['project_id'] = $project?->getKey();

        return view('sales-fee-reports.show', [
            'report' => $this->reportService->build($request->user(), $filters),
            'filters' => $filters,
            'salesUser' => $salesUser,
            'project' => $project,
        ]);
    }

    public function print(Request $request): View
    {
        $this->authorizeReport($request->user());
        $filters = $this->filters($request, $request->user());

        abort_unless($request->user()->can('sales-fee-reports.print'), 403);

        return view('sales-fee-reports.print', [
            'report' => $this->reportService->build($request->user(), $filters),
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReport($request->user());
        abort_unless($request->user()->can('sales-fee-reports.export'), 403);

        $filters = $this->filters($request, $request->user());
        $report = $this->reportService->build($request->user(), $filters);
        $spreadsheet = new Spreadsheet;
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Rekap Sales');
        $summarySheet->fromArray([
            ['LAPORAN PERFORMA SALES'],
            ['Periode', $filters['date_from'], 'sampai', $filters['date_to']],
            [],
            ['Sales', 'Koordinator', 'Proyek', 'Total Agenda', 'Agenda Selesai', 'Total Lead', 'Tatap Muka', 'Cek Lokasi', 'UTJ'],
        ]);

        $row = 5;
        foreach ($report['rows'] as $item) {
            $summarySheet->fromArray([[
                $item['sales_name'], $item['coordinator_name'], $item['project_name'],
                $item['total_agenda'], $item['agenda_selesai'], $item['total_lead'],
                $item['tatap_muka'], $item['cek_lokasi'], $item['utj'],
            ]], null, 'A'.$row++);
        }

        $leadSheet = $spreadsheet->createSheet();
        $leadSheet->setTitle('Detail Lead');
        $leadSheet->fromArray([['Tanggal', 'ID Lead', 'Konsumen', 'No HP', 'Sales', 'Proyek', 'Sumber', 'Kanal', 'Aktivitas', 'Status', 'Keterangan']]);
        $row = 2;
        foreach ($report['leads'] as $lead) {
            $leadSheet->fromArray([[
                $lead->tanggal_lead->format('Y-m-d'), $lead->id_lead, $lead->nama_konsumen, $lead->no_hp,
                $lead->salesPic->name, $lead->project?->name ?? '-', $lead->sumber_lead, $lead->kanal_masuk,
                $lead->aktivitas_lead, $lead->status_lead, $lead->keterangan,
            ]], null, 'A'.$row++);
        }

        $agendaSheet = $spreadsheet->createSheet();
        $agendaSheet->setTitle('Detail Agenda');
        $agendaSheet->fromArray([['Tanggal', 'Sales Owner', 'Dibuat Oleh', 'Proyek', 'Kategori', 'Judul Agenda', 'Lokasi', 'Hasil', 'Status']]);
        $row = 2;
        foreach ($report['agendas'] as $agenda) {
            $agendaSheet->fromArray([[
                $agenda->agenda_date->format('Y-m-d'), $agenda->owner->name, $agenda->creator->name,
                $agenda->lead?->project?->name ?? $agenda->salesCase?->lot?->project?->name ?? '-',
                $agenda->activity_category, $agenda->title, $agenda->location,
                $agenda->activity_result, $agenda->status,
            ]], null, 'A'.$row++);
        }

        foreach ([$summarySheet, $leadSheet, $agendaSheet] as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'laporan-performa-sales-'.$filters['date_from'].'-sd-'.$filters['date_to'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** @return array{branch_id: int|null, date_from: string, date_to: string, project_id: int|null, coordinator_id: int|null, sales_pic_id: int|null} */
    private function filters(Request $request, User $user): array
    {
        return [
            'branch_id' => $user->canAccessAllBranches() ? ($request->integer('branch_id') ?: null) : $user->branch_id,
            'date_from' => $request->input('date_from', today()->startOfMonth()->toDateString()),
            'date_to' => $request->input('date_to', today()->toDateString()),
            'project_id' => $request->integer('project_id') ?: null,
            'coordinator_id' => $request->integer('coordinator_id') ?: null,
            'sales_pic_id' => $request->integer('sales_pic_id') ?: null,
        ];
    }

    /** @return array{branches: Collection, projects: Collection, coordinators: Collection, salesUsers: Collection, filters: array<string, int|string|null>} */
    private function viewData(User $user, array $filters): array
    {
        $branchId = $filters['branch_id'];

        return [
            'branches' => $user->canAccessAllBranches()
                ? Branch::query()->where('is_active', true)->orderBy('name')->get()
                : collect([$user->branch])->filter(),
            'projects' => Project::query()
                ->where('is_active', true)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'coordinators' => Team::query()
                ->where('is_active', true)
                ->whereNotNull('coordinator_id')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->with('coordinator')
                ->get()
                ->pluck('coordinator')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values(),
            'salesUsers' => User::query()
                ->whereNotNull('branch_id')
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->whereHas('leads')
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
        ];
    }

    private function authorizeReport(User $user): void
    {
        abort_unless(
            $user->can('sales-fee-reports.view')
                || $user->can('leads.monitor')
                || $user->can('leads.view-team')
                || $user->can('leads.view-all'),
            403,
        );
    }
}
