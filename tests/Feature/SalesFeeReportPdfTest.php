<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\SalesFeeReportService;
use App\Support\Rbac;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalesFeeReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_dompdf_security_settings_are_explicitly_disabled(): void
    {
        $this->assertFalse(config('dompdf.options.enable_remote'));
        $this->assertFalse(config('dompdf.options.enable_php'));
        $this->assertSame(realpath(public_path('brand')), realpath(config('dompdf.options.chroot')));
    }

    #[DataProvider('allowedRoles')]
    public function test_allowed_roles_can_download_summary_and_detail_pdf(string $roleName): void
    {
        [$branch, $project, $sales] = $this->reportContext();
        $viewer = $this->globalUser($branch, $roleName);

        $summary = $this->actingAs($viewer)->get(route('sales-fee-reports.pdf', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]));
        $detail = $this->actingAs($viewer)->get(route('sales-fee-reports.detail-pdf.project', [
            'salesUser' => $sales,
            'project' => $project,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]));

        $summary->assertOk();
        $detail->assertOk();
        $this->assertSame('application/pdf', $summary->headers->get('content-type'));
        $this->assertSame('application/pdf', $detail->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $summary->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $detail->headers->get('content-disposition'));
    }

    public static function allowedRoles(): array
    {
        return [['super_admin'], ['pusat'], ['bm'], ['spv'], ['sas'], ['sap']];
    }

    public function test_sales_and_coordinator_cannot_download_pdf(): void
    {
        [$branch, $project, $sales, $team] = $this->reportContext(true);
        $coordinator = $this->teamUser($branch, $team, 'koordinator_sales');

        $this->actingAs($sales)->get(route('sales-fee-reports.pdf'))->assertForbidden();
        $this->actingAs($coordinator)->get(route('sales-fee-reports.pdf'))->assertForbidden();
        $this->actingAs($sales)->get(route('sales-fee-reports.detail-pdf.project', ['salesUser' => $sales, 'project' => $project]))->assertForbidden();
        $this->actingAs($coordinator)->get(route('sales-fee-reports.detail-pdf.project', ['salesUser' => $sales, 'project' => $project]))->assertForbidden();
    }

    public function test_pdf_view_data_respects_branch_scope_and_filters(): void
    {
        [$branch, $project] = $this->reportContext();
        $otherBranch = Branch::factory()->create();
        $viewer = $this->globalUser($branch, 'sas');
        $localLead = Lead::factory()->create(['id_lead' => 'PDF-LOCAL', 'branch_id' => $branch->id, 'project_id' => $project->id, 'tanggal_lead' => '2026-10-10']);
        $foreignLead = Lead::factory()->create(['id_lead' => 'PDF-FOREIGN', 'branch_id' => $otherBranch->id, 'tanggal_lead' => '2026-10-10']);
        $captured = null;
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('pdf');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use (&$captured): bool {
            $captured = [$view, $data];

            return true;
        })->andReturn($pdf);

        $this->actingAs($viewer)->get(route('sales-fee-reports.pdf', [
            'branch_id' => $otherBranch->id,
            'project_id' => $project->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]))->assertOk();

        $this->assertSame('sales-fee-reports.pdf-summary', $captured[0]);
        $this->assertSame($branch->id, $captured[1]['filters']['branch_id']);
        $this->assertSame($project->id, $captured[1]['filters']['project_id']);
        $this->assertSame('2026-10-01', $captured[1]['filters']['date_from']);
        $this->assertSame('2026-10-31', $captured[1]['filters']['date_to']);
        $this->assertTrue($captured[1]['report']['leads']->contains('id', $localLead->id));
        $this->assertFalse($captured[1]['report']['leads']->contains('id', $foreignLead->id));
    }

    public function test_pdf_download_is_audited(): void
    {
        [$branch, $project, $sales] = $this->reportContext();
        $viewer = $this->globalUser($branch, 'sas');
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('pdf');
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);

        $this->actingAs($viewer)->get(route('sales-fee-reports.pdf'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $viewer->id,
            'event' => 'sales_fee_report_pdf_downloaded',
        ]);
    }

    public function test_pdf_rejects_reports_over_the_safe_row_limit(): void
    {
        [$branch, $project, $sales] = $this->reportContext();
        $viewer = $this->globalUser($branch, 'sas');
        $reportService = Mockery::mock(SalesFeeReportService::class);
        $reportService->shouldReceive('build')->once()->andReturn([
            'rows' => collect(),
            'totals' => ['total_agenda' => 0, 'agenda_selesai' => 0, 'total_lead' => 1001, 'tatap_muka' => 0, 'cek_lokasi' => 0, 'utj' => 0],
            'leads' => collect(range(1, 1001)),
            'agendas' => collect(),
        ]);
        $this->app->instance(SalesFeeReportService::class, $reportService);

        $this->actingAs($viewer)->get(route('sales-fee-reports.detail-pdf.project', ['salesUser' => $sales, 'project' => $project]))->assertStatus(422)->assertSee('Periode terlalu panjang untuk PDF');
    }

    public function test_summary_pdf_does_not_apply_the_detail_row_limit(): void
    {
        [$branch] = $this->reportContext();
        $viewer = $this->globalUser($branch, 'sas');
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('pdf');
        Pdf::shouldReceive('loadView')->once()->andReturn($pdf);
        $reportService = Mockery::mock(SalesFeeReportService::class);
        $reportService->shouldReceive('build')->once()->andReturn([
            'rows' => collect([['sales_id' => 1, 'sales_name' => 'Sales', 'coordinator_name' => '-', 'project_id' => null, 'project_name' => '-', 'total_agenda' => 1001, 'agenda_selesai' => 0, 'total_lead' => 1001, 'tatap_muka' => 0, 'cek_lokasi' => 0, 'utj' => 0]]),
            'totals' => ['total_agenda' => 1001, 'agenda_selesai' => 0, 'total_lead' => 1001, 'tatap_muka' => 0, 'cek_lokasi' => 0, 'utj' => 0],
            'leads' => collect(range(1, 1001)),
            'agendas' => collect(range(1, 1001)),
        ]);
        $this->app->instance(SalesFeeReportService::class, $reportService);

        $this->actingAs($viewer)->get(route('sales-fee-reports.pdf'))->assertOk();
    }

    public function test_pdf_memory_guard_returns_indonesian_message_and_audits_rejection(): void
    {
        [$branch] = $this->reportContext();
        $viewer = $this->globalUser($branch, 'sas');
        config(['sales-fee.pdf.estimated_bytes_per_record' => 1024 * 1024 * 1024]);
        $reportService = Mockery::mock(SalesFeeReportService::class);
        $reportService->shouldReceive('build')->once()->andReturn([
            'rows' => collect([['sales_id' => 1]]),
            'totals' => ['total_agenda' => 0, 'agenda_selesai' => 0, 'total_lead' => 1, 'tatap_muka' => 0, 'cek_lokasi' => 0, 'utj' => 0],
            'leads' => collect([1]),
            'agendas' => collect(),
        ]);
        $this->app->instance(SalesFeeReportService::class, $reportService);

        $this->actingAs($viewer)->get(route('sales-fee-reports.pdf'))
            ->assertStatus(422)
            ->assertSee('Laporan terlalu besar untuk PDF pada batas memori server');
        $this->assertDatabaseHas('activity_logs', ['event' => 'sales_fee_report_pdf_rejected']);
    }

    public function test_redirected_agenda_is_credited_to_new_owner_in_pdf_data(): void
    {
        [$branch, $project, $oldOwner, $team] = $this->reportContext(true);
        $newOwner = $this->teamUser($branch, $team, 'sales');
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'created_by' => $oldOwner->id,
            'owner_id' => $newOwner->id,
            'agenda_date' => '2026-10-10',
        ]);
        $viewer = $this->globalUser($branch, 'sas');
        $captured = null;
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('pdf');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $view, array $data) use (&$captured): bool {
            $captured = $data;

            return true;
        })->andReturn($pdf);

        $this->actingAs($viewer)->get(route('sales-fee-reports.pdf', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk();

        $this->assertSame($newOwner->id, $captured['report']['agendas']->first()->owner_id);
        $this->assertSame($agenda->id, $captured['report']['agendas']->first()->id);
    }

    /** @return array{0: Branch, 1: Project, 2: User, 3?: Team} */
    private function reportContext(bool $withTeam = false): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->create(['branch_id' => $branch->id]);
        $sales = User::factory()->for($branch, 'branch')->create();

        if (! $withTeam) {
            return [$branch, $project, $sales];
        }

        $team = Team::factory()->create(['branch_id' => $branch->id, 'project_id' => $project->id]);
        $this->teamUser($branch, $team, 'sales', $sales);

        return [$branch, $project, $sales, $team];
    }

    private function globalUser(Branch $branch, string $roleName): User
    {
        $user = User::factory()->for($branch, 'branch')->create();
        $user->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function teamUser(Branch $branch, Team $team, string $roleName, ?User $user = null): User
    {
        $user ??= User::factory()->for($branch, 'branch')->create();
        Rbac::provisionTeam($team);
        $user->teams()->attach($team, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $user->assignRole(Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail());
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $user;
    }
}
