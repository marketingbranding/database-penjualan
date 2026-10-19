<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadCutoverDisposition;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OasisCrmMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_connection_cannot_be_the_target_connection(): void
    {
        $this->artisan('migration:import-oasis', ['--source' => 'sqlite'])
            ->assertFailed()
            ->expectsOutputToContain('must not be the OASIS Next target connection');
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.legacy_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);
        DB::purge('legacy_test');
        $schema = Schema::connection('legacy_test');
        $schema->create('branches', function ($table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active');
            $table->timestamps();
        });
        $schema->create('lead_master', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->string('project_name');
            $table->boolean('is_active');
            $table->timestamps();
        });
        $schema->create('roles', function ($table): void {
            $table->id();
            $table->string('slug');
        });
        $schema->create('permissions', function ($table): void {
            $table->id();
        });
        $schema->create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('supervisor_user_id')->nullable();
            $table->unsignedBigInteger('role_id');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        $schema->create('sales_leads', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('sales_user_id');
            $table->unsignedBigInteger('created_by');
            $table->string('source');
            $table->string('platform');
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->string('campaign_name')->nullable();
            $table->string('id_promo')->nullable();
            $table->date('lead_date');
            $table->string('current_status');
            $table->text('notes')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        $schema->create('sales_lead_status_histories', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('sales_lead_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('status');
            $table->timestamp('changed_at');
            $table->timestamps();
        });
        $schema->create('content_items', function ($table): void {
            $table->id();
            $table->string('item_type');
            $table->string('agenda_type');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('created_by');
            $table->date('scheduled_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('deadline_date')->nullable();
            $table->string('sales_activity_category')->nullable();
            $table->string('title')->nullable();
            $table->string('location')->nullable();
            $table->text('activity_result')->nullable();
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('task_detail')->nullable();
            $table->timestamps();
        });
        $schema->create('sales_agenda_evidences', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('content_item_id');
            $table->string('file_path')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function test_delta_rehearsal_updates_existing_lead_imports_new_lead_and_activity_once(): void
    {
        $this->seedLegacySnapshot();

        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $branch = DB::table('branches')->first();
        $salesOne = User::query()->where('email', 'sales-one@example.test')->firstOrFail();
        $salesTwo = User::query()->where('email', 'sales-two@example.test')->firstOrFail();
        $teamOne = $this->createFinalTeam($branch->id, $salesOne, 1);
        $teamTwo = $this->createFinalTeam($branch->id, $salesTwo, 2);

        $localLead = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();
        DB::table('leads')->where('id', $localLead->id)->update([
            'qualification_status' => 'qualified',
            'next_follow_up_note' => 'OASIS Next-only note',
        ]);
        $beforeCreatedBy = $localLead->created_by;
        $beforeCreatedAt = $localLead->created_at;

        DB::connection('legacy_test')->table('sales_leads')->where('id', 1)->update([
            'sales_user_id' => 2,
            'current_status' => 'site_visit',
            'notes' => 'Changed in CRM',
            'updated_at' => '2026-10-19 11:00:00',
        ]);
        DB::connection('legacy_test')->table('sales_leads')->insert([
            'id' => 2,
            'branch_id' => 1,
            'project_id' => 1,
            'sales_user_id' => 2,
            'created_by' => 2,
            'source' => 'Online',
            'platform' => 'TikTok',
            'customer_name' => 'New CRM Lead',
            'phone' => '081234567892',
            'campaign_name' => 'New Campaign',
            'id_promo' => null,
            'lead_date' => '2026-10-19',
            'current_status' => 'discussion',
            'notes' => null,
            'deleted_at' => null,
            'created_at' => '2026-10-19 10:30:00',
            'updated_at' => '2026-10-19 10:30:00',
        ]);
        DB::connection('legacy_test')->table('sales_lead_status_histories')->insert([
            'id' => 2,
            'sales_lead_id' => 1,
            'actor_id' => 2,
            'status' => 'site_visit',
            'changed_at' => '2026-10-19 11:00:00',
            'created_at' => '2026-10-19 11:00:00',
            'updated_at' => '2026-10-19 11:00:00',
        ]);

        $arguments = [
            '--source' => 'legacy_test',
            '--apply' => true,
            '--delta' => true,
            '--since' => '2026-10-19 10:00:00',
            '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ];
        $this->artisan('migration:import-oasis', $arguments)->assertSuccessful();

        $updatedLead = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();
        $newLead = DB::table('leads')->where('id_lead', 'CRM-2')->firstOrFail();
        $this->assertSame($salesTwo->id, $updatedLead->sales_pic_id);
        $this->assertSame($teamTwo->id, $updatedLead->team_id);
        $this->assertSame($teamTwo->id, $newLead->team_id);
        $this->assertSame($beforeCreatedBy, $updatedLead->created_by);
        $this->assertSame($beforeCreatedAt, $updatedLead->created_at);
        $this->assertSame('qualified', $updatedLead->qualification_status);
        $this->assertSame('OASIS Next-only note', $updatedLead->next_follow_up_note);
        $this->assertSame(2, DB::table('lead_activities')->where('lead_id', $updatedLead->id)->count());
        $this->assertSame(2, DB::table('legacy_migration_maps')->where('entity', 'lead_activity')->count());

        $beforeSecondRun = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();
        $this->artisan('migration:import-oasis', $arguments)->assertSuccessful();
        $this->assertSame(2, DB::table('leads')->count());
        $this->assertSame(2, DB::table('lead_activities')->where('lead_id', $updatedLead->id)->count());
        $this->assertEquals((array) $beforeSecondRun, (array) DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail());
    }

    public function test_full_import_counts_identical_existing_leads_as_unchanged_without_restamping_maps(): void
    {
        $this->seedLegacySnapshot();

        $arguments = [
            '--source' => 'legacy_test',
            '--apply' => true,
            '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ];
        $this->artisan('migration:import-oasis', $arguments)->assertSuccessful();

        $leadBefore = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();
        $mapBefore = DB::table('legacy_migration_maps')->where('entity', 'lead')->where('source_id', 1)->firstOrFail();
        $reportPath = storage_path('app/full-import-idempotency-test.json');

        try {
            $this->artisan('migration:import-oasis', [...$arguments, '--report' => $reportPath])->assertSuccessful();
            $report = json_decode(File::get($reportPath), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(0, $report['counts']['leads_inserted']);
            $this->assertSame(0, $report['counts']['leads_updated']);
            $this->assertSame(1, $report['counts']['leads_unchanged']);
            $this->assertSame(1, $report['counts']['lead_activities_unchanged']);
            $this->assertSame(7, $report['counts']['migration_maps_unchanged']);
            $this->assertSame((array) $leadBefore, (array) DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail());
            $this->assertSame((array) $mapBefore, (array) DB::table('legacy_migration_maps')->where('entity', 'lead')->where('source_id', 1)->firstOrFail());
        } finally {
            File::delete($reportPath);
        }
    }

    public function test_full_import_updates_only_the_changed_legacy_lead_field(): void
    {
        $this->seedLegacySnapshot();
        $arguments = [
            '--source' => 'legacy_test',
            '--apply' => true,
            '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ];
        $this->artisan('migration:import-oasis', $arguments)->assertSuccessful();
        $leadBefore = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();

        DB::connection('legacy_test')->table('sales_leads')->where('id', 1)->update([
            'notes' => 'Changed in CRM',
            'updated_at' => '2026-10-19 11:00:00',
        ]);
        $reportPath = storage_path('app/full-import-change-test.json');

        try {
            $this->artisan('migration:import-oasis', [...$arguments, '--report' => $reportPath])->assertSuccessful();
            $report = json_decode(File::get($reportPath), true, 512, JSON_THROW_ON_ERROR);
            $leadAfter = DB::table('leads')->where('id_lead', 'CRM-1')->firstOrFail();

            $this->assertSame(1, $report['counts']['leads_updated']);
            $this->assertSame(0, $report['counts']['leads_unchanged']);
            $this->assertSame('Changed in CRM', $leadAfter->keterangan);
            $this->assertSame($leadBefore->updated_at, $leadAfter->updated_at);
        } finally {
            File::delete($reportPath);
        }
    }

    public function test_full_import_preserves_a_normalized_lead_team_without_legacy_membership_reinsertion(): void
    {
        $this->seedLegacySnapshot();
        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $branch = DB::table('branches')->firstOrFail();
        $sales = User::query()->where('email', 'sales-one@example.test')->firstOrFail();
        $finalTeam = $this->createFinalTeam($branch->id, $sales, 1);
        $legacyTeamId = DB::table('teams')->where('code', 'LEGACY-B01')->value('id');
        DB::table('model_has_roles')->where('team_id', $legacyTeamId)->where('model_id', $sales->id)->delete();
        DB::table('team_user')->where('team_id', $legacyTeamId)->where('user_id', $sales->id)->delete();
        DB::table('leads')->where('id_lead', 'CRM-1')->update(['team_id' => $finalTeam->id]);
        $membershipCount = DB::table('team_user')->count();

        $reportPath = storage_path('app/full-import-normalized-team-test.json');
        try {
            $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true, '--report' => $reportPath])->assertSuccessful();
            $report = json_decode(File::get($reportPath), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(0, $report['counts']['leads_updated']);
            $this->assertSame($finalTeam->id, DB::table('leads')->where('id_lead', 'CRM-1')->value('team_id'));
            $this->assertSame($membershipCount, DB::table('team_user')->count());
            $this->assertSame(0, DB::table('team_user')->where('team_id', $legacyTeamId)->where('user_id', $sales->id)->count());
            $this->assertSame(0, DB::table('model_has_roles')->where('team_id', $legacyTeamId)->where('model_id', $sales->id)->count());
        } finally {
            File::delete($reportPath);
        }
    }

    public function test_full_import_preserves_a_normalized_agenda_team(): void
    {
        $this->seedLegacySnapshot();
        DB::connection('legacy_test')->table('content_items')->insert([
            'id' => 1,
            'item_type' => 'agenda',
            'agenda_type' => 'buku_saku_sales',
            'branch_id' => 1,
            'owner_user_id' => 1,
            'created_by' => 1,
            'scheduled_date' => '2026-10-19',
            'start_date' => null,
            'deadline_date' => null,
            'sales_activity_category' => 'Administrasi',
            'title' => 'Agenda One',
            'location' => 'Office',
            'activity_result' => 'Planned',
            'status' => 'planned',
            'completed_at' => null,
            'notes' => null,
            'task_detail' => null,
            'created_at' => '2026-10-19 09:30:00',
            'updated_at' => '2026-10-19 09:30:00',
        ]);
        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $branch = DB::table('branches')->firstOrFail();
        $sales = User::query()->where('email', 'sales-one@example.test')->firstOrFail();
        $finalTeam = $this->createFinalTeam($branch->id, $sales, 1);
        DB::table('sales_agendas')->where('id', DB::table('sales_agendas')->where('title', 'Agenda One')->value('id'))->update(['team_id' => $finalTeam->id]);
        $agendaBefore = DB::table('sales_agendas')->where('title', 'Agenda One')->firstOrFail();

        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();
        $agendaAfter = DB::table('sales_agendas')->where('title', 'Agenda One')->firstOrFail();

        $this->assertSame((array) $agendaBefore, (array) $agendaAfter);
        $this->assertSame($finalTeam->id, $agendaAfter->team_id);
    }

    public function test_agenda_import_resolves_coordinator_owner_to_the_coordinator_final_team(): void
    {
        $this->seedLegacySnapshot();
        DB::connection('legacy_test')->table('roles')->insert(['id' => 2, 'slug' => 'sales_coordinator']);
        DB::connection('legacy_test')->table('users')->insert([
            'id' => 3, 'name' => 'Coordinator Fixture', 'email' => 'coordinator-fixture@example.test', 'password' => 'hashed-three',
            'branch_id' => 1, 'role_id' => 2, 'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
        ]);
        DB::connection('legacy_test')->table('content_items')->insert([
            'id' => 5520, 'item_type' => 'agenda', 'agenda_type' => 'buku_saku_sales', 'branch_id' => 1,
            'owner_user_id' => 3, 'created_by' => 3, 'scheduled_date' => '2026-10-28',
            'start_date' => null, 'deadline_date' => null, 'sales_activity_category' => 'Administrasi',
            'title' => 'Coordinator fixture agenda', 'location' => null, 'activity_result' => null,
            'status' => 'planned', 'completed_at' => null, 'notes' => null, 'task_detail' => null,
            'created_at' => '2026-10-01 08:01:29', 'updated_at' => '2026-10-01 08:01:29',
        ]);

        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $coordinator = User::query()->where('email', 'coordinator-fixture@example.test')->firstOrFail();
        $finalTeam = Team::query()->create([
            'parent_id' => null, 'branch_id' => 1, 'project_id' => null,
            'supervisor_id' => null, 'coordinator_id' => $coordinator->id,
            'code' => 'COORD-B01-U'.$coordinator->id, 'name' => 'Coordinator Fixture Team', 'is_active' => true,
        ]);
        Rbac::provisionTeam($finalTeam);
        $finalTeam->users()->attach($coordinator->id, ['is_default' => true]);
        $role = Role::query()->where('team_id', $finalTeam->id)->where('name', 'koordinator_sales')->firstOrFail();
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id, 'model_type' => User::class, 'model_id' => $coordinator->id, 'team_id' => $finalTeam->id,
        ]);
        DB::connection('legacy_test')->table('content_items')->where('id', 5520)->update(['updated_at' => '2026-10-20 08:00:00']);

        $this->artisan('migration:import-oasis', ['--source' => 'legacy_test', '--apply' => true])->assertSuccessful();

        $agenda = DB::table('sales_agendas')->where('id', DB::table('legacy_migration_maps')->where('entity', 'agenda')->where('source_id', 5520)->value('target_id'))->firstOrFail();

        $this->assertSame($coordinator->id, $agenda->owner_id);
        $this->assertSame($finalTeam->id, $agenda->team_id);
    }

    public function test_coordinator_pic_is_imported_only_when_sales_owner_is_confirmed_by_history(): void
    {
        $this->seedLegacySnapshot();
        DB::connection('legacy_test')->table('roles')->insert(['id' => 2, 'slug' => 'sales_coordinator']);
        DB::connection('legacy_test')->table('users')->insert([
            'id' => 3, 'name' => 'Coordinator', 'email' => 'coordinator@example.test', 'password' => 'hashed-three',
            'branch_id' => 1, 'role_id' => 2, 'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
        ]);
        DB::connection('legacy_test')->table('sales_leads')->insert([
            'id' => 2, 'branch_id' => 1, 'project_id' => 1, 'sales_user_id' => 3, 'created_by' => 1,
            'source' => 'Online', 'platform' => 'TikTok', 'customer_name' => 'Recovered CRM Lead',
            'phone' => '081234567892', 'campaign_name' => null, 'id_promo' => null, 'lead_date' => '2026-10-19',
            'current_status' => 'discussion', 'notes' => null, 'deleted_at' => null,
            'created_at' => '2026-10-19 10:30:00', 'updated_at' => '2026-10-19 10:30:00',
        ]);
        DB::connection('legacy_test')->table('sales_lead_status_histories')->insert([
            'id' => 2, 'sales_lead_id' => 2, 'actor_id' => 1, 'status' => 'discussion',
            'changed_at' => '2026-10-19 10:30:00', 'created_at' => '2026-10-19 10:30:00', 'updated_at' => '2026-10-19 10:30:00',
        ]);

        $this->artisan('migration:import-oasis', [
            '--source' => 'legacy_test', '--apply' => true, '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ])->assertSuccessful()->expectsOutputToContain('lead_coordinator_pic_recovered');

        $sales = User::query()->where('email', 'sales-one@example.test')->firstOrFail();
        $this->assertDatabaseHas('leads', ['id_lead' => 'CRM-2', 'sales_pic_id' => $sales->id, 'created_by' => $sales->id]);
    }

    public function test_unresolved_coordinator_pic_is_preserved_as_an_auditable_excluded_record(): void
    {
        $this->seedLegacySnapshot();
        DB::connection('legacy_test')->table('roles')->insert(['id' => 2, 'slug' => 'sales_coordinator']);
        DB::connection('legacy_test')->table('users')->insert([
            'id' => 3, 'name' => 'Coordinator', 'email' => 'coordinator@example.test', 'password' => 'hashed-three',
            'branch_id' => 1, 'role_id' => 2, 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00',
        ]);
        DB::connection('legacy_test')->table('sales_leads')->insert([
            'id' => 2, 'branch_id' => 1, 'project_id' => 1, 'sales_user_id' => 3, 'created_by' => 3,
            'source' => 'Online', 'platform' => 'TikTok', 'customer_name' => 'Unresolved CRM Lead',
            'phone' => '081234567892', 'campaign_name' => null, 'id_promo' => null, 'lead_date' => '2026-09-01',
            'current_status' => 'discussion', 'notes' => null, 'deleted_at' => null,
            'created_at' => '2026-09-01 10:30:00', 'updated_at' => '2026-09-01 10:30:00',
        ]);

        $this->artisan('migration:import-oasis', [
            '--source' => 'legacy_test', '--apply' => true, '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ])->assertFailed()->expectsOutputToContain('lead_coordinator_pic_excluded_from_cutover');

        $targetLead = DB::table('leads')->where('id_lead', 'CRM-2')->firstOrFail();
        $this->assertNull($targetLead->sales_pic_id);
        $this->assertDatabaseHas('lead_cutover_dispositions', [
            'lead_id' => $targetLead->id,
            'classification' => LeadCutoverDisposition::EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED,
        ]);
        $this->assertNull(Lead::query()->whereKey($targetLead->id)->first());
        $this->assertNotNull(Lead::withoutGlobalScopes()->find($targetLead->id));
        $this->assertDatabaseHas('leads', ['id_lead' => 'CRM-1']);

        $disposition = DB::table('lead_cutover_dispositions')->where('lead_id', $targetLead->id)->first();
        $this->artisan('migration:import-oasis', [
            '--source' => 'legacy_test', '--apply' => true, '--freeze-at' => '2026-10-19 12:00:00',
            '--timezone' => 'Asia/Jakarta',
        ])->assertFailed();
        $this->assertEquals((array) $disposition, (array) DB::table('lead_cutover_dispositions')->where('lead_id', $targetLead->id)->first());
    }

    private function seedLegacySnapshot(): void
    {
        DB::connection('legacy_test')->table('branches')->insert([
            'id' => 1, 'code' => 'B01', 'name' => 'Branch One', 'is_active' => true,
            'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
        ]);
        DB::connection('legacy_test')->table('lead_master')->insert([
            'id' => 1, 'branch_id' => 1, 'project_name' => 'Project One', 'is_active' => true,
            'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
        ]);
        DB::connection('legacy_test')->table('roles')->insert(['id' => 1, 'slug' => 'staff']);
        DB::connection('legacy_test')->table('users')->insert([
            [
                'id' => 1, 'name' => 'Sales One', 'email' => 'sales-one@example.test', 'password' => 'hashed-one',
                'branch_id' => 1, 'role_id' => 1, 'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
            ],
            [
                'id' => 2, 'name' => 'Sales Two', 'email' => 'sales-two@example.test', 'password' => 'hashed-two',
                'branch_id' => 1, 'role_id' => 1, 'created_at' => '2026-10-19 09:00:00', 'updated_at' => '2026-10-19 09:00:00',
            ],
        ]);
        DB::connection('legacy_test')->table('sales_leads')->insert([
            'id' => 1, 'branch_id' => 1, 'project_id' => 1, 'sales_user_id' => 1, 'created_by' => 1,
            'source' => 'Online', 'platform' => 'TikTok', 'customer_name' => 'Existing CRM Lead',
            'phone' => '081234567891', 'campaign_name' => 'Campaign', 'id_promo' => null,
            'lead_date' => '2026-10-19', 'current_status' => 'discussion', 'notes' => 'Original', 'deleted_at' => null,
            'created_at' => '2026-10-19 09:30:00', 'updated_at' => '2026-10-19 09:30:00',
        ]);
        DB::connection('legacy_test')->table('sales_lead_status_histories')->insert([
            'id' => 1, 'sales_lead_id' => 1, 'actor_id' => 1, 'status' => 'discussion',
            'changed_at' => '2026-10-19 09:30:00', 'created_at' => '2026-10-19 09:30:00', 'updated_at' => '2026-10-19 09:30:00',
        ]);
    }

    private function createFinalTeam(int $branchId, User $sales, int $number): Team
    {
        $team = Team::query()->create([
            'parent_id' => null, 'branch_id' => $branchId, 'project_id' => null,
            'supervisor_id' => null, 'coordinator_id' => null, 'code' => 'COORD-B01-U'.$number,
            'name' => 'Final Team '.$number, 'is_active' => true,
        ]);
        Rbac::provisionTeam($team);
        $team->users()->attach($sales->id, ['is_default' => true]);
        $role = Role::query()->where('team_id', $team->id)->where('name', 'sales')->firstOrFail();
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id, 'model_type' => User::class, 'model_id' => $sales->id, 'team_id' => $team->id,
        ]);

        return $team;
    }
}
