<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesAgendaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_create_agenda_with_optional_sales_case_and_evidence(): void
    {
        Storage::fake('local');
        [$sales, $branch] = $this->salesUser();
        $image = UploadedFile::fake()->image('kunjungan.jpg');

        $response = $this->actingAs($sales)->post(route('sales-agendas.store'), [
            'agenda_date' => '2026-10-08',
            'activity_category' => 'Cek Lokasi',
            'title' => 'Kunjungan lokasi calon konsumen',
            'location' => 'Perumahan Marison',
            'activity_result' => 'Konsumen hadir untuk melihat lokasi.',
            'evidence' => [$image],
        ]);

        $agenda = SalesAgenda::query()->firstOrFail();

        $response->assertRedirect(route('sales-agendas.index'));
        $this->assertDatabaseHas('sales_agendas', [
            'id' => $agenda->id,
            'branch_id' => $branch->id,
            'created_by' => $sales->id,
            'sales_case_id' => null,
            'activity_category' => 'Cek Lokasi',
            'status' => 'planned',
        ]);
        $this->assertDatabaseHas('sales_agenda_evidence', [
            'sales_agenda_id' => $agenda->id,
            'original_name' => 'kunjungan.jpg',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => $agenda->getMorphClass(),
            'subject_id' => $agenda->id,
            'branch_id' => $branch->id,
            'event' => 'created',
        ]);
    }

    public function test_sales_can_view_and_complete_own_agenda(): void
    {
        [$sales] = $this->salesUser();
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $sales->branch_id,
            'created_by' => $sales->id,
            'title' => 'Follow up calon konsumen',
        ]);

        $this->actingAs($sales)
            ->get(route('sales-agendas.index'))
            ->assertOk()
            ->assertSee('Follow up calon konsumen');

        $this->actingAs($sales)
            ->patch(route('sales-agendas.complete', $agenda), ['activity_result' => 'Berhasil dihubungi.'])
            ->assertRedirect();

        $this->assertDatabaseHas('sales_agendas', [
            'id' => $agenda->id,
            'status' => 'done',
            'activity_result' => 'Berhasil dihubungi.',
        ]);
    }

    public function test_planned_agenda_has_cancel_confirmation(): void
    {
        [$sales] = $this->salesUser();
        SalesAgenda::factory()->create([
            'branch_id' => $sales->branch_id,
            'created_by' => $sales->id,
            'status' => 'planned',
        ]);

        $this->actingAs($sales)
            ->get(route('sales-agendas.index'))
            ->assertOk()
            ->assertSee('Yakin membatalkan agenda ini?');
    }

    public function test_sales_can_link_agenda_to_a_lead(): void
    {
        [$sales, $branch] = $this->salesUser();
        $lead = Lead::factory()->create([
            'id_lead' => '260627-ON-TI-01',
            'branch_id' => $branch->id,
            'sales_pic_id' => $sales->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post(route('sales-agendas.store'), [
            'lead_id' => $lead->id,
            'agenda_date' => '2026-10-08',
            'activity_category' => 'Follow-up',
            'title' => 'Follow up lead',
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_agendas', [
            'lead_id' => $lead->id,
            'created_by' => $sales->id,
        ]);
    }

    public function test_sales_cannot_view_another_branch_agenda(): void
    {
        [$sales, $branch] = $this->salesUser();
        $otherBranch = Branch::factory()->create();
        $otherUser = User::factory()->for($otherBranch, 'branch')->create();
        $agenda = SalesAgenda::factory()->create([
            'branch_id' => $otherBranch->id,
            'created_by' => $otherUser->id,
        ]);

        $this->actingAs($sales)
            ->get(route('sales-agendas.index'))
            ->assertOk()
            ->assertDontSee($agenda->title);

        $this->actingAs($sales)
            ->get(route('sales-agendas.edit', $agenda))
            ->assertForbidden();

        $this->assertSame($branch->id, $sales->branch_id);
    }

    public function test_sales_cannot_attach_sales_case_from_another_branch(): void
    {
        [$sales] = $this->salesUser();
        $foreignCase = SalesCase::factory()->create();

        $this->actingAs($sales)
            ->post(route('sales-agendas.store'), [
                'sales_case_id' => $foreignCase->id,
                'agenda_date' => '2026-10-08',
                'activity_category' => 'Follow-up',
                'title' => 'Agenda lintas cabang',
            ])
            ->assertSessionHasErrors('sales_case_id');

        $this->assertDatabaseCount('sales_agendas', 0);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('sales-agendas.index'))->assertRedirect(route('login'));
    }

    public function test_empty_agenda_form_is_rejected(): void
    {
        [$sales] = $this->salesUser();

        $this->actingAs($sales)
            ->post(route('sales-agendas.store'), [])
            ->assertSessionHasErrors(['agenda_date', 'activity_category', 'title']);
    }

    /** @return array{User, Branch} */
    private function salesUser(): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create();
        $sales = User::factory()->for($branch, 'branch')->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'sas')->firstOrFail();
        $sales->globalRoles()->attach($role);

        return [$sales, $branch];
    }
}
