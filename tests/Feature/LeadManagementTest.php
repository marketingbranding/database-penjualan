<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\LeadHandoff;
use App\Models\Project;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadCreatedForSalesNotification;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_create_and_update_own_lead(): void
    {
        [$branch, $team, $sales, $project] = $this->salesContext();
        Notification::fake();

        $payload = [
            'id_promo' => '260101-D1-01',
            'nama_promo' => 'DP 10jt All In',
            'tanggal_lead' => '2026-06-27',
            'sumber_lead' => 'Online',
            'kanal_masuk' => 'TikTok',
            'aktivitas_lead' => 'Live',
            'nama_konsumen' => 'Yopi Irawan',
            'no_hp' => '088980621563',
            'project_id' => $project->id,
            'sales_pic_id' => $sales->id,
            'status_lead' => 'No Respon',
            'keterangan' => 'Lead dari live TikTok.',
        ];

        $response = $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), $payload);

        $lead = Lead::query()->firstOrFail();

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('260627-ON-TI-01', $lead->id_lead);
        $this->assertSame($branch->id, $lead->branch_id);
        $this->assertSame($team->id, $lead->team_id);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $lead->getMorphClass(),
            'subject_id' => $lead->id,
            'branch_id' => $branch->id,
            'event' => 'created',
        ]);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'Lead Baru',
            'status_to' => 'No Respon',
        ]);

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->put(route('leads.update', $lead), [...$payload, 'status_lead' => 'Diskusi'])
            ->assertRedirect(route('leads.show', $lead));

        $this->assertSame('Diskusi', $lead->fresh()->status_lead);
        Notification::assertNothingSent();
    }

    public function test_sales_can_create_a_lead_without_a_phone_number(): void
    {
        [, $team, $sales, $project] = $this->salesContext();
        $payload = [
            'tanggal_lead' => '2026-06-27',
            'sumber_lead' => 'Online',
            'kanal_masuk' => 'TikTok',
            'aktivitas_lead' => 'Live',
            'nama_konsumen' => 'Lead Tanpa Nomor',
            'project_id' => $project->id,
            'sales_pic_id' => $sales->id,
            'status_lead' => 'No Respon',
        ];

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'nama_konsumen' => 'Lead Tanpa Nomor',
            'no_hp' => null,
        ]);
    }

    public function test_sales_can_record_activity_and_change_lead_status(): void
    {
        [, $team, $sales] = $this->salesContext();
        $lead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'status_lead' => 'No Respon',
        ]);

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.activities.store', $lead), [
                'activity_type' => 'Follow Up',
                'description' => 'Konsumen membalas WhatsApp.',
                'status_lead' => 'Diskusi',
                'happened_at' => '2026-06-27 10:30',
            ])
            ->assertRedirect();

        $this->assertSame('Diskusi', $lead->fresh()->status_lead);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'activity_type' => 'Follow Up',
            'status_from' => 'No Respon',
            'status_to' => 'Diskusi',
            'description' => 'Konsumen membalas WhatsApp.',
        ]);
    }

    public function test_lead_workspace_defaults_to_table_and_keeps_active_filter(): void
    {
        [, $team, $sales] = $this->salesContext();
        $pipelineLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'status_lead' => 'UTJ',
            'nama_konsumen' => 'Lead Pipeline',
        ]);
        $lostLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-02',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'status_lead' => 'Tidak Lolos BI Checking',
            'nama_konsumen' => 'Lead Tidak Aktif',
        ]);

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('leads.index'))
            ->assertOk()
            ->assertViewHas('view', 'table')
            ->assertSee('Tabel Lead')
            ->assertDontSee('Board saat ini read-only')
            ->assertSee($pipelineLead->id_lead);

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('leads.index', ['view' => 'table', 'active' => 1]))
            ->assertOk()
            ->assertViewHas('view', 'table')
            ->assertSee($pipelineLead->id_lead)
            ->assertDontSee($lostLead->id_lead);
    }

    public function test_sales_can_schedule_qualify_and_handoff_a_lead_without_creating_a_sales_case(): void
    {
        [$branch, $team, $sales, , $coordinator] = $this->salesContext(withCoordinator: true);
        $lead = Lead::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])
            ->patch(route('leads.follow-up.update', $lead), [
                'next_follow_up_at' => '2026-10-15 09:30',
                'next_follow_up_note' => 'Konfirmasi jadwal kunjungan.',
            ])->assertRedirect();

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])
            ->patch(route('leads.qualification.update', $lead), [
                'qualification_status' => 'qualified',
                'qualification_note' => 'Kebutuhan dan kemampuan bayar sudah dikonfirmasi.',
            ])->assertRedirect();

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])
            ->post(route('leads.handoffs.store', $lead), [
                'note' => 'Mohon ditinjau sebelum masuk proses penjualan.',
            ])->assertRedirect();

        $handoff = LeadHandoff::query()->firstOrFail();
        $this->assertSame('submitted', $handoff->status);
        $this->assertSame('qualified', $lead->fresh()->qualification_status);
        $this->assertDatabaseCount('sales_cases', 0);

        $this->actingAs($coordinator)->withSession(['active_team_id' => $team->id])
            ->patch(route('lead-handoffs.review', $handoff), [
                'status' => 'accepted',
                'review_note' => 'Siap diteruskan saat alur proses sudah final.',
            ])->assertRedirect();

        $this->assertSame('accepted', $handoff->fresh()->status);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'next_follow_up_note' => 'Konfirmasi jadwal kunjungan.',
            'qualification_status' => 'qualified',
        ]);
    }

    public function test_unqualified_lead_cannot_be_handed_off(): void
    {
        [, $team, $sales] = $this->salesContext();
        $lead = Lead::factory()->create([
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])
            ->post(route('leads.handoffs.store', $lead), ['note' => 'Belum qualified.'])
            ->assertSessionHasErrors('note');

        $this->assertDatabaseCount('lead_handoffs', 0);
    }

    public function test_sales_can_filter_leads_by_today_follow_up(): void
    {
        [, $team, $sales] = $this->salesContext();
        $todayLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'next_follow_up_at' => today()->setTime(10, 0),
        ]);
        $laterLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-02',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'next_follow_up_at' => today()->addDay()->setTime(10, 0),
        ]);

        $this->actingAs($sales)->withSession(['active_team_id' => $team->id])
            ->get(route('leads.index', ['follow_up' => 'today']))
            ->assertOk()
            ->assertSee($todayLead->id_lead)
            ->assertDontSee($laterLead->id_lead);
    }

    public function test_second_lead_same_date_source_and_channel_gets_next_sequence(): void
    {
        [, $team, $sales] = $this->salesContext();
        $first = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'tanggal_lead' => '2026-06-27',
            'sumber_lead' => 'Online',
            'kanal_masuk' => 'TikTok',
            'branch_id' => $sales->branch_id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
        ]);

        $second = Lead::generateLeadId('2026-06-27', 'Online', 'TikTok');

        $this->assertSame('260627-ON-TI-02', $second);
        $this->assertModelExists($first);
    }

    public function test_coordinator_can_input_lead_for_sales_in_own_team(): void
    {
        [$branch, $team, $sales, $project, $coordinator] = $this->salesContext(withCoordinator: true);
        Notification::fake();

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), [
                'tanggal_lead' => '2026-06-28',
                'sumber_lead' => 'Online',
                'kanal_masuk' => 'TikTok',
                'aktivitas_lead' => 'Live',
                'nama_konsumen' => 'Citra',
                'no_hp' => '085727614860',
                'project_id' => $project->id,
                'sales_pic_id' => $sales->id,
                'status_lead' => 'Diskusi',
            ])
            ->assertRedirect();

        $lead = Lead::query()->firstOrFail();
        $this->assertSame($sales->id, $lead->sales_pic_id);
        $this->assertSame($coordinator->id, $lead->created_by);
        $this->assertSame($branch->id, $lead->branch_id);
        Notification::assertSentTo($sales, LeadCreatedForSalesNotification::class);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $lead->getMorphClass(),
            'subject_id' => $lead->id,
            'event' => 'lead_created_for_sales',
        ]);
    }

    public function test_sales_cannot_create_a_lead_for_themselves_through_a_different_sales_pic(): void
    {
        [, $team, $sales, $project] = $this->salesContext();
        $otherSales = User::factory()->for($sales->branch, 'branch')->create();

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), [
                'tanggal_lead' => '2026-06-28', 'sumber_lead' => 'Online', 'kanal_masuk' => 'TikTok',
                'aktivitas_lead' => 'Live', 'nama_konsumen' => 'Citra', 'project_id' => $project->id,
                'sales_pic_id' => $otherSales->id, 'status_lead' => 'Diskusi',
            ])
            ->assertSessionHasErrors('sales_pic_id');
    }

    public function test_super_admin_can_edit_legacy_lead_without_a_sales_pic_role(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $project = Project::factory()->for($branch)->create();
        $legacyPic = User::factory()->for($branch, 'branch')->create();
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());
        $lead = Lead::factory()->create([
            'branch_id' => $branch->id, 'project_id' => $project->id, 'sales_pic_id' => $legacyPic->id,
            'created_by' => $legacyPic->id, 'nama_konsumen' => 'Legacy Lead',
        ]);

        $this->actingAs($admin)->put(route('leads.update', $lead), [
            'tanggal_lead' => $lead->tanggal_lead->toDateString(), 'sumber_lead' => $lead->sumber_lead,
            'kanal_masuk' => $lead->kanal_masuk, 'aktivitas_lead' => $lead->aktivitas_lead,
            'nama_konsumen' => 'Legacy Lead Updated', 'no_hp' => $lead->no_hp,
            'project_id' => $project->id, 'sales_pic_id' => $legacyPic->id,
            'status_lead' => $lead->status_lead, 'keterangan' => $lead->keterangan,
        ])->assertRedirect(route('leads.show', $lead));

        $this->assertSame('Legacy Lead Updated', $lead->fresh()->nama_konsumen);
    }

    public function test_coordinator_cannot_assign_lead_to_sales_from_another_team(): void
    {
        [, $team, , $project, $coordinator] = $this->salesContext(withCoordinator: true);
        $otherTeam = Team::factory()->for($project->branch)->create();
        Rbac::provisionTeam($otherTeam);
        $otherSales = User::factory()->for($project->branch, 'branch')->create();
        $otherTeam->users()->attach($otherSales);

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), [
                'tanggal_lead' => '2026-06-28',
                'sumber_lead' => 'Online',
                'kanal_masuk' => 'TikTok',
                'aktivitas_lead' => 'Live',
                'nama_konsumen' => 'Citra',
                'no_hp' => '085727614860',
                'project_id' => $project->id,
                'sales_pic_id' => $otherSales->id,
                'status_lead' => 'Diskusi',
            ])
            ->assertSessionHasErrors('sales_pic_id');

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_coordinator_form_only_offers_active_sales_from_the_active_team(): void
    {
        [$branch, $team, $sales, $project, $coordinator] = $this->salesContext(withCoordinator: true);
        $outsideSales = User::factory()->for($branch, 'branch')->create(['name' => 'Outside Sales']);

        $this->actingAs($coordinator)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('leads.create'))
            ->assertOk()
            ->assertSee('value="'.$sales->id.'"', false)
            ->assertDontSee('value="'.$coordinator->id.'"', false)
            ->assertDontSee('value="'.$outsideSales->id.'"', false);
    }

    public function test_super_admin_can_assign_only_an_active_sales_pic(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        Rbac::provisionTeam($team);
        $sales = User::factory()->for($branch, 'branch')->create();
        $team->users()->attach($sales, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());
        $admin = User::factory()->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());
        $nonSales = User::factory()->for($branch, 'branch')->create();
        $payload = [
            'tanggal_lead' => '2026-06-28', 'sumber_lead' => 'Online', 'kanal_masuk' => 'TikTok',
            'aktivitas_lead' => 'Live', 'nama_konsumen' => 'Assigned Sales', 'project_id' => null,
            'status_lead' => 'No Respon',
        ];

        $this->actingAs($admin)->post(route('leads.store'), [...$payload, 'sales_pic_id' => $sales->id])->assertRedirect();
        $this->actingAs($admin)->post(route('leads.store'), [...$payload, 'sales_pic_id' => $nonSales->id])->assertSessionHasErrors('sales_pic_id');
    }

    public function test_monitoring_role_can_view_but_cannot_create_or_edit_lead(): void
    {
        [$branch, $team, $sales] = $this->salesContext();
        $lead = Lead::factory()->create([
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'id_lead' => '260627-ON-TI-01',
        ]);
        $spv = User::factory()->for($branch, 'branch')->create();
        $spv->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail());

        $this->actingAs($spv)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee($lead->id_lead);

        $this->actingAs($spv)->get(route('leads.create'))->assertForbidden();
        $this->actingAs($spv)->get(route('leads.edit', $lead))->assertForbidden();
    }

    public function test_monitoring_filters_and_summary_are_scoped_to_branch(): void
    {
        [$branch, $team, $sales] = $this->salesContext();
        $matchingLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'tanggal_lead' => '2026-06-27',
            'status_lead' => 'UTJ',
            'nama_konsumen' => 'Lead Monitoring',
        ]);
        $otherLead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-02',
            'branch_id' => $branch->id,
            'team_id' => $team->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
            'tanggal_lead' => '2026-06-27',
            'status_lead' => 'Diskusi',
            'nama_konsumen' => 'Lead Lain',
        ]);
        $spv = User::factory()->for($branch, 'branch')->create();
        $spv->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail());

        $this->actingAs($spv)
            ->get(route('leads.monitoring', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'status_lead' => 'UTJ',
            ]))
            ->assertOk()
            ->assertSee($matchingLead->id_lead)
            ->assertDontSee($otherLead->id_lead)
            ->assertSee('Total Lead');
    }

    public function test_sales_cannot_open_monitoring_page(): void
    {
        [, $team, $sales] = $this->salesContext();

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('leads.monitoring'))
            ->assertForbidden();
    }

    public function test_sales_cannot_view_lead_from_another_branch(): void
    {
        [, $team, $sales] = $this->salesContext();
        $otherBranch = Branch::factory()->create();
        $otherLead = Lead::factory()->create([
            'branch_id' => $otherBranch->id,
            'id_lead' => '260627-ON-TI-01',
        ]);

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->get(route('leads.show', $otherLead))
            ->assertForbidden();
    }

    public function test_empty_lead_form_is_rejected(): void
    {
        [, $team, $sales] = $this->salesContext();

        $this->actingAs($sales)
            ->withSession(['active_team_id' => $team->id])
            ->post(route('leads.store'), [])
            ->assertSessionHasErrors(['tanggal_lead', 'sumber_lead', 'kanal_masuk', 'nama_konsumen', 'sales_pic_id']);
    }

    /** @return array{Branch, Team, User, Project, ?User} */
    private function salesContext(bool $withCoordinator = false): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $team = Team::factory()->for($branch)->create();
        Rbac::provisionTeam($team);
        $project = Project::factory()->for($branch)->create();
        $sales = User::factory()->for($branch, 'branch')->create();
        $team->users()->attach($sales, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());

        $coordinator = null;
        if ($withCoordinator) {
            $coordinator = User::factory()->for($branch, 'branch')->create();
            $team->users()->attach($coordinator);
            $team->update(['coordinator_id' => $coordinator->id]);
            $coordinator->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'koordinator_sales')->firstOrFail());
        }

        return [$branch, $team, $sales, $project, $coordinator];
    }
}
