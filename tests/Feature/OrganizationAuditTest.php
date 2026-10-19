<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\OrganizationAuditService;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_confirms_team_mapping_and_reports_lead_scope_mismatch_without_writing(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['code' => 'AUDIT-BR']);
        $team = Team::factory()->for($branch)->create(['name' => 'Audit Team', 'coordinator_id' => null]);
        Rbac::provisionTeam($team);
        $coordinator = User::factory()->for($branch)->create(['name' => 'Audit Coordinator']);
        $sales = User::factory()->for($branch)->create(['name' => 'Audit Sales']);
        $this->assignTeamRole($coordinator, $team, 'koordinator_sales');
        $this->assignTeamRole($sales, $team, 'sales');
        $team->update(['coordinator_id' => $coordinator->id]);
        Lead::factory()->create([
            'id_lead' => 'AUDIT-VALID',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
        ]);
        Lead::factory()->create([
            'id_lead' => 'AUDIT-MISMATCH',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => User::factory()->create()->id,
            'created_by' => $sales->id,
        ]);
        $teamCount = Team::query()->count();
        $leadCount = Lead::query()->count();

        $report = app(OrganizationAuditService::class)->run();

        $this->assertSame(1, $report['counts']['active_teams']);
        $this->assertSame(1, $report['counts']['coordinators']);
        $this->assertSame(1, $report['counts']['sales']);
        $this->assertSame(1, $report['counts']['lead_mismatches']);
        $this->assertSame('CONFIRMED', $report['coordinators'][0]['mapping_status']);
        $this->assertSame('HIGH CONFIDENCE', $report['sales'][0]['mapping_status']);
        $this->assertSame($teamCount, Team::query()->count());
        $this->assertSame($leadCount, Lead::query()->count());
    }

    public function test_audit_marks_multiple_coordinator_legacy_team_as_ambiguous(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['code' => 'AUDIT-AMB']);
        $team = Team::factory()->for($branch)->create(['name' => 'Migrasi Tim Audit', 'coordinator_id' => null]);
        Rbac::provisionTeam($team);
        $firstCoordinator = User::factory()->for($branch)->create(['name' => 'Coordinator A']);
        $secondCoordinator = User::factory()->for($branch)->create(['name' => 'Coordinator B']);
        $this->assignTeamRole($firstCoordinator, $team, 'koordinator_sales');
        $this->assignTeamRole($secondCoordinator, $team, 'koordinator_sales');

        $report = app(OrganizationAuditService::class)->run();

        $this->assertSame(2, $report['counts']['coordinators']);
        $this->assertCount(1, array_filter($report['issues'], fn (array $issue): bool => $issue['code'] === 'multiple_coordinator_roles_in_team'));
        $this->assertCount(2, array_filter($report['coordinators'], fn (array $coordinator): bool => $coordinator['mapping_status'] === 'AMBIGUOUS'));
    }

    public function test_audit_command_returns_json_without_mutating_database(): void
    {
        Rbac::provision();
        $before = [
            'teams' => Team::query()->count(),
            'users' => User::query()->count(),
        ];

        $this->artisan('oasis:audit-organization', ['--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('"read_only": true');

        $this->assertSame($before['teams'], Team::query()->count());
        $this->assertSame($before['users'], User::query()->count());
    }

    private function assignTeamRole(User $user, Team $team, string $roleName): void
    {
        $team->users()->attach($user, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $role = Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);
    }
}
