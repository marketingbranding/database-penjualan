<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Project;
use App\Models\Team;
use App\Support\BusinessTime;
use App\TeamPerformanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamPerformanceController extends Controller
{
    public function __construct(private readonly TeamPerformanceService $performanceService) {}

    public function index(Request $request): View
    {
        $this->authorizePerformance($request);
        $filters = $this->filters($request);

        return view('team-performance.index', [
            'report' => $this->performanceService->build($request->user(), $filters),
            'filters' => $filters,
            'branches' => $request->user()->canAccessAllBranches() ? Branch::query()->where('is_active', true)->orderBy('name')->get() : collect([$request->user()->branch])->filter(),
            'projects' => Project::query()->where('is_active', true)->when($filters['branch_id'], fn ($query, int $branchId) => $query->where('branch_id', $branchId))->orderBy('name')->get(),
            'coordinators' => Team::query()->where('is_active', true)->whereNotNull('coordinator_id')->when($filters['branch_id'], fn ($query, int $branchId) => $query->where('branch_id', $branchId))->with('coordinator')->get()->pluck('coordinator')->filter()->unique('id')->sortBy('name')->values(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePerformance($request);
        $filters = $this->filters($request);
        $report = $this->performanceService->build($request->user(), $filters);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Performa Tim');
        $sheet->fromArray([['PERFORMA TIM'], ['Periode', $filters['date_from'], 'sampai', $filters['date_to']], [], ['Proyek', 'Tim Koordinator', 'Sales', ...$report['statusLabels'], ...$report['sideOutcomeLabels'], 'Movement UTJ', 'Movement Akad', 'Konversi UTJ %', 'Konversi Akad %', 'Overdue Follow-up', 'Agenda Planned', 'Agenda Done', 'Agenda Cancelled', 'Agenda Overdue', 'Completion %', 'Pengalihan In', 'Pengalihan Out']]);
        $row = 5;

        foreach ($report['projects'] as $project) {
            foreach ($project['teams'] as $team) {
                foreach ($team['sales'] as $sales) {
                    $sheet->fromArray([[
                        $project['project_name'], $team['team_name'], $sales['sales_name'], ...array_values($sales['statuses']), ...array_values($sales['side_outcomes']),
                        $sales['movement_utj'], $sales['movement_akad'], $sales['cohort_total'] ? round($sales['cohort_reached_utj'] / $sales['cohort_total'] * 100, 1) : 0, $sales['cohort_total'] ? round($sales['cohort_reached_akad'] / $sales['cohort_total'] * 100, 1) : 0, $sales['overdue_followups'],
                        $sales['agendas_planned'], $sales['agendas_done'], $sales['agendas_cancelled'], $sales['agendas_overdue'],
                        $sales['completion_rate'], $sales['redirected_in'], $sales['redirected_out'],
                    ]], null, 'A'.$row++);
                }
            }
        }

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($spreadsheet))->save('php://output'),
            'performa-tim-'.$filters['date_from'].'-sd-'.$filters['date_to'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function authorizePerformance(Request $request): void
    {
        abort_unless($request->user()->can('reports.team-performance'), 403);
    }

    /** @return array{branch_id: int|null, project_id: int|null, coordinator_id: int|null, date_from: string, date_to: string} */
    private function filters(Request $request): array
    {
        $today = BusinessTime::now()->toDateString();

        return [
            'branch_id' => $request->user()->canAccessAllBranches() ? ($request->integer('branch_id') ?: null) : $request->user()->branch_id,
            'project_id' => $request->integer('project_id') ?: null,
            'coordinator_id' => $request->integer('coordinator_id') ?: null,
            'date_from' => $request->input('date_from', BusinessTime::now()->startOfMonth()->toDateString()),
            'date_to' => $request->input('date_to', $today),
        ];
    }
}
