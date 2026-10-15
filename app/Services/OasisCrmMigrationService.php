<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Team;
use App\Support\Rbac;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OasisCrmMigrationService
{
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

    private OutputStyle $output;

    /** @var array<string, array<int, int>> */
    private array $targetIds = [];

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
    ): array {
        $this->sourceConnectionName = $sourceConnection;
        $this->source = DB::connection($sourceConnection);
        $this->target = DB::connection((string) config('database.default'));
        $this->apply = $apply;
        $this->copyEvidence = $copyEvidence;
        $this->output = $output;
        $this->report = [
            'started_at' => CarbonImmutable::now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'source_connection' => $sourceConnection,
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

        $this->loadExistingMaps();
        $this->validateEvidenceOptions();
        $this->output->writeln('<comment>Reading legacy Oasis data in '.($apply ? 'apply' : 'dry-run').' mode...</comment>');

        $work = function (): void {
            $this->migrateBranches();
            $this->migrateProjects();
            $this->migrateTeams();
            $this->migrateRoles();
            $this->migrateUsers();
            $this->updateUserHierarchy();
            $this->assignUsersToTeamsAndRoles();
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
            ->get(['entity', 'source_id', 'target_id']) as $map) {
            $this->targetIds[(string) $map->entity][(int) $map->source_id] = (int) $map->target_id;
        }
    }

    private function migrateBranches(): void
    {
        foreach ($this->source->table('branches')->orderBy('id')->get() as $branch) {
            $targetId = $this->upsertMapped(
                'branch',
                (int) $branch->id,
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
                $this->branchIds[(int) $branch->id] = $targetId;
            }
        }

        $this->count('branches', count($this->branchIds));
    }

    private function migrateProjects(): void
    {
        foreach ($this->source->table('lead_master')->orderBy('id')->get() as $project) {
            $branchId = $this->branchIds[(int) $project->branch_id] ?? null;
            if ($branchId === null) {
                $this->skip('project', (int) $project->id, 'source branch is not mapped');

                continue;
            }

            $code = 'LEGACY-LM-'.((int) $project->id);
            $targetId = $this->upsertMapped(
                'project',
                (int) $project->id,
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
                $this->projectIds[(int) $project->id] = $targetId;
            }
        }

        $this->count('projects', count($this->projectIds));
    }

    private function migrateTeams(): void
    {
        foreach ($this->source->table('branches')->orderBy('id')->get() as $branch) {
            $branchId = $this->branchIds[(int) $branch->id] ?? null;
            if ($branchId === null) {
                continue;
            }

            $code = 'LEGACY-'.Str::upper((string) $branch->code);
            $targetId = $this->upsertMapped(
                'team',
                (int) $branch->id,
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
                $this->teamIds[(int) $branch->id] = $targetId;
            }
        }

        $this->count('teams', count($this->teamIds));
    }

    private function migrateRoles(): void
    {
        if ($this->apply) {
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
            $branchId = $user->branch_id !== null ? ($this->branchIds[(int) $user->branch_id] ?? null) : null;
            $targetId = $this->upsertMapped(
                'user',
                (int) $user->id,
                fn (): ?int => $this->target->table('users')->where('email', $user->email)->value('id'),
                [
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'password' => (string) $user->password,
                    'remember_token' => $user->remember_token,
                    'branch_id' => $branchId,
                    'reports_to_id' => null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->userIds[(int) $user->id] = $targetId;
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
            $branchId = $this->branchIds[(int) $lead->branch_id] ?? null;
            $projectId = $this->projectIds[(int) $lead->project_id] ?? null;
            $salesPicId = $this->userIds[(int) $lead->sales_user_id] ?? null;
            $createdBy = $this->userIds[(int) $lead->created_by] ?? $salesPicId;

            if (! in_array((string) $lead->source, ['Online', 'Offline', 'Referral', 'Freelance', 'Lead Cabang', 'Lead Pusat'], true)) {
                $this->ambiguous('lead', (int) $lead->id, 'unknown lead source', ['source' => $lead->source]);

                continue;
            }

            if ($branchId === null || $projectId === null || $salesPicId === null || $createdBy === null) {
                $this->skip('lead', (int) $lead->id, 'branch, project, sales user, or creator is not mapped');

                continue;
            }

            [$status, $originalStatus] = $this->safeLeadStatus($lead);
            if ($originalStatus !== $status) {
                $this->count('lead_process_status_sanitized');
            }

            $targetId = $this->upsertMapped(
                'lead',
                (int) $lead->id,
                fn (): ?int => $this->target->table('leads')->where('id_lead', 'CRM-'.((int) $lead->id))->value('id'),
                [
                    'id_lead' => 'CRM-'.((int) $lead->id),
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
                    'team_id' => $this->teamIds[(int) $lead->branch_id] ?? null,
                    'sales_pic_id' => $salesPicId,
                    'created_by' => $createdBy,
                    'status_lead' => $status,
                    'keterangan' => $lead->notes,
                    'origin_agenda_id' => null,
                    'next_follow_up_at' => null,
                    'next_follow_up_note' => null,
                    'qualification_status' => 'not_qualified',
                    'qualification_note' => null,
                    'qualified_at' => null,
                    'qualified_by' => null,
                    'created_at' => $lead->created_at,
                    'updated_at' => $lead->updated_at,
                ],
                ['original_status' => $originalStatus, 'process_fields_excluded' => true],
            );

            if ($targetId !== null) {
                $this->count('leads');
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
                $sourceStatus = strtolower((string) $history->status);
                if (! in_array($sourceStatus, self::SAFE_STATUSES, true)) {
                    if (in_array($sourceStatus, self::PROCESS_STATUSES, true)) {
                        $this->count('lead_process_activities_excluded');
                    } else {
                        $this->ambiguous('lead_activity', (int) $history->id, 'unknown source status', ['status' => $history->status]);
                    }

                    continue;
                }

                $activityId = $this->upsertMapped(
                    'lead_activity',
                    (int) $history->id,
                    fn (): ?int => null,
                    [
                        'lead_id' => $targetLeadId,
                        'user_id' => $history->actor_id !== null ? ($this->userIds[(int) $history->actor_id] ?? null) : null,
                        'activity_type' => 'Status Update',
                        'description' => 'Migrasi status Buku Saku: '.$this->statusName($sourceStatus),
                        'status_from' => $previousStatus,
                        'status_to' => $this->statusName($sourceStatus),
                        'happened_at' => $history->changed_at ?? $history->created_at,
                        'created_at' => $history->created_at,
                        'updated_at' => $history->updated_at,
                    ],
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
            $targetId = $this->upsertMapped(
                'agenda',
                (int) $agenda->id,
                fn (): ?int => null,
                [
                    'branch_id' => $branchId,
                    'team_id' => $this->teamIds[(int) $agenda->branch_id] ?? null,
                    'created_by' => $creatorId,
                    'owner_id' => $ownerId,
                    'sales_case_id' => null,
                    'lead_id' => null,
                    'agenda_date' => $agendaDate,
                    'activity_category' => $this->agendaCategory((string) $agenda->sales_activity_category),
                    'title' => $agenda->title ?: 'Buku Saku #'.((int) $agenda->id),
                    'location' => $agenda->location,
                    'activity_result' => $agenda->activity_result ?: ($agenda->notes ?: $agenda->task_detail),
                    'status' => (string) $agenda->status === 'done' ? 'done' : 'planned',
                    'completed_at' => $agenda->completed_at,
                    'created_at' => $agenda->created_at,
                    'updated_at' => $agenda->updated_at,
                ],
            );

            if ($targetId !== null) {
                $this->count('agendas');
                $this->count('agendas_without_lead');

                $assignmentId = $this->upsertMapped(
                    'agenda_assignment',
                    (int) $agenda->id,
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
                (int) $evidence->id,
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
    private function upsertMapped(string $entity, int $sourceId, Closure $existingId, array $attributes, array $metadata = []): ?int
    {
        if (isset($this->targetIds[$entity][$sourceId])) {
            $targetId = $this->targetIds[$entity][$sourceId];
            if ($this->apply && $targetId > 0) {
                $this->target->table($this->targetTable($entity))->where('id', $targetId)->update($attributes);
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
                $this->target->table($table)->where('id', $targetId)->update($attributes);
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
        $this->target->table('legacy_migration_maps')->updateOrInsert(
            ['source_connection' => $this->sourceConnectionName, 'entity' => $entity, 'source_id' => $sourceId],
            [
                'target_id' => $targetId,
                'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
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
