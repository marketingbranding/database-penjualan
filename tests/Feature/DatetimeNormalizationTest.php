<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatetimeNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_datetime_is_saved_utc_and_prefilled_in_business_timezone(): void
    {
        [$sales, $lead, $team] = $this->salesContext();

        $this->actingAs($sales)->patch(route('leads.follow-up.update', $lead), [
            'next_follow_up_at' => '2026-10-15T23:30',
        ])->assertRedirect();

        $this->assertSame('2026-10-15 16:30:00', DB::table('leads')->where('id', $lead->id)->value('next_follow_up_at'));

        $this->actingAs($sales)->get(route('leads.show', $lead))
            ->assertSee('value="2026-10-15T23:30"', false);

        $this->actingAs($sales)->post(route('leads.activities.store', $lead), [
            'activity_type' => 'Catatan',
            'description' => 'Aktivitas malam.',
            'happened_at' => '2026-10-15T23:30',
        ])->assertRedirect();

        $activity = LeadActivity::query()->latest('id')->firstOrFail();
        $this->assertSame('2026-10-15 16:30:00', $activity->getRawOriginal('happened_at'));
        $this->assertSame('user', $activity->happened_at_source);
        $this->assertSame('23:30', substr((string) BusinessTime::forInput($activity->happened_at), 11, 5));
    }

    public function test_normalization_command_classifies_converts_and_reverses_rows(): void
    {
        [$sales, $lead] = $this->salesContext();
        $system = LeadActivity::factory()->create(['lead_id' => $lead->id, 'user_id' => $sales->id, 'happened_at_source' => null]);
        $user = LeadActivity::factory()->create(['lead_id' => $lead->id, 'user_id' => $sales->id, 'happened_at_source' => null]);
        DB::table('lead_activities')->where('id', $system->id)->update(['created_at' => '2026-10-15 16:30:00', 'happened_at' => '2026-10-15 16:31:00']);
        DB::table('lead_activities')->where('id', $user->id)->update(['created_at' => '2026-10-15 16:30:00', 'happened_at' => '2026-10-15 23:30:00']);

        Artisan::call('datetime:normalize-lead-activities');
        $dryRun = Artisan::output();
        $this->assertStringContainsString('System-written: 1', $dryRun);
        $this->assertStringContainsString('User-entered: 1', $dryRun);
        $this->assertSame('2026-10-15 23:30:00', DB::table('lead_activities')->where('id', $user->id)->value('happened_at'));

        Artisan::call('datetime:normalize-lead-activities', ['--force' => true]);
        $forceOutput = Artisan::output();
        preg_match('/Batch diterapkan: ([a-f0-9-]+)/', $forceOutput, $matches);
        $batchId = $matches[1] ?? null;

        $this->assertNotNull($batchId);
        $this->assertSame('system', DB::table('lead_activities')->where('id', $system->id)->value('happened_at_source'));
        $this->assertSame('user', DB::table('lead_activities')->where('id', $user->id)->value('happened_at_source'));
        $this->assertSame('2026-10-15 16:30:00', DB::table('lead_activities')->where('id', $user->id)->value('happened_at'));

        Artisan::call('datetime:normalize-lead-activities', ['--force' => true, '--reverse' => $batchId]);
        $this->assertSame('2026-10-15 23:30:00', DB::table('lead_activities')->where('id', $user->id)->value('happened_at'));
        $this->assertNull(DB::table('lead_activities')->where('id', $user->id)->value('happened_at_source'));
    }

    /** @return array{0: User, 1: Lead, 2: Team} */
    private function salesContext(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->create(['branch_id' => $branch->id]);
        Rbac::provisionTeam($team);
        $sales = User::factory()->for($branch, 'branch')->create();
        $sales->teams()->attach($team, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());
        $sales->unsetRelation('roles')->unsetRelation('permissions');
        $lead = Lead::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'sales_pic_id' => $sales->id, 'created_by' => $sales->id]);

        return [$sales, $lead, $team];
    }
}
