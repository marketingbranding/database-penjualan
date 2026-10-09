<?php

namespace Tests\Feature;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Role;
use App\Models\SalesCase;
use App\Models\User;
use App\Notifications\ReservationCancelledNotification;
use App\PaymentMethod;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReservationCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_cancel_reservation_without_deleting_sales_case(): void
    {
        Notification::fake();
        [$admin, $salesCase] = $this->reservedSalesCase();
        $reason = 'Konsumen membatalkan pengajuan secara resmi.';

        $this->actingAs($admin)
            ->patch(route('consumers.cancel', $salesCase), ['reason' => $reason])
            ->assertRedirect(route('consumers.show', $salesCase));

        $salesCase->refresh();
        $this->assertNotNull($salesCase->cancelled_at);
        $this->assertSame($admin->id, $salesCase->cancelled_by);
        $this->assertSame($reason, $salesCase->cancellation_reason);
        $this->assertSame(LotStatus::Available, $salesCase->lot->fresh()->status);
        $this->assertDatabaseHas('sales_cases', ['id' => $salesCase->id]);
        $this->assertDatabaseHas('lot_status_histories', [
            'lot_id' => $salesCase->lot_id,
            'from_status' => LotStatus::Reserved->value,
            'to_status' => LotStatus::Available->value,
            'source' => 'reservation_cancelled',
            'reason' => $reason,
        ]);
        Notification::assertSentTo($admin, ReservationCancelledNotification::class);

        $this->actingAs($admin)
            ->get(route('consumers.show', $salesCase))
            ->assertOk()
            ->assertSee('Reservasi dibatalkan')
            ->assertSee($reason);
    }

    public function test_cancelled_lot_can_be_used_by_a_new_sales_case(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase();
        $this->actingAs($admin)->patch(route('consumers.cancel', $salesCase), [
            'reason' => 'Konsumen pertama membatalkan pemesanan.',
        ]);

        $payload = $this->consumerPayload($salesCase->lot->project, $salesCase->lot);
        $payload['branch_id'] = $salesCase->lot->project->branch_id;
        $payload['no_ktp'] = '3308105702920003';
        $payload['nama_konsumen'] = 'Konsumen Pengganti';

        $this->actingAs($admin)
            ->post(route('consumers.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('sales_cases', 2);
        $this->assertSame(1, SalesCase::query()->active()->count());
        $this->assertSame(LotStatus::Reserved, $salesCase->lot->fresh()->status);
    }

    public function test_sold_lot_reservation_cannot_be_cancelled(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase();
        $salesCase->lot->update(['status' => LotStatus::Sold]);

        $this->actingAs($admin)
            ->patch(route('consumers.cancel', $salesCase), [
                'reason' => 'Mencoba membatalkan kavling yang sudah akad.',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertNull($salesCase->fresh()->cancelled_at);
    }

    public function test_branch_role_without_cancel_permission_cannot_cancel_reservation(): void
    {
        [$admin, $salesCase] = $this->reservedSalesCase('Magelang', 'A01');
        $spv = User::factory()->for($admin->branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'spv')->firstOrFail();
        $spv->globalRoles()->attach($role);

        $this->actingAs($spv)
            ->patch(route('consumers.cancel', $salesCase), [
                'reason' => 'Percobaan pembatalan oleh role cabang.',
            ])
            ->assertForbidden();

        $this->assertNull($salesCase->fresh()->cancelled_at);
    }

    /** @return array{User, SalesCase} */
    private function reservedSalesCase(string $branchName = 'Magelang', string $lotCode = 'A01'): array
    {
        Rbac::provision();
        $branch = Branch::factory()->create(['name' => $branchName]);
        $project = Project::factory()->for($branch)->create();
        $block = ProjectBlock::factory()->for($project)->create(['code' => $lotCode[0]]);
        $lot = Lot::factory()->for($project)->for($block, 'block')->create([
            'number' => (int) substr($lotCode, 1),
            'code' => $lotCode,
            'status' => LotStatus::Reserved,
        ]);
        $admin = User::factory()->for($branch)->create();
        $role = Role::query()->whereNull('team_id')->where('name', 'super_admin')->firstOrFail();
        $admin->globalRoles()->attach($role);
        $salesCase = SalesCase::factory()->for(Consumer::factory())->for($lot)->for($admin, 'creator')->create([
            'payment_method' => PaymentMethod::Cash,
        ]);

        return [$admin, $salesCase];
    }

    /** @return array<string, mixed> */
    private function consumerPayload(Project $project, Lot $lot): array
    {
        return [
            'project_id' => $project->id,
            'lot_id' => $lot->id,
            'no_ktp' => '3308106504650003',
            'nama_konsumen' => 'Rr Arista Widiastuti',
            'tanggal_lahir' => '1965-04-25',
            'pekerjaan' => 'Karyawan Swasta',
            'detail_pekerjaan' => null,
            'alamat' => 'Jl Kanon Blok E-15',
            'kelurahan' => 'Jogonegoro',
            'kecamatan' => 'Mertoyudan',
            'kabupaten_kota' => 'Kabupaten Magelang',
            'no_hp' => '081392267571',
            'nama_kontak_darurat' => 'Arismaya',
            'no_hp_kontak_darurat' => '081338628019',
            'metode_pembayaran' => PaymentMethod::Cash->value,
            'keterangan' => null,
        ];
    }
}
