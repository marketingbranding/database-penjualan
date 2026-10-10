<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertAgendaToLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_prefills_from_tiktok_live_agenda(): void
    {
        [$sales, $agenda] = $this->agendaContext('TikTok Live');

        $this->actingAs($sales)
            ->get(route('leads.create', ['from_agenda' => $agenda->id]))
            ->assertOk()
            ->assertSee('Lead ini dibuat dari agenda', false)
            ->assertSee('value="Online"', false)
            ->assertSee('value="TikTok"', false)
            ->assertSee('value="Live"', false)
            ->assertSee('name="origin_agenda_id" value="'.$agenda->id.'"', false);
    }

    public function test_storing_lead_links_origin_agenda(): void
    {
        [$sales, $agenda] = $this->agendaContext('Canvassing');

        $response = $this->actingAs($sales)->post(route('leads.store'), [
            'tanggal_lead' => today()->toDateString(),
            'sumber_lead' => 'Offline',
            'kanal_masuk' => 'Canvassing',
            'aktivitas_lead' => 'Sebar Brosur',
            'nama_konsumen' => 'Hasil Canvassing',
            'no_hp' => '081234567890',
            'sales_pic_id' => $sales->id,
            'status_lead' => 'No Respon',
            'origin_agenda_id' => $agenda->id,
        ]);

        $lead = Lead::query()->firstOrFail();
        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame($agenda->id, $lead->origin_agenda_id);
        $this->assertSame($agenda->id, $lead->originAgenda->id);

        $this->actingAs($sales)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Berasal dari agenda')
            ->assertSee($agenda->title);
    }

    public function test_one_agenda_can_produce_many_leads(): void
    {
        [$sales, $agenda] = $this->agendaContext('Canvassing');

        foreach (['Satu', 'Dua'] as $name) {
            $this->actingAs($sales)->post(route('leads.store'), [
                'tanggal_lead' => today()->toDateString(),
                'sumber_lead' => 'Offline',
                'kanal_masuk' => 'Canvassing',
                'aktivitas_lead' => 'Sebar Brosur',
                'nama_konsumen' => 'Konsumen '.$name,
                'no_hp' => '081234567891',
                'sales_pic_id' => $sales->id,
                'status_lead' => 'No Respon',
                'origin_agenda_id' => $agenda->id,
            ])->assertRedirect();
        }

        $this->assertSame(2, Lead::query()->where('origin_agenda_id', $agenda->id)->count());
    }

    public function test_agenda_from_another_branch_is_rejected(): void
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        [$sales] = $this->branchSalesContext('Canvassing', $branch);
        $foreignBranch = Branch::factory()->create();
        $foreignUser = User::factory()->for($foreignBranch, 'branch')->create();
        $foreignAgenda = SalesAgenda::factory()->create([
            'branch_id' => $foreignBranch->id,
            'created_by' => $foreignUser->id,
        ]);

        $this->actingAs($sales)
            ->get(route('leads.create', ['from_agenda' => $foreignAgenda->id]))
            ->assertForbidden();

        $this->actingAs($sales)->post(route('leads.store'), [
            'tanggal_lead' => today()->toDateString(),
            'sumber_lead' => 'Offline',
            'kanal_masuk' => 'Canvassing',
            'aktivitas_lead' => 'Sebar Brosur',
            'nama_konsumen' => 'Asing',
            'no_hp' => '081234567892',
            'sales_pic_id' => $sales->id,
            'status_lead' => 'No Respon',
            'origin_agenda_id' => $foreignAgenda->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_normal_lead_creation_still_works(): void
    {
        [$sales] = $this->agendaContext('Canvassing');

        $this->actingAs($sales)->post(route('leads.store'), [
            'tanggal_lead' => today()->toDateString(),
            'sumber_lead' => 'Online',
            'kanal_masuk' => 'TikTok',
            'aktivitas_lead' => 'Live',
            'nama_konsumen' => 'Manual',
            'no_hp' => '081234567893',
            'sales_pic_id' => $sales->id,
            'status_lead' => 'No Respon',
        ])->assertRedirect();

        $lead = Lead::query()->firstOrFail();
        $this->assertNull($lead->origin_agenda_id);
    }

    /** @return array{User, SalesAgenda} */
    private function agendaContext(string $category): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $admin = User::factory()->for($branch, 'branch')->create();
        $admin->globalRoles()->attach(Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail());
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $admin->id,
            'activity_category' => $category,
            'status' => 'done',
        ]);

        return [$admin, $agenda];
    }

    /** @return array{User, SalesAgenda} */
    private function branchSalesContext(string $category, Branch $branch): array
    {
        $team = Team::factory()->for($branch)->create();
        Rbac::provisionTeam($team);
        $sales = User::factory()->for($branch, 'branch')->create();
        $team->users()->attach($sales, ['is_default' => true]);
        setPermissionsTeamId($team->id);
        $sales->assignRole(Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail());
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $sales->id,
            'team_id' => $team->id,
            'activity_category' => $category,
            'status' => 'done',
        ]);

        return [$sales, $agenda];
    }
}
