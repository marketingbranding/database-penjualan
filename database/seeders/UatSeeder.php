<?php

namespace Database\Seeders;

use App\LotStatus;
use App\Models\Branch;
use App\Models\Consumer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Lot;
use App\Models\Project;
use App\Models\ProjectBlock;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SalesAgenda;
use App\Models\SalesCase;
use App\Models\Team;
use App\Models\User;
use App\PaymentMethod;
use App\Support\BusinessTime;
use App\Support\Rbac;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UatSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $password = env('UAT_PASSWORD') ?: config('rbac.super_admin.password');

        if (! is_string($password) || $password === '') {
            throw new \RuntimeException('UAT_PASSWORD atau SUPER_ADMIN_PASSWORD wajib diisi sebelum menjalankan UAT seeder.');
        }

        $this->call([
            RolePermissionSeeder::class,
            BankSeeder::class,
            LeadTimeTargetSeeder::class,
        ]);

        Rbac::provision();

        $branches = [
            'kedungbulus' => Branch::query()->updateOrCreate(['code' => 'UAT-KED'], ['name' => 'UAT Kedungbulus', 'is_active' => true]),
            'kuwasen' => Branch::query()->updateOrCreate(['code' => 'UAT-KUW'], ['name' => 'UAT Kuwasen', 'is_active' => true]),
        ];
        $projects = [
            'kedungbulus' => Project::query()->updateOrCreate(['code' => 'UAT-MAR-KED'], ['name' => 'UAT Marison Kedungbulus', 'branch_id' => $branches['kedungbulus']->id, 'is_active' => true]),
            'kuwasen' => Project::query()->updateOrCreate(['code' => 'UAT-MAR-KUW'], ['name' => 'UAT Marison Kuwasen', 'branch_id' => $branches['kuwasen']->id, 'is_active' => true]),
        ];
        $promotions = [
            'all_in' => Promotion::query()->updateOrCreate(['promo_id' => 'UAT-260101-D1-01'], ['name' => 'DP 10jt All In UAT', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']),
            'cash' => Promotion::query()->updateOrCreate(['promo_id' => 'UAT-260101-CASH-01'], ['name' => 'Cashback UAT', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']),
        ];

        $teams = [
            'kedungbulus' => Team::query()->updateOrCreate(['code' => 'UAT-TEAM-KED'], ['name' => 'UAT Tim Kedungbulus', 'branch_id' => $branches['kedungbulus']->id, 'is_active' => true]),
            'kuwasen' => Team::query()->updateOrCreate(['code' => 'UAT-TEAM-KUW'], ['name' => 'UAT Tim Kuwasen', 'branch_id' => $branches['kuwasen']->id, 'is_active' => true]),
        ];

        foreach ($teams as $team) {
            Rbac::provisionTeam($team);
        }

        $admin = $this->globalUser('uat.admin@example.test', 'UAT Admin', null, 'super_admin', $password);
        $bm = $this->globalUser('uat.bm@example.test', 'UAT BM', $branches['kedungbulus'], 'bm', $password);
        $spv = $this->globalUser('uat.spv@example.test', 'UAT SPV', $branches['kedungbulus'], 'spv', $password);
        $sas = $this->globalUser('uat.sas@example.test', 'UAT SAS', $branches['kedungbulus'], 'sas', $password);
        $sap = $this->globalUser('uat.sap@example.test', 'UAT SAP', $branches['kuwasen'], 'sap', $password);

        $coordKed = $this->teamUser('uat.coord.ked@example.test', 'UAT Koordinator Kedungbulus', $branches['kedungbulus'], $teams['kedungbulus'], 'koordinator_sales', $password);
        $salesAndi = $this->teamUser('uat.sales.andi@example.test', 'UAT Sales Andi', $branches['kedungbulus'], $teams['kedungbulus'], 'sales', $password);
        $salesBudi = $this->teamUser('uat.sales.budi@example.test', 'UAT Sales Budi', $branches['kedungbulus'], $teams['kedungbulus'], 'sales', $password);
        $coordKuw = $this->teamUser('uat.coord.kuw@example.test', 'UAT Koordinator Kuwasen', $branches['kuwasen'], $teams['kuwasen'], 'koordinator_sales', $password);
        $salesCitra = $this->teamUser('uat.sales.citra@example.test', 'UAT Sales Citra', $branches['kuwasen'], $teams['kuwasen'], 'sales', $password);

        $teams['kedungbulus']->update(['coordinator_id' => $coordKed->id, 'supervisor_id' => $spv->id]);
        $teams['kuwasen']->update(['coordinator_id' => $coordKuw->id, 'supervisor_id' => $sap->id]);

        $this->seedProjectStructure($projects['kedungbulus']);
        $this->seedProjectStructure($projects['kuwasen']);
        $salesCase = $this->seedSalesCase($projects['kedungbulus'], $promotions['all_in'], $salesAndi);

        $leads = [
            ['id' => '261001-ON-TI-01', 'date' => '2026-10-01', 'source' => 'Online', 'channel' => 'TikTok', 'activity' => 'Live', 'name' => 'Yopi Irawan', 'phone' => '088980621563', 'status' => 'No Respon', 'sales' => $salesAndi, 'project' => $projects['kedungbulus'], 'promo' => $promotions['all_in']],
            ['id' => '261002-ON-TI-02', 'date' => '2026-10-02', 'source' => 'Online', 'channel' => 'TikTok', 'activity' => 'Live', 'name' => 'Citra', 'phone' => '085727614860', 'status' => 'Diskusi', 'sales' => $salesAndi, 'project' => $projects['kedungbulus'], 'promo' => $promotions['all_in']],
            ['id' => '261003-OF-CA-01', 'date' => '2026-10-03', 'source' => 'Offline', 'channel' => 'Canvassing', 'activity' => 'Sebar Brosur', 'name' => 'Miza', 'phone' => '081548413504', 'status' => 'Tatap Muka', 'sales' => $salesBudi, 'project' => $projects['kedungbulus'], 'promo' => $promotions['cash']],
            ['id' => '261004-OF-PA-01', 'date' => '2026-10-04', 'source' => 'Offline', 'channel' => 'Pameran', 'activity' => 'Event', 'name' => 'Nika', 'phone' => '085786821124', 'status' => 'Cek Lokasi', 'sales' => $salesBudi, 'project' => $projects['kedungbulus'], 'promo' => $promotions['cash']],
            ['id' => '261005-RE-WA-01', 'date' => '2026-10-05', 'source' => 'Referral', 'channel' => 'WhatsApp', 'activity' => 'Referral', 'name' => 'Afrilia', 'phone' => '088802820253', 'status' => 'UTJ', 'sales' => $salesAndi, 'project' => $projects['kedungbulus'], 'promo' => $promotions['all_in']],
            ['id' => '261006-ON-FB-01', 'date' => '2026-10-06', 'source' => 'Online', 'channel' => 'Facebook', 'activity' => 'Konten Organik', 'name' => 'Rudi', 'phone' => '081234567890', 'status' => 'Cek SLIK', 'sales' => $salesCitra, 'project' => $projects['kuwasen'], 'promo' => $promotions['cash']],
            ['id' => '261007-FR-TL-01', 'date' => '2026-10-07', 'source' => 'Freelance', 'channel' => 'Telepon', 'activity' => 'Freelance', 'name' => 'Sinta', 'phone' => '082345678901', 'status' => 'Jadi Freelance', 'sales' => $salesCitra, 'project' => $projects['kuwasen'], 'promo' => $promotions['cash']],
            ['id' => '261008-LC-DL-01', 'date' => '2026-10-08', 'source' => 'Lead Cabang', 'channel' => 'Datang Langsung', 'activity' => 'Datang Langsung', 'name' => 'Dimas', 'phone' => '083456789012', 'status' => 'Akad', 'sales' => $salesCitra, 'project' => $projects['kuwasen'], 'promo' => $promotions['all_in']],
        ];

        foreach ($leads as $leadData) {
            $this->seedLead($leadData, $leadData['sales']);
        }

        $this->seedAgenda('UAT Follow Up Citra', '2026-10-02', 'Follow-up', $salesAndi, $teams['kedungbulus'], $leads[1]['id'], 'done', 'Konsumen merespons dan meminta simulasi.');
        $this->seedAgenda('UAT Cek Lokasi Miza', '2026-10-03', 'Cek Lokasi', $salesBudi, $teams['kedungbulus'], $leads[2]['id'], 'done', 'Lokasi sesuai kebutuhan konsumen.');
        $this->seedAgenda('UAT Follow Up Sinta', '2026-10-07', 'Follow-up', $salesCitra, $teams['kuwasen'], $leads[6]['id'], 'planned', null);
        $this->seedAgenda('UAT Agenda Tanpa Lead', '2026-10-08', 'Pembuatan Konten', $salesAndi, $teams['kedungbulus'], null, 'planned', null);

        unset($admin, $bm, $spv, $sas, $sap, $coordKed, $coordKuw, $salesCase);
    }

    private function globalUser(string $email, string $name, ?Branch $branch, string $roleName, string $password): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'branch_id' => $branch?->id,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->whereNull('team_id')->where('name', $roleName)->firstOrFail();
        $user->globalRoles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function teamUser(string $email, string $name, Branch $branch, Team $team, string $roleName, string $password): User
    {
        $user = User::query()->updateOrCreate(['email' => $email], [
            'name' => $name,
            'branch_id' => $branch->id,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);
        $team->users()->syncWithoutDetaching([$user->id => ['is_default' => true]]);
        setPermissionsTeamId($team->id);
        $role = Role::query()->where('team_id', $team->id)->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    private function seedProjectStructure(Project $project): void
    {
        $block = ProjectBlock::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'A'],
            ['sort_order' => 1, 'is_active' => true],
        );
        Lot::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'A01'],
            ['project_block_id' => $block->id, 'number' => 1, 'status' => LotStatus::Available],
        );
        Lot::query()->updateOrCreate(
            ['project_id' => $project->id, 'code' => 'A02'],
            ['project_block_id' => $block->id, 'number' => 2, 'status' => LotStatus::Available],
        );
    }

    private function seedSalesCase(Project $project, Promotion $promotion, User $creator): SalesCase
    {
        $lot = Lot::query()->where('project_id', $project->id)->where('code', 'A01')->firstOrFail();
        $consumer = Consumer::query()->updateOrCreate(['no_ktp' => 'UAT-3273010101010001'], [
            'nama_konsumen' => 'UAT Konsumen Proses',
            'tanggal_lahir' => '1990-01-01',
            'pekerjaan' => 'Karyawan Swasta',
            'detail_pekerjaan' => 'UAT Company',
            'alamat' => 'Alamat UAT',
            'kelurahan' => 'UAT',
            'kecamatan' => 'UAT',
            'kabupaten_kota' => 'UAT',
            'no_hp' => '081234000001',
            'nama_kontak_darurat' => 'UAT Kontak',
            'no_hp_kontak_darurat' => '081234000002',
        ]);

        return SalesCase::query()->updateOrCreate(
            ['consumer_id' => $consumer->id, 'lot_id' => $lot->id],
            ['promotion_id' => $promotion->id, 'payment_method' => PaymentMethod::Cash, 'created_by' => $creator->id, 'notes' => 'Data proses untuk UAT.'],
        );
    }

    /** @param array{id: string, date: string, source: string, channel: string, activity: string, name: string, phone: string, status: string, sales: User, project: Project, promo: Promotion} $data */
    private function seedLead(array $data, User $creator): Lead
    {
        $lead = Lead::query()->updateOrCreate(['id_lead' => $data['id']], [
            'id_promo' => $data['promo']->promo_id,
            'nama_promo' => $data['promo']->name,
            'tanggal_lead' => $data['date'],
            'sumber_lead' => $data['source'],
            'kanal_masuk' => $data['channel'],
            'aktivitas_lead' => $data['activity'],
            'nama_konsumen' => $data['name'],
            'no_hp' => $data['phone'],
            'project_id' => $data['project']->id,
            'branch_id' => $data['sales']->branch_id,
            'team_id' => $data['sales']->teams()->first()?->id,
            'sales_pic_id' => $data['sales']->id,
            'created_by' => $creator->id,
            'status_lead' => $data['status'],
            'keterangan' => 'Data Lead UAT.',
        ]);
        LeadActivity::query()->updateOrCreate(['lead_id' => $lead->id, 'activity_type' => 'Lead Baru'], [
            'user_id' => $creator->id,
            'description' => 'Lead UAT dibuat.',
            'status_to' => 'No Respon',
            'happened_at_source' => 'system',
            'happened_at' => BusinessTime::fromInput($data['date'].'T09:00'),
        ]);

        if ($data['status'] !== 'No Respon') {
            LeadActivity::query()->updateOrCreate(['lead_id' => $lead->id, 'activity_type' => 'Status Update'], [
                'user_id' => $creator->id,
                'description' => 'Status UAT diperbarui.',
                'status_from' => 'No Respon',
                'status_to' => $data['status'],
                'happened_at_source' => 'system',
                'happened_at' => BusinessTime::fromInput($data['date'].'T14:00'),
            ]);
        }

        return $lead;
    }

    private function seedAgenda(string $title, string $date, string $category, User $creator, Team $team, ?string $leadId, string $status, ?string $result): SalesAgenda
    {
        $lead = $leadId ? Lead::query()->where('id_lead', $leadId)->first() : null;
        $agenda = SalesAgenda::query()
            ->where('title', $title)
            ->where('created_by', $creator->id)
            ->whereDate('agenda_date', $date)
            ->first() ?? new SalesAgenda;
        $agenda->fill([
            'branch_id' => $creator->branch_id,
            'team_id' => $team->id,
            'lead_id' => $lead?->id,
            'sales_case_id' => null,
            'agenda_date' => $date,
            'activity_category' => $category,
            'title' => $title,
            'location' => 'Lokasi UAT',
            'activity_result' => $result,
            'status' => $status,
            'completed_at' => $status === 'done' ? BusinessTime::fromInput($date.'T17:00') : null,
            'created_by' => $creator->id,
            'owner_id' => $creator->id,
        ]);
        $agenda->save();

        return $agenda;
    }
}
