<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OasisCrmMigrationService
{
    /** @var list<string> */
    private const REQUIRED_SOURCE_TABLES = [
        'branches',
        'lead_master',
        'roles',
        'permissions',
        'users',
        'sales_leads',
        'sales_lead_status_histories',
        'content_items',
        'sales_agenda_evidences',
    ];

    /** @var array<string, string> */
    private const ROLE_MAP = [
        'superadmin' => 'super_admin',
        'admin' => 'bm',
        'manager' => 'spv',
        'staff' => 'sales',
        'pusat' => 'pusat',
        'sales' => 'sales',
        'sales_coordinator' => 'koordinator_sales',
        'supervisor' => 'spv',
        'branch_manager' => 'bm',
    ];

    /** @var array<string, string> */
    private const CHANNEL_MAP = [
        'TikTok' => 'TikTok',
        'Facebook' => 'Facebook',
        'Instagram' => 'Instagram',
        'WhatsApp' => 'WhatsApp',
        'Canvassing' => 'Canvassing',
        'Marketplace' => 'Marketplace',
        'Pameran' => 'Pameran',
        'Event' => 'Event',
        'Telepon' => 'Telepon',
        'Datang Langsung' => 'Datang Langsung',
        'Website' => 'Website',
    ];

    /** @var array<string, string> */
    private const STATUS_MAP = [
        'discussion' => 'Diskusi',
        'no_response' => 'No Respon',
        'site_visit' => 'Cek Lokasi',
        'face_to_face' => 'Tatap Muka',
        'utj' => 'UTJ',
        'slik_check' => 'Cek SLIK',
        'slik_rejected' => 'Tidak Lolos BI Checking',
        'akad' => 'Akad',
        'freelance' => 'Jadi Freelance',
    ];

    /** @var array<string> */
    private const SAFE_STATUSES = ['discussion', 'no_response', 'site_visit', 'face_to_face'];

    /** @var array<string> */
    private const PROCESS_STATUSES = ['utj', 'slik_check', 'slik_rejected', 'akad', 'freelance'];

    private Connection $source;

    private Connection $target;

    private string $sourceConnectionName;

    private bool $apply;

    private bool $copyEvidence;

    private bool $delta;

    private ?CarbonImmutable $since;

    private ?CarbonImmutable $freezeAt;

    private string $sourceTimezone;

    private OutputStyle $output;

    /** @var array<string, array<int, int>> */
    private array $targetIds = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $mapMetadata = [];

    /** @var array<string, array<int, string>> */
    private array $pendingSourceTimestamps = [];

    /** @var array<int, object> */
    private array $sourceUsers = [];

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<int, int> */
    private array $branchIds = [];

    /** @var array<int, int> */
    private array $projectIds = [];

    /** @var array<int, int> */
    private array $teamIds = [];

    /** @var array<int, string> */
    private array $roleNames = [];

    /** @var array<int, array<int, object>> */
    private array $historiesByLead = [];

    /** @var array<string, mixed> */
    private array $report = [];

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $sourceConnection,
        bool $apply,
        bool $copyEvidence,
        OutputStyle $output,
        ?string $reportPath = null,
        bool $delta = false,
        ?string $since = null,
        ?string $freezeAt = null,
        string $timezone = 'Asia/Jakarta',
    ): array {
        $this->sourceConnectionName = $sourceConnection;
        $targetConnectionName = (string) config('database.default');
        if ($sourceConnection === $targetConnectionName) {
            throw new RuntimeException('The legacy source connection must not be the OASIS Next target connection.');
        }
        $this->source = DB::connection($sourceConnection);
        $this->target = DB::connection($targetConnectionName);
        if (! app()->environment('testing') && $this->sourceAndTargetShareDatabaseIdentity($sourceConnection, $targetConnectionName)) {
            throw new RuntimeException('The legacy source and OASIS Next target resolve to the same database identity.');
        }
        $this->targetIds = [];
        $this->mapMetadata = [];
        $this->pendingSourceTimestamps = [];
        $this->sourceUsers = [];
        $this->userIds = [];
        $this->branchIds = [];
        $this->projectIds = [];
        $this->teamIds = [];
        $this->roleNames = [];
        $this->historiesByLead = [];
        $this->apply = $apply;
        $this->copyEvidence = $copyEvidence;
        $this->delta = $delta;
        $this->sourceTimezone = $timezone;
        $this->since = $since !== null ? CarbonImmutable::parse($since, $timezone) : null;
        $this->freezeAt = $freezeAt !== null ? CarbonImmutable::parse($freezeAt, $timezone) : null;
        if ($this->delta && ($this->since === null || $this->freezeAt === null)) {
            throw new RuntimeException('Delta mode requires both --since and --freeze-at.');
        }
        if ($this->since !== null && $this->freezeAt !== null && $this->freezeAt->lessThanOrEqualTo($this->since)) {
            throw new RuntimeException('--freeze-at must be later than --since.');
        }
        $this->output = $output;
        $this->report = [
            'started_at' => CarbonImmutable::now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'source_connection' => $sourceConnection,
            'cutover' => [
                'timezone' => $timezone,
                'source_freeze_at' => $this->freezeAt?->toIso8601String(),
                'previous_sync_at' => $this->since?->toIso8601String(),
                'migration_started_at' => CarbonImmutable::now($timezone)->toIso8601String(),
                'migration_finished_at' => null,
            ],
            'delta' => $delta,
            'policy' => [
                'consumers' => 'excluded by request',
                'sales_process' => 'UTJ through BAST excluded',
                'legacy_spreadsheet_records' => 'excluded; source table is never queried',
                'unlinked_agendas' => 'imported without lead_id and listed in report',
                'permissions' => 'canonical Oasis Next permissions are provisioned; legacy permission rows are not copied',
            ],
            'counts' => [],
            'issues' => [
                'ambiguous' => [],
                'skipped' => [],
                'warnings' => [],
            ],
            'report_path' => null,
            'has_blocking_issues' => false,
        ];

        $this->validateSourceSchema();
        $this->loadExistingMaps();
        $this->validateEvidenceOptions();
        $this->output->writeln('<comment>Reading legacy Oasis data in '.($apply ? 'apply' : 'dry-run').' mode...</comment>');

        $work = function (): void {
            $this->migrateBranches();
            $this->migrateProjects();
            $this->migrateTeams();
            $this->migrateRoles();
            $this->migrateUsers();
            if (! $this->delta) {
                $this->updateUserHierarchy();
                $this->assignUsersToTeamsAndRoles();
            }
            $this->prepareLeadHistories();
            $this->migrateLeads();
            $this->migrateLeadActivities();
            $this->migrateAgendas();
            $this->migrateAgendaEvidence();
        };

        if ($apply) {
            $this->target->transaction($work);
        } else {
            $work();
        }

        $this->report['finished_at'] = CarbonImmutable::now()->toIso8601String();
        $this->report['cutover']['migration_finished_at'] = CarbonImmutable::now($timezone)->toIso8601String();
        $this->recordReconciliation();
        $this->writeReport($reportPath);

        return $this->report;
    }

    private function loadExistingMaps(): void
    {
        if (! $this->target->getSchemaBuilder()->hasTable('legacy_migration_maps')) {
            throw new RuntimeException('Target migration is incomplete. Run php artisan migrate first.');
        }

        foreach ($this->target->table('legacy_migration_maps')
            ->where('source_connection', $this->sourceConnectionName)
            ->get(['entity', 'source_id', 'target_id', 'metadata']) as $map) {
            $this->targetIds[(string) $map->entity][(int) $map->source_id] = (int) $map->target_id;
            $this->mapMetadata[(string) $map->entity][(int) $map->source_id] = is_string($map->metadata) && $map->metadata !== ''
                ? (json_decode($map->metadata, true, 512, JSON_THROW_ON_ERROR) ?: [])
                : [];
        }
    }

    private function validateSourceSchema(): void
    {
        $missing = array_values(array_filter(
            self::REQUIRED_SOURCE_TABLES,
            fn (string $table): bool => ! $this->source->getSchemaBuilder()->hasTable($table),
        ));
        if ($missing !== []) {
            throw new RuntimeException('Legacy source schema is incomplete. Missing tables: '.implode(', ', $missing));
        }
    }

    private function sourceAndTargetShareDatabaseIdentity(string $sourceConnection, string $targetConnection): bool
    {
        $sourceConfig = (array) config('database.connections.'.$sourceConnection, []);
        $targetConfig = (array) config('database.connections.'.$targetConnection, []);
        foreach (['driver', 'host', 'port', 'database', 'unix_socket'] as $key) {
            if (($sourceConfig[$key] ?? null) !== ($targetConfig[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function migrateBranches(): void
    {
        foreach ($this->source->table('branches')->orderBy('id')->get() as $branch) {
            $sourceId = (int) $branch->id;
            if (! $this->shouldProcessSourceRow('branch', $sourceId, $branch)) {
                if (isset($this->targetIds['branch'][$sourceId])) {
                    $this->branchIds[$sourceId] = $this->targetIds['branch'][$sourceId];
                }

                continue;
            }

            $targetId = $this->upsertMapped(
                'branch',
                $sourceId,
                fn (): ?int => $this->target->table('branches')->where('code', $branch->code)->value('id'),
                [
                    'code' => (string) $branch->code,
                    'name' => (string) $branch->name,
                    'is_active' => (bool) $branch->is_active,
                    'created_at' => $branch->created_at,
                    'updated_at' => $branch->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->branchIds[$sourceId] = $targetId;
            }
        }

        $this->count('branches', count($this->branchIds));
    }

    private function migrateProjects(): void
    {
        foreach ($this->source->table('lead_master')->orderBy('id')->get() as $project) {
            $sourceId = (int) $project->id;
            if (! $this->shouldProcessSourceRow('project', $sourceId, $project)) {
                if (isset($this->targetIds['project'][$sourceId])) {
                    $this->projectIds[$sourceId] = $this->targetIds['project'][$sourceId];
                }

                continue;
            }

            $branchId = $this->branchIds[(int) $project->branch_id] ?? null;
            if ($branchId === null) {
                $this->skip('project', (int) $project->id, 'source branch is not mapped');

                continue;
            }

            $code = 'LEGACY-LM-'.((int) $project->id);
            $targetId = $this->upsertMapped(
                'project',
                $sourceId,
                fn (): ?int => $this->target->table('projects')->where('code', $code)->value('id'),
                [
                    'branch_id' => $branchId,
                    'code' => $code,
                    'name' => (string) $project->project_name,
                    'is_active' => (bool) $project->is_active,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->projectIds[$sourceId] = $targetId;
            }
        }

        $this->count('projects', count($this->projectIds));
    }

    private function migrateTeams(): void
    {
        foreach ($this->source->table('branches')->orderBy('id')->get() as $branch) {
            $sourceId = (int) $branch->id;
            if (! $this->shouldProcessSourceRow('team', $sourceId, $branch)) {
                if (isset($this->targetIds['team'][$sourceId])) {
                    $this->teamIds[$sourceId] = $this->targetIds['team'][$sourceId];
                }

                continue;
            }

            $branchId = $this->branchIds[(int) $branch->id] ?? null;
            if ($branchId === null) {
                continue;
            }

            $code = 'LEGACY-'.Str::upper((string) $branch->code);
            $targetId = $this->upsertMapped(
                'team',
                $sourceId,
                fn (): ?int => $this->target->table('teams')->where('code', $code)->value('id'),
                [
                    'parent_id' => null,
                    'branch_id' => $branchId,
                    'project_id' => null,
                    'supervisor_id' => null,
                    'coordinator_id' => null,
                    'code' => $code,
                    'name' => 'Legacy '.$branch->name,
                    'is_active' => (bool) $branch->is_active,
                    'created_at' => $branch->created_at,
                    'updated_at' => $branch->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->teamIds[$sourceId] = $targetId;
            }
        }

        $this->count('teams', count($this->teamIds));
    }

    private function migrateRoles(): void
    {
        if ($this->apply && ! $this->delta) {
            Rbac::provision();

            foreach ($this->teamIds as $teamId) {
                Rbac::provisionTeam(Team::query()->findOrFail($teamId));
            }
        }

        foreach ($this->source->table('roles')->orderBy('id')->get() as $role) {
            $mappedRole = self::ROLE_MAP[(string) $role->slug] ?? null;
            $this->roleNames[(int) $role->id] = $mappedRole ?? '';

            if ($mappedRole === null) {
                $this->ambiguous('role', (int) $role->id, 'no Oasis Next role mapping', ['slug' => $role->slug]);

                continue;
            }

            $this->count('role_mappings');
        }

        $this->count('legacy_permissions_excluded', (int) $this->source->table('permissions')->count());
    }

    private function migrateUsers(): void
    {
        $this->sourceUsers = $this->source->table('users')->orderBy('id')->get()->keyBy('id')->all();

        foreach ($this->sourceUsers as $user) {
            $sourceId = (int) $user->id;
            if (! $this->shouldProcessSourceRow('user', $sourceId, $user)) {
                if (isset($this->targetIds['user'][$sourceId])) {
                    $this->userIds[$sourceId] = $this->targetIds['user'][$sourceId];
                }

                continue;
            }

            $branchId = $user->branch_id !== null ? ($this->branchIds[(int) $user->branch_id] ?? null) : null;
            $attributes = [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => (string) $user->password,
                'remember_token' => $user->remember_token,
                'branch_id' => $branchId,
                'reports_to_id' => null,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
            $updateAttributes = $attributes;
            unset($updateAttributes['reports_to_id'], $updateAttributes['created_at'], $updateAttributes['updated_at']);
            $targetId = $this->upsertMapped(
                'user',
                $sourceId,
                fn (): ?int => $this->target->table('users')->where('email', $user->email)->value('id'),
                $attributes,
                ['source_updated_at' => $user->updated_at],
                $updateAttributes,
            );

            if ($targetId !== null) {
                $this->userIds[$sourceId] = $targetId;
            }
        }

        $this->count('users', count($this->userIds));
    }

    private function updateUserHierarchy(): void
    {
        foreach ($this->sourceUsers as $user) {
            $targetId = $this->userIds[(int) $user->id] ?? null;
            $reportsToId = $user->supervisor_user_id !== null
                ? ($this->userIds[(int) $user->supervisor_user_id] ?? null)
                : null;

            if ($targetId === null) {
                continue;
            }

            if ($reportsToId === null && $user->supervisor_user_id !== null) {
                $this->ambiguous('user', (int) $user->id, 'supervisor user is not mapped');
            }

            if ($this->apply) {
                $this->target->table('users')->where('id', $targetId)->update(['reports_to_id' => $reportsToId]);
            }
        }

        foreach ($this->teamIds as $sourceBranchId => $teamId) {
            $supervisorId = null;
            $coordinatorId = null;

            foreach ($this->sourceUsers as $user) {
                if ((int) $user->branch_id !== $sourceBranchId) {
                    continue;
                }

                $roleName = $this->roleNames[(int) $user->role_id] ?? '';
                if ($supervisorId === null && $roleName === 'spv') {
                    $supervisorId = $this->userIds[(int) $user->id] ?? null;
                }
                if ($coordinatorId === null && $roleName === 'koordinator_sales') {
                    $coordinatorId = $this->userIds[(int) $user->id] ?? null;
                }
            }

            if ($this->apply) {
                $this->target->table('teams')->where('id', $teamId)->update([
                    'supervisor_id' => $supervisorId,
                    'coordinator_id' => $coordinatorId,
                ]);
            }
        }
    }

    private function assignUsersToTeamsAndRoles(): void
    {
        foreach ($this->sourceUsers as $user) {
            $targetUserId = $this->userIds[(int) $user->id] ?? null;
            $roleName = $this->roleNames[(int) $user->role_id] ?? '';
            $teamId = $user->branch_id !== null ? ($this->teamIds[(int) $user->branch_id] ?? null) : null;

            if ($targetUserId === null || $roleName === '') {
                continue;
            }

            if (in_array($roleName, ['sales', 'koordinator_sales'], true)) {
                if ($teamId === null) {
                    $this->ambiguous('user_role', (int) $user->id, 'team role has no branch team');

                    continue;
                }

                $this->count('team_role_assignments');
                if (! $this->apply) {
                    continue;
                }

                $this->target->table('team_user')->updateOrInsert(
                    ['team_id' => $teamId, 'user_id' => $targetUserId],
                    ['is_default' => true, 'created_at' => $user->created_at, 'updated_at' => $user->updated_at],
                );

                $roleId = Role::query()->where('team_id', $teamId)->where('name', $roleName)->value('id');
                if ($roleId !== null) {
                    $this->target->table('model_has_roles')->insertOrIgnore([
                        'role_id' => $roleId,
                        'model_id' => $targetUserId,
                        'model_type' => 'App\\Models\\User',
                        'team_id' => $teamId,
                    ]);
                }

                continue;
            }

            $this->count('global_role_assignments');
            if ($this->apply) {
                $roleId = Role::query()->whereNull('team_id')->where('name', $roleName)->value('id');
                if ($roleId !== null) {
                    $this->target->table('global_role_user')->updateOrInsert(
                        ['role_id' => $roleId, 'user_id' => $targetUserId],
                        ['created_at' => $user->created_at, 'updated_at' => $user->updated_at],
                    );
                }
            }
        }
    }

    private function prepareLeadHistories(): void
    {
        foreach ($this->source->table('sales_lead_status_histories')->orderBy('sales_lead_id')->orderBy('changed_at')->orderBy('id')->get() as $history) {
            $this->historiesByLead[(int) $history->sales_lead_id][] = $history;
        }
    }

    private function migrateLeads(): void
    {
        foreach ($this->source->table('sales_leads')->whereNull('deleted_at')->orderBy('id')->get() as $lead) {
            $sourceId = (int) $lead->id;
            if (! $this->shouldProcessSourceRow('lead', $sourceId, $lead)) {
                continue;
            }

            $branchId = $this->branchIds[(int) $lead->branch_id] ?? null;
            $projectId = $this->projectIds[(int) $lead->project_id] ?? null;
            $salesPicId = $this->userIds[(int) $lead->sales_user_id] ?? null;
            $createdBy = $this->userIds[(int) $lead->created_by] ?? $salesPicId;

            if (! in_array((string) $lead->source, ['Online', 'Offline', 'Referral', 'Freelance', 'Lead Cabang', 'Lead Pusat'], true)) {
                $this->ambiguous('lead', (int) $lead->id, 'unknown lead source', ['source' => $lead->source]);

                continue;
            }

            if ($branchId === null || $projectId === null || $salesPicId === null || $createdBy === null) {
                $this->skip('lead', $sourceId, 'branch, project, sales user, or creator is not mapped');

                continue;
            }

            [$status, $originalStatus] = $this->safeLeadStatus($lead);
            if ($originalStatus !== $status) {
                $this->count('lead_process_status_sanitized');
            }

            $wasMapped = isset($this->targetIds['lead'][$sourceId]);
            $resolvedTeamId = $this->resolveFinalTeamId($salesPicId, $branchId, 'lead', $sourceId);
            $legacyTeamId = $this->teamIds[(int) $lead->branch_id] ?? null;
            $teamId = $resolvedTeamId ?? ($wasMapped ? null : $legacyTeamId);
            if ($resolvedTeamId === null && $wasMapped) {
                $this->count('lead_team_preserved_without_new_final_team');
                if ($this->delta) {
                    $this->ambiguous('lead', $sourceId, 'updated lead Sales PIC has no deterministic final Team', [
                        'sales_pic_id' => $salesPicId,
                        'branch_id' => $branchId,
                    ]);
                }
            }
            if ($resolvedTeamId === null && ! $wasMapped && $this->delta) {
                $this->ambiguous('lead', $sourceId, 'new lead has no deterministic final Team', [
                    'sales_pic_id' => $salesPicId,
                    'branch_id' => $branchId,
                ]);
                $teamId = null;
            }

            $attributes = [
                'id_lead' => 'CRM-'.$sourceId,
                'id_promo' => $lead->id_promo,
                'nama_promo' => $lead->campaign_name,
                'tanggal_lead' => $lead->lead_date,
                'sumber_lead' => $this->sourceName((string) $lead->source),
                'kanal_masuk' => $this->channelName((string) $lead->platform),
                'aktivitas_lead' => $this->activityName((string) $lead->platform),
                'nama_konsumen' => (string) $lead->customer_name,
                'no_hp' => $lead->phone,
                'project_id' => $projectId,
                'branch_id' => $branchId,
                'team_id' => $teamId,
                'sales_pic_id' => $salesPicId,
                'created_by' => $createdBy,
                'status_lead' => $status,
                'keterangan' => $lead->notes,
                'next_follow_up_at' => null,
                'next_follow_up_note' => null,
                'qualification_status' => 'not_qualified',
                'qualification_note' => null,
                'qualified_at' => null,
                'qualified_by' => null,
                'created_at' => $lead->created_at,
                'updated_at' => $lead->updated_at,
            ];
            $updateAttributes = $attributes;
            unset(
                $updateAttributes['id_lead'],
                $updateAttributes['created_by'],
                $updateAttributes['created_at'],
                $updateAttributes['updated_at'],
                $updateAttributes['next_follow_up_at'],
                $updateAttributes['next_follow_up_note'],
                $updateAttributes['qualification_status'],
                $updateAttributes['qualification_note'],
                $updateAttributes['qualified_at'],
                $updateAttributes['qualified_by'],
            );
            if ($wasMapped && $resolvedTeamId === null) {
                unset($updateAttributes['team_id']);
            }

            $targetId = $this->upsertMapped(
                'lead',
                $sourceId,
                fn (): ?int => $this->target->table('leads')->where('id_lead', 'CRM-'.$sourceId)->value('id'),
                $attributes,
                ['original_status' => $originalStatus, 'source_updated_at' => $lead->updated_at, 'process_fields_excluded' => true],
                $updateAttributes,
            );

            if ($targetId !== null) {
                $this->count('leads');
                $this->count($wasMapped ? 'leads_updated' : 'leads_new');
            }
        }
    }

    private function migrateLeadActivities(): void
    {
        foreach ($this->historiesByLead as $sourceLeadId => $histories) {
            $targetLeadId = $this->targetIds['lead'][$sourceLeadId] ?? null;
            if ($targetLeadId === null) {
                continue;
            }

            $previousStatus = null;
            foreach ($histories as $history) {
                $historyId = (int) $history->id;
                $shouldProcess = $this->shouldProcessSourceRow('lead_activity', $historyId, $history, 'changed_at');
                $sourceStatus = strtolower((string) $history->status);
                if (! in_array($sourceStatus, self::SAFE_STATUSES, true)) {
                    if (in_array($sourceStatus, self::PROCESS_STATUSES, true)) {
                        $this->count('lead_process_activities_excluded');
                    } else {
                        $this->ambiguous('lead_activity', (int) $history->id, 'unknown source status', ['status' => $history->status]);
                    }

                    continue;
                }

                if (! $shouldProcess) {
                    $previousStatus = $this->statusName($sourceStatus);

                    continue;
                }

                $activityId = $this->upsertMapped(
                    'lead_activity',
                    $historyId,
                    fn (): ?int => null,
                    [
                        'lead_id' => $targetLeadId,
                        'user_id' => $history->actor_id !== null ? ($this->userIds[(int) $history->actor_id] ?? null) : null,
                        'activity_type' => 'Status Update',
                        'description' => 'Migrasi status Buku Saku: '.$this->statusName($sourceStatus),
                        'status_from' => $previousStatus,
                        'status_to' => $this->statusName($sourceStatus),
                        'happened_at' => $history->changed_at ?? $history->created_at,
                        'happened_at_source' => $history->changed_at !== null ? 'legacy_changed_at' : 'legacy_created_at',
                        'created_at' => $history->created_at,
                        'updated_at' => $history->updated_at,
                    ],
                    [],
                );

                if ($activityId !== null) {
                    $this->count('lead_activities');
                }

                $previousStatus = $this->statusName($sourceStatus);
            }
        }
    }

    private function migrateAgendas(): void
    {
        foreach ($this->source->table('content_items')
            ->where('item_type', 'agenda')
            ->where('agenda_type', 'buku_saku_sales')
            ->orderBy('id')->get() as $agenda) {
            $sourceId = (int) $agenda->id;
            if (! $this->shouldProcessSourceRow('agenda', $sourceId, $agenda)) {
                continue;
            }

            $branchId = $this->branchIds[(int) $agenda->branch_id] ?? null;
            $ownerId = $this->userIds[(int) $agenda->owner_user_id] ?? null;
            $creatorId = $this->userIds[(int) $agenda->created_by] ?? $ownerId;

            if ($branchId === null || $ownerId === null || $creatorId === null) {
                $this->skip('agenda', (int) $agenda->id, 'branch, owner, or creator is not mapped');

                continue;
            }

            $agendaDate = $agenda->scheduled_date
                ?? $agenda->start_date
                ?? $agenda->deadline_date
                ?? CarbonImmutable::parse($agenda->created_at)->toDateString();
            $wasMapped = isset($this->targetIds['agenda'][$sourceId]);
            $resolvedTeamId = $this->resolveFinalTeamId($ownerId, $branchId, 'agenda', $sourceId);
            $agendaTeamId = $resolvedTeamId ?? ($wasMapped ? null : ($this->teamIds[(int) $agenda->branch_id] ?? null));
            if ($resolvedTeamId === null && $wasMapped) {
                $this->count('agenda_team_preserved_without_new_final_team');
            }
            if ($resolvedTeamId === null && ! $wasMapped && $this->delta) {
                $this->ambiguous('agenda', $sourceId, 'new agenda has no deterministic final Team', [
                    'owner_id' => $ownerId,
                    'branch_id' => $branchId,
                ]);
                $agendaTeamId = null;
            }
            $attributes = [
                'branch_id' => $branchId,
                'team_id' => $agendaTeamId,
                'created_by' => $creatorId,
                'owner_id' => $ownerId,
                'sales_case_id' => null,
                'lead_id' => null,
                'agenda_date' => $agendaDate,
                'activity_category' => $this->agendaCategory((string) $agenda->sales_activity_category),
                'title' => $agenda->title ?: 'Buku Saku #'.$sourceId,
                'location' => $agenda->location,
                'activity_result' => $agenda->activity_result ?: ($agenda->notes ?: $agenda->task_detail),
                'status' => (string) $agenda->status === 'done' ? 'done' : 'planned',
                'completed_at' => $agenda->completed_at,
                'created_at' => $agenda->created_at,
                'updated_at' => $agenda->updated_at,
            ];
            $updateAttributes = $attributes;
            unset($updateAttributes['team_id'], $updateAttributes['created_at'], $updateAttributes['updated_at']);
            $targetId = $this->upsertMapped(
                'agenda',
                $sourceId,
                fn (): ?int => null,
                $attributes,
                ['source_updated_at' => $agenda->updated_at],
                $updateAttributes,
            );

            if ($targetId !== null) {
                $this->count('agendas');
                $this->count('agendas_without_lead');

                $assignmentId = $this->upsertMapped(
                    'agenda_assignment',
                    $sourceId,
                    fn (): ?int => null,
                    [
                        'sales_agenda_id' => $targetId,
                        'action' => 'created_for',
                        'from_user_id' => null,
                        'to_user_id' => $ownerId,
                        'by_user_id' => $creatorId,
                        'reason' => 'Migrated from legacy Oasis Buku Saku assignment.',
                        'created_at' => $agenda->created_at,
                        'updated_at' => $agenda->updated_at,
                    ],
                    [],
                );

                if ($assignmentId !== null) {
                    $this->count('agenda_assignments');
                }
            }
        }
    }

    private function migrateAgendaEvidence(): void
    {
        foreach ($this->source->table('sales_agenda_evidences')->orderBy('id')->get() as $evidence) {
            $sourceId = (int) $evidence->id;
            if (! $this->shouldProcessSourceRow('agenda_evidence', $sourceId, $evidence)) {
                continue;
            }

            $agendaId = $this->targetIds['agenda'][(int) $evidence->content_item_id] ?? null;
            if ($agendaId === null) {
                $this->skip('agenda_evidence', (int) $evidence->id, 'agenda is not mapped');

                continue;
            }

            $sourcePath = $this->sourceEvidencePath($evidence);
            $extension = pathinfo((string) ($evidence->original_name ?: 'evidence.bin'), PATHINFO_EXTENSION);
            $targetPath = 'migration/evidence/'.((int) $evidence->id).'-'.Str::slug(pathinfo((string) ($evidence->original_name ?: 'evidence'), PATHINFO_FILENAME)).($extension !== '' ? '.'.$extension : '');

            if ($this->apply && $this->copyEvidence) {
                if ($sourcePath === null || ! is_file($sourcePath)) {
                    $this->skip('agenda_evidence', (int) $evidence->id, 'source evidence file is missing');

                    continue;
                }

                Storage::disk('public')->put($targetPath, File::get($sourcePath));
            } elseif ($this->apply) {
                $this->skip('agenda_evidence', (int) $evidence->id, 'apply requires --copy-evidence to create a valid target file');

                continue;
            }

            $targetId = $this->upsertMapped(
                'agenda_evidence',
                $sourceId,
                fn (): ?int => null,
                [
                    'sales_agenda_id' => $agendaId,
                    'file_path' => $targetPath,
                    'original_name' => (string) ($evidence->original_name ?: 'evidence'),
                    'mime_type' => (string) ($evidence->mime_type ?: 'application/octet-stream'),
                    'file_size' => (int) ($evidence->size_bytes ?: 0),
                    'created_at' => $evidence->created_at,
                    'updated_at' => $evidence->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->count('agenda_evidence');
            }
        }
    }

    /** @param Closure(): ?int $existingId */
    private function upsertMapped(string $entity, int $sourceId, Closure $existingId, array $attributes, array $metadata = [], ?array $updateAttributes = null): ?int
    {
        if (isset($this->targetIds[$entity][$sourceId])) {
            $targetId = $this->targetIds[$entity][$sourceId];
            if ($this->apply && $targetId > 0) {
                $this->target->table($this->targetTable($entity))->where('id', $targetId)->update($updateAttributes ?? $attributes);
                $this->storeMap($entity, $sourceId, $targetId, $metadata);
            }

            return $targetId;
        }

        $targetId = $existingId();
        if ($targetId === null && ! $this->apply) {
            $targetId = -$sourceId;
        }

        if ($this->apply) {
            $table = $this->targetTable($entity);
            if ($targetId === null) {
                $targetId = (int) $this->target->table($table)->insertGetId($attributes);
            } else {
                $this->target->table($table)->where('id', $targetId)->update($updateAttributes ?? $attributes);
            }

            $this->storeMap($entity, $sourceId, $targetId, $metadata);
        }

        if ($targetId !== null) {
            $this->targetIds[$entity][$sourceId] = $targetId;
        }

        return $targetId;
    }

    private function storeMap(string $entity, int $sourceId, int $targetId, array $metadata = []): void
    {
        $now = now();
        $pendingTimestamp = $this->pendingSourceTimestamps[$entity][$sourceId] ?? null;
        if (is_string($pendingTimestamp) && $pendingTimestamp !== '') {
            $metadata['source_updated_at'] = $pendingTimestamp;
        }
        $key = ['source_connection' => $this->sourceConnectionName, 'entity' => $entity, 'source_id' => $sourceId];
        $values = [
            'target_id' => $targetId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];
        $mapQuery = $this->target->table('legacy_migration_maps')->where($key);
        if ($mapQuery->exists()) {
            $mapQuery->update($values);

            $this->mapMetadata[$entity][$sourceId] = $metadata;

            return;
        }

        $this->target->table('legacy_migration_maps')->insert($key + $values + ['created_at' => $now]);
        $this->mapMetadata[$entity][$sourceId] = $metadata;
    }

    private function targetTable(string $entity): string
    {
        return match ($entity) {
            'branch' => 'branches',
            'project' => 'projects',
            'team' => 'teams',
            'user' => 'users',
            'lead' => 'leads',
            'lead_activity' => 'lead_activities',
            'agenda' => 'sales_agendas',
            'agenda_assignment' => 'sales_agenda_assignment_histories',
            'agenda_evidence' => 'sales_agenda_evidence',
            default => throw new RuntimeException('Unknown migration entity: '.$entity),
        };
    }

    private function shouldProcessSourceRow(string $entity, int $sourceId, object $row, string $preferredTimestamp = 'updated_at'): bool
    {
        $latest = $this->latestSourceTimestamp($row, $preferredTimestamp);
        if ($this->freezeAt !== null && $latest !== null && $latest->greaterThan($this->freezeAt)) {
            $this->count('source_rows_after_freeze');

            return false;
        }

        if (! $this->delta) {
            return true;
        }

        if (! isset($this->targetIds[$entity][$sourceId])) {
            $this->count('unmapped_recovery_records');

            return true;
        }

        if ($latest === null) {
            $this->count('source_rows_without_timestamps');

            return true;
        }

        $lastSynced = $this->mapMetadata[$entity][$sourceId]['source_updated_at'] ?? null;
        if (is_string($lastSynced) && $lastSynced !== '' && $latest->lessThanOrEqualTo(CarbonImmutable::parse($lastSynced, $this->sourceTimezone))) {
            $this->count('unchanged_source_records');

            return false;
        }

        if ($latest->lessThanOrEqualTo($this->since)) {
            $this->count('unchanged_source_records');

            return false;
        }

        $this->count('delta_candidates');
        $this->pendingSourceTimestamps[$entity][$sourceId] = $latest->toIso8601String();

        return true;
    }

    private function latestSourceTimestamp(object $row, string $preferredTimestamp): ?CarbonImmutable
    {
        $timestamps = [];
        foreach ([$preferredTimestamp, 'updated_at', 'created_at', 'changed_at'] as $column) {
            if (! property_exists($row, $column) || $row->{$column} === null || $row->{$column} === '') {
                continue;
            }

            $timestamps[] = CarbonImmutable::parse((string) $row->{$column}, $this->sourceTimezone);
        }

        if ($timestamps === []) {
            return null;
        }

        return collect($timestamps)->sortBy(fn (CarbonImmutable $timestamp): int => $timestamp->getTimestamp())->last();
    }

    private function resolveFinalTeamId(?int $userId, ?int $branchId, string $entity, int $sourceId): ?int
    {
        if ($userId === null || $branchId === null) {
            return null;
        }

        $modelType = (new User)->getMorphClass();
        $teamIds = $this->target->table('team_user as memberships')
            ->join('teams', 'teams.id', '=', 'memberships.team_id')
            ->join('model_has_roles as model_roles', function (JoinClause $join) use ($modelType): void {
                $join->on('model_roles.model_id', '=', 'memberships.user_id')
                    ->where('model_roles.model_type', '=', $modelType)
                    ->whereColumn('model_roles.team_id', 'memberships.team_id');
            })
            ->join('roles', function (JoinClause $join): void {
                $join->on('roles.id', '=', 'model_roles.role_id')
                    ->where('roles.name', '=', 'sales')
                    ->whereColumn('roles.team_id', 'teams.id');
            })
            ->where('memberships.user_id', $userId)
            ->where('teams.is_active', true)
            ->where('teams.branch_id', $branchId)
            ->where('teams.code', 'like', 'COORD-B%')
            ->distinct()
            ->pluck('teams.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if (count($teamIds) > 1) {
            $this->ambiguous($entity, $sourceId, 'user resolves to multiple active final Teams', [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'team_ids' => $teamIds,
            ]);

            return null;
        }

        return $teamIds[0] ?? null;
    }

    private function recordReconciliation(): void
    {
        $sourceLeads = $this->source->table('sales_leads')->whereNull('deleted_at');
        if ($this->freezeAt !== null) {
            $sourceLeads->where('created_at', '<=', $this->sourceCutoffValue())->where('updated_at', '<=', $this->sourceCutoffValue());
        }

        $sourceReconciliation = [
            'lead_total_at_freeze' => (int) $sourceLeads->count(),
            'lead_created_since_previous_sync' => null,
            'lead_updated_since_previous_sync' => null,
            'lead_activity_total_at_freeze' => null,
            'agenda_total_at_freeze' => null,
        ];
        if ($this->since !== null) {
            $sourceReconciliation['lead_created_since_previous_sync'] = (int) $this->source->table('sales_leads')
                ->whereNull('deleted_at')
                ->where('created_at', '>', $this->sourceSinceValue())
                ->where('created_at', '<=', $this->sourceCutoffValue())
                ->count();
            $sourceReconciliation['lead_updated_since_previous_sync'] = (int) $this->source->table('sales_leads')
                ->whereNull('deleted_at')
                ->where('updated_at', '>', $this->sourceSinceValue())
                ->where('updated_at', '<=', $this->sourceCutoffValue())
                ->count();
        }
        if ($this->source->getSchemaBuilder()->hasTable('sales_lead_status_histories')) {
            $histories = $this->source->table('sales_lead_status_histories');
            if ($this->freezeAt !== null) {
                $histories->where('changed_at', '<=', $this->sourceCutoffValue());
            }
            $sourceReconciliation['lead_activity_total_at_freeze'] = (int) $histories->count();
        }
        if ($this->source->getSchemaBuilder()->hasTable('content_items')) {
            $agendas = $this->source->table('content_items')->where('item_type', 'agenda')->where('agenda_type', 'buku_saku_sales');
            if ($this->freezeAt !== null) {
                $agendas->where('created_at', '<=', $this->sourceCutoffValue())->where('updated_at', '<=', $this->sourceCutoffValue());
            }
            $sourceReconciliation['agenda_total_at_freeze'] = (int) $agendas->count();
        }

        $this->report['reconciliation'] = [
            'source' => $sourceReconciliation,
            'target' => [
                'lead_total' => (int) $this->target->table('leads')->count(),
                'mapped_lead_total' => (int) $this->target->table('legacy_migration_maps')->where('source_connection', $this->sourceConnectionName)->where('entity', 'lead')->count(),
                'newly_imported' => (int) ($this->report['counts']['leads_new'] ?? 0),
                'updated' => (int) ($this->report['counts']['leads_updated'] ?? 0),
                'unchanged' => (int) ($this->report['counts']['unchanged_source_records'] ?? 0),
                'exceptions' => count($this->report['issues']['ambiguous'] ?? []),
            ],
        ];
    }

    private function sourceCutoffValue(): string
    {
        return $this->freezeAt?->format('Y-m-d H:i:s') ?? CarbonImmutable::now($this->sourceTimezone)->format('Y-m-d H:i:s');
    }

    private function sourceSinceValue(): string
    {
        return $this->since?->format('Y-m-d H:i:s') ?? $this->sourceCutoffValue();
    }

    private function validateEvidenceOptions(): void
    {
        $evidenceCount = (int) $this->source->table('sales_agenda_evidences')->count();
        if ($evidenceCount === 0 || ! $this->apply) {
            return;
        }

        if (! $this->copyEvidence) {
            throw new RuntimeException('Source contains agenda evidence. Re-run apply with --copy-evidence.');
        }

        foreach ($this->source->table('sales_agenda_evidences')->orderBy('id')->get() as $evidence) {
            if ($this->sourceEvidencePath($evidence) === null || ! is_file($this->sourceEvidencePath($evidence))) {
                throw new RuntimeException('Evidence file is missing for source evidence #'.((int) $evidence->id).'. Verify LEGACY_OASIS_STORAGE_PATH before applying.');
            }
        }
    }

    /** @return array{0: string, 1: string} */
    private function safeLeadStatus(object $lead): array
    {
        $original = strtolower((string) $lead->current_status);
        if (in_array($original, self::SAFE_STATUSES, true)) {
            return [$this->statusName($original), $this->statusName($original)];
        }

        foreach (array_reverse($this->historiesByLead[(int) $lead->id] ?? []) as $history) {
            $historyStatus = strtolower((string) $history->status);
            if (in_array($historyStatus, self::SAFE_STATUSES, true)) {
                return [$this->statusName($historyStatus), $this->statusName($original)];
            }
        }

        return ['No Respon', self::STATUS_MAP[$original] ?? 'Tidak Diketahui'];
    }

    private function sourceName(string $source): string
    {
        return in_array($source, ['Online', 'Offline', 'Referral', 'Freelance', 'Lead Cabang', 'Lead Pusat'], true)
            ? $source
            : 'Lead Pusat';
    }

    private function channelName(string $channel): string
    {
        return self::CHANNEL_MAP[$channel] ?? 'Tidak Diketahui';
    }

    private function activityName(string $platform): string
    {
        return match ($platform) {
            'TikTok' => 'Live',
            'Facebook', 'Instagram', 'Website' => 'Konten Organik',
            'Marketplace' => 'Marketplace',
            'WhatsApp', 'Telepon' => 'Follow Up Data Lama',
            'Canvassing' => 'Sebar Brosur',
            'Pameran', 'Event' => 'Event',
            'Datang Langsung' => 'Datang Langsung',
            default => 'Tidak Diketahui',
        };
    }

    private function statusName(string $status): string
    {
        return self::STATUS_MAP[$status] ?? 'Tidak Diketahui';
    }

    private function agendaCategory(string $category): string
    {
        $allowed = ['Canvassing', 'Follow-up', 'Telepon/WhatsApp', 'Tatap Muka Konsumen', 'Cek Lokasi', 'TikTok Live', 'Pembuatan Konten', 'Event/Pameran', 'Administrasi', 'Rapat', 'Lainnya'];
        if (in_array($category, $allowed, true)) {
            return $category;
        }

        $this->ambiguous('agenda', 0, 'unknown agenda category', ['category' => $category]);

        return 'Lainnya';
    }

    private function sourceEvidencePath(object $evidence): ?string
    {
        $root = config('legacy-migration.source_storage_path');
        if (! is_string($root) || $root === '') {
            return null;
        }

        $root = realpath($root);
        $relativePath = ltrim((string) ($evidence->file_path ?: $evidence->storage_path), '/\\');
        if ($root === false || $relativePath === '') {
            return null;
        }

        $path = realpath($root.DIRECTORY_SEPARATOR.$relativePath);
        if ($path === false || ! Str::startsWith($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    private function count(string $key, int $amount = 1): void
    {
        $this->report['counts'][$key] = ($this->report['counts'][$key] ?? 0) + $amount;
    }

    /** @param array<string, mixed> $details */
    private function ambiguous(string $entity, int $sourceId, string $reason, array $details = []): void
    {
        $this->report['has_blocking_issues'] = true;
        $this->addIssue('ambiguous', $entity, $sourceId, $reason, $details);
    }

    /** @param array<string, mixed> $details */
    private function skip(string $entity, int $sourceId, string $reason, array $details = []): void
    {
        $this->count('skipped_records');
        $this->addIssue('skipped', $entity, $sourceId, $reason, $details);
    }

    /** @param array<string, mixed> $details */
    private function addIssue(string $type, string $entity, int $sourceId, string $reason, array $details = []): void
    {
        if (count($this->report['issues'][$type]) >= 500) {
            return;
        }

        $this->report['issues'][$type][] = array_merge([
            'entity' => $entity,
            'source_id' => $sourceId,
            'reason' => $reason,
        ], $details);
    }

    private function writeReport(?string $reportPath): void
    {
        $path = $reportPath !== null
            ? (str_starts_with($reportPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $reportPath) === 1
                ? $reportPath
                : (str_starts_with($reportPath, 'storage'.DIRECTORY_SEPARATOR) || str_starts_with($reportPath, 'storage/')
                    ? base_path($reportPath)
                    : storage_path($reportPath)))
            : storage_path('app/private/oasis-migration-'.now()->format('Ymd-His').'.json');

        $this->report['report_path'] = $path;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
