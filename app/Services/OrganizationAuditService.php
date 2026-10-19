<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationAuditService
{
    /**
     * Run a read-only audit of the organization and operational ownership graph.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $branches = DB::table('branches')
            ->select(['id', 'code', 'name', 'is_active'])
            ->orderBy('name')
            ->get();
        $teams = $this->teamRows();
        $teamRoles = $this->teamRoleRows();
        $globalRoles = $this->globalRoleRows();
        $users = $this->userRows($teamRoles, $globalRoles);
        $leads = $this->leadMismatches();
        $agendas = $this->agendaMismatches();
        $salesCases = $this->salesCaseSummary();

        $teamSummaries = $this->teamSummaries($teams, $teamRoles);
        $coordinators = $this->coordinatorSummaries($users, $teams, $teamRoles);
        $sales = $this->salesSummaries($users, $teams, $teamRoles);
        $issues = $this->issues($teamSummaries, $users, $coordinators, $sales, $teamRoles, $leads, $agendas);

        return [
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'counts' => [
                'branches' => $branches->count(),
                'active_branches' => $branches->where('is_active', true)->count(),
                'teams' => $teams->count(),
                'active_teams' => $teams->where('is_active', true)->count(),
                'legacy_migration_teams' => $teams->filter(fn (array $team): bool => $this->isLegacyTeam($team))->count(),
                'users' => $users->count(),
                'coordinators' => $coordinators->count(),
                'sales' => $sales->count(),
                'leads' => (int) DB::table('leads')->count(),
                'lead_mismatches' => count($leads),
                'agendas' => (int) DB::table('sales_agendas')->count(),
                'agenda_mismatches' => count($agendas),
                'active_sales_cases' => $salesCases['active'],
            ],
            'branches' => $this->branchSummaries($branches, $teamSummaries),
            'teams' => $teamSummaries->values()->all(),
            'coordinators' => $coordinators->values()->all(),
            'sales' => $sales->values()->all(),
            'operational' => [
                'lead_mismatches' => $leads,
                'agenda_mismatches' => $agendas,
                'sales_cases' => $salesCases,
            ],
            'active_team_selector' => $this->selectorSummary($users, $teams),
            'issues' => $issues,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function teamRows(): Collection
    {
        return DB::table('teams as t')
            ->leftJoin('branches as b', 'b.id', '=', 't.branch_id')
            ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
            ->leftJoin('users as supervisor', 'supervisor.id', '=', 't.supervisor_id')
            ->leftJoin('users as coordinator', 'coordinator.id', '=', 't.coordinator_id')
            ->select([
                't.id', 't.code', 't.name', 't.is_active', 't.branch_id', 'b.code as branch_code', 'b.name as branch_name',
                't.project_id', 'p.code as project_code', 'p.name as project_name', 't.supervisor_id', 'supervisor.name as supervisor_name',
                't.coordinator_id', 'coordinator.name as coordinator_name',
            ])
            ->orderBy('b.name')
            ->orderBy('t.name')
            ->get()
            ->map(fn (object $team): array => (array) $team);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function teamRoleRows(): Collection
    {
        return DB::table('team_user as tu')
            ->join('teams as t', 't.id', '=', 'tu.team_id')
            ->join('users as u', 'u.id', '=', 'tu.user_id')
            ->leftJoin('users as reports_to', 'reports_to.id', '=', 'u.reports_to_id')
            ->leftJoin('model_has_roles as mhr', function ($join): void {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', '=', 'App\\Models\\User')
                    ->whereColumn('mhr.team_id', 't.id');
            })
            ->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')
            ->select([
                'tu.team_id', 'tu.user_id', 'tu.is_default', 't.branch_id as team_branch_id', 't.name as team_name',
                't.coordinator_id as team_coordinator_id', 't.is_active as team_is_active', 'u.branch_id as user_branch_id',
                'u.name', 'u.reports_to_id', 'reports_to.name as reports_to_name', 'r.name as role_name',
            ])
            ->orderBy('tu.team_id')
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $row): array => (array) $row);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function globalRoleRows(): Collection
    {
        return DB::table('global_role_user as gru')
            ->join('users as u', 'u.id', '=', 'gru.user_id')
            ->join('roles as r', 'r.id', '=', 'gru.role_id')
            ->select(['u.id as user_id', 'u.name', 'u.branch_id', 'r.name as role_name'])
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $row): array => (array) $row);
    }

    /** @param Collection<int, array<string, mixed>> $teamRoles
     * @param  Collection<int, array<string, mixed>>  $globalRoles
     * @return Collection<int, array<string, mixed>>
     */
    private function userRows(Collection $teamRoles, Collection $globalRoles): Collection
    {
        $users = DB::table('users as u')
            ->leftJoin('branches as b', 'b.id', '=', 'u.branch_id')
            ->leftJoin('users as reports_to', 'reports_to.id', '=', 'u.reports_to_id')
            ->select(['u.id', 'u.name', 'u.branch_id', 'b.code as branch_code', 'b.name as branch_name', 'u.reports_to_id', 'reports_to.name as reports_to_name'])
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $row): array => [
                ...(array) $row,
                'team_memberships' => [],
                'team_roles' => [],
                'global_roles' => $globalRoles->where('user_id', $row->id)->pluck('role_name')->values()->all(),
            ]);

        foreach ($teamRoles as $row) {
            $index = $users->search(fn (array $user): bool => $user['id'] === $row['user_id']);
            if ($index === false) {
                continue;
            }

            $user = $users[$index];
            $user['team_memberships'][] = [
                'team_id' => $row['team_id'],
                'team_name' => $row['team_name'],
                'team_branch_id' => $row['team_branch_id'],
                'team_is_active' => (bool) $row['team_is_active'],
                'is_default' => (bool) $row['is_default'],
            ];
            if ($row['role_name'] !== null) {
                $user['team_roles'][] = ['team_id' => $row['team_id'], 'role' => $row['role_name']];
            }
            $users[$index] = $user;
        }

        return $users;
    }

    /** @param Collection<int, array<string, mixed>> $teams
     * @param  Collection<int, array<string, mixed>>  $teamRoles
     * @return Collection<int, array<string, mixed>>
     */
    private function teamSummaries(Collection $teams, Collection $teamRoles): Collection
    {
        return $teams->map(function (array $team) use ($teamRoles): array {
            $members = $teamRoles->where('team_id', $team['id'])->unique('user_id')->values();
            $coordinators = $teamRoles->where('team_id', $team['id'])->where('role_name', 'koordinator_sales')->unique('user_id')->values();
            $sales = $teamRoles->where('team_id', $team['id'])->where('role_name', 'sales')->unique('user_id')->values();

            return [
                ...$team,
                'is_active' => (bool) $team['is_active'],
                'is_legacy_migration' => $this->isLegacyTeam($team),
                'member_count' => $members->count(),
                'members' => $members->map(fn (array $member): array => [
                    'id' => $member['user_id'], 'name' => $member['name'], 'role' => $member['role_name'],
                    'branch_id' => $member['user_branch_id'], 'reports_to_id' => $member['reports_to_id'],
                ])->all(),
                'coordinator_role_users' => $coordinators->map(fn (array $member): array => ['id' => $member['user_id'], 'name' => $member['name']])->all(),
                'sales_users' => $sales->map(fn (array $member): array => ['id' => $member['user_id'], 'name' => $member['name']])->all(),
            ];
        });
    }

    /** @param Collection<int, array<string, mixed>> $teams
     * @return array<int, array<string, mixed>>
     */
    private function branchSummaries(Collection $branches, Collection $teams): array
    {
        return $branches->map(fn (object $branch): array => [
            'id' => $branch->id,
            'code' => $branch->code,
            'name' => $branch->name,
            'is_active' => (bool) $branch->is_active,
            'teams' => $teams->where('branch_id', $branch->id)->values()->all(),
        ])->all();
    }

    /** @param Collection<int, array<string, mixed>> $users
     * @param  Collection<int, array<string, mixed>>  $teams
     * @param  Collection<int, array<string, mixed>>  $teamRoles
     * @return Collection<int, array<string, mixed>>
     */
    private function coordinatorSummaries(Collection $users, Collection $teams, Collection $teamRoles): Collection
    {
        $coordinatorIds = $teamRoles->where('role_name', 'koordinator_sales')->pluck('user_id')->unique();

        return $coordinatorIds->map(function (int $userId) use ($users, $teams, $teamRoles): array {
            $user = $users->firstWhere('id', $userId);
            $memberships = collect($user['team_memberships'] ?? [])->filter(fn (array $membership): bool => $membership['team_is_active'])->values();
            $coordinatedTeams = $teams->where('is_active', true)->where('coordinator_id', $userId)->values();
            $roleTeams = $teamRoles->where('user_id', $userId)->where('role_name', 'koordinator_sales')->pluck('team_id')->unique()->values();
            $candidateSales = $memberships->flatMap(fn (array $membership) => $teamRoles
                ->where('team_id', $membership['team_id'])
                ->where('role_name', 'sales')
                ->unique('user_id')
                ->map(fn (array $sales): array => ['id' => $sales['user_id'], 'name' => $sales['name'], 'team_id' => $membership['team_id']]))
                ->unique(fn (array $sales): string => $sales['team_id'].'-'.$sales['id'])
                ->values();

            return [
                'id' => $user['id'], 'name' => $user['name'], 'branch_id' => $user['branch_id'], 'branch_name' => $user['branch_name'],
                'reports_to_id' => $user['reports_to_id'], 'reports_to_name' => $user['reports_to_name'],
                'active_team_ids' => $memberships->pluck('team_id')->all(), 'role_team_ids' => $roleTeams->all(),
                'coordinated_team_ids' => $coordinatedTeams->pluck('id')->all(), 'candidate_sales' => $candidateSales->all(),
                'mapping_status' => $this->coordinatorMappingStatus($user, $memberships, $coordinatedTeams, $roleTeams, $teamRoles),
            ];
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $users
     * @param  Collection<int, array<string, mixed>>  $teams
     * @param  Collection<int, array<string, mixed>>  $teamRoles
     * @return Collection<int, array<string, mixed>>
     */
    private function salesSummaries(Collection $users, Collection $teams, Collection $teamRoles): Collection
    {
        $salesIds = $teamRoles->where('role_name', 'sales')->pluck('user_id')->unique();

        $coordinatorRoleUserIds = $teamRoles->where('role_name', 'koordinator_sales')->pluck('user_id')->unique();

        return $salesIds->map(function (int $userId) use ($users, $teams, $coordinatorRoleUserIds): array {
            $user = $users->firstWhere('id', $userId);
            $memberships = collect($user['team_memberships'] ?? [])->filter(fn (array $membership): bool => $membership['team_is_active'])->values();
            $coordinatorCandidates = $memberships->map(function (array $membership) use ($teams): ?array {
                $team = $teams->firstWhere('id', $membership['team_id']);
                if ($team === null || $team['coordinator_id'] === null) {
                    return null;
                }

                return ['id' => $team['coordinator_id'], 'name' => $team['coordinator_name'], 'team_id' => $team['id']];
            })->filter()->values();
            $reportsToIsCoordinator = $user['reports_to_id'] !== null && $coordinatorRoleUserIds->contains((int) $user['reports_to_id']);

            return [
                'id' => $user['id'], 'name' => $user['name'], 'branch_id' => $user['branch_id'], 'branch_name' => $user['branch_name'],
                'reports_to_id' => $user['reports_to_id'], 'reports_to_name' => $user['reports_to_name'],
                'active_team_count' => $memberships->count(), 'active_teams' => $memberships->all(),
                'expected_coordinators' => $coordinatorCandidates->all(),
                'reports_to_is_coordinator' => $reportsToIsCoordinator,
                'mapping_status' => $this->salesMappingStatus($memberships, $coordinatorCandidates, $reportsToIsCoordinator),
                'dashboard_ready' => $memberships->count() === 1 && $coordinatorCandidates->count() === 1,
            ];
        })->values();
    }

    /** @param array<string, mixed> $user
     * @param  Collection<int, array<string, mixed>>  $memberships
     * @param  Collection<int, array<string, mixed>>  $coordinatedTeams
     * @param  Collection<int, int>  $roleTeams
     * @param  Collection<int, array<string, mixed>>  $teamRoles
     */
    private function coordinatorMappingStatus(array $user, Collection $memberships, Collection $coordinatedTeams, Collection $roleTeams, Collection $teamRoles): string
    {
        if ($coordinatedTeams->isNotEmpty() && $coordinatedTeams->every(fn (array $team): bool => $roleTeams->contains($team['id']) && $memberships->pluck('team_id')->contains($team['id']))) {
            return 'CONFIRMED';
        }

        $candidateTeams = $memberships->filter(fn (array $membership): bool => $teamRoles->where('team_id', $membership['team_id'])->where('role_name', 'koordinator_sales')->unique('user_id')->count() === 1);

        if ($candidateTeams->count() === 1 && $coordinatedTeams->isEmpty()) {
            return 'HIGH CONFIDENCE';
        }

        return $memberships->isEmpty() ? 'UNRESOLVED' : 'AMBIGUOUS';
    }

    /** @param Collection<int, array<string, mixed>> $memberships
     * @param  Collection<int, array<string, mixed>>  $coordinators
     */
    private function salesMappingStatus(Collection $memberships, Collection $coordinators, bool $reportsToIsCoordinator): string
    {
        if ($memberships->count() > 1) {
            return 'AMBIGUOUS';
        }
        if ($coordinators->count() === 1) {
            return 'CONFIRMED';
        }

        return $memberships->isEmpty() ? 'UNRESOLVED' : 'AMBIGUOUS';
    }

    /** @return array<int, array<string, mixed>> */
    private function leadMismatches(): array
    {
        return DB::table('leads as l')
            ->leftJoin('teams as t', 't.id', '=', 'l.team_id')
            ->leftJoin('users as u', 'u.id', '=', 'l.sales_pic_id')
            ->leftJoin('branches as b', 'b.id', '=', 'l.branch_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('lead_cutover_dispositions as lcd')
                    ->whereColumn('lcd.lead_id', 'l.id')
                    ->where('lcd.classification', 'EXCLUDE_FROM_CUTOVER_OLD_UNRESOLVED');
            })
            ->where(function ($query): void {
                $query->whereNull('l.team_id')->orWhereNull('l.sales_pic_id')->orWhereColumn('l.branch_id', '<>', 'u.branch_id')->orWhereColumn('t.branch_id', '<>', 'l.branch_id')->orWhereNotExists(function ($subquery): void {
                    $subquery->selectRaw('1')->from('team_user as tu')->whereColumn('tu.team_id', 'l.team_id')->whereColumn('tu.user_id', 'l.sales_pic_id');
                });
            })
            ->select(['l.id', 'l.id_lead', 'l.branch_id', 'b.name as branch_name', 'l.team_id', 't.name as team_name', 'l.sales_pic_id', 'u.name as sales_name', 'u.branch_id as sales_branch_id'])
            ->orderBy('l.id')
            ->limit(250)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function agendaMismatches(): array
    {
        return DB::table('sales_agendas as a')
            ->leftJoin('teams as t', 't.id', '=', 'a.team_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.owner_id')
            ->leftJoin('leads as l', 'l.id', '=', 'a.lead_id')
            ->where(function ($query): void {
                $query->whereNull('a.team_id')->orWhereNull('a.owner_id')->orWhereColumn('a.branch_id', '<>', 'u.branch_id')->orWhereColumn('t.branch_id', '<>', 'a.branch_id')->orWhere(function ($query): void {
                    $query->whereNotNull('a.lead_id')->where(function ($query): void {
                        $query->whereColumn('l.team_id', '<>', 'a.team_id')->orWhereColumn('l.branch_id', '<>', 'a.branch_id');
                    });
                });
            })
            ->select(['a.id', 'a.branch_id', 'a.team_id', 't.name as team_name', 'a.owner_id', 'u.name as owner_name', 'a.lead_id', 'l.team_id as lead_team_id', 'l.branch_id as lead_branch_id'])
            ->orderBy('a.id')
            ->limit(250)
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return array<string, mixed> */
    private function salesCaseSummary(): array
    {
        $query = DB::table('sales_cases as sc')
            ->join('lots as l', 'l.id', '=', 'sc.lot_id')
            ->join('projects as p', 'p.id', '=', 'l.project_id')
            ->leftJoin('users as u', 'u.id', '=', 'sc.created_by');

        return [
            'active' => (int) (clone $query)->whereNull('sc.cancelled_at')->where(function ($statusQuery): void {
                $statusQuery->whereNull('sc.consumer_status')->orWhere('sc.consumer_status', '!=', 'MUNDUR');
            })->count(),
            'creator_branch_mismatches' => (int) (clone $query)->whereNotNull('sc.created_by')->whereColumn('u.branch_id', '<>', 'p.branch_id')->count(),
            'team_owned' => false,
            'note' => 'Sales cases do not currently carry team_id or sales_pic_id; team scope is inferred through linked agenda/lead or project branch.',
        ];
    }

    /** @param Collection<int, array<string, mixed>> $users
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<string, mixed>
     */
    private function selectorSummary(Collection $users, Collection $teams): array
    {
        $multiTeamUsers = $users->map(fn (array $user): array => [...$user, 'active_team_count' => collect($user['team_memberships'])->where('team_is_active', true)->count()])->filter(fn (array $user): bool => $user['active_team_count'] > 1)->values();
        $crossBranchUsers = $users->filter(function (array $user): bool {
            return collect($user['team_memberships'])->where('team_is_active', true)->contains(fn (array $membership): bool => $membership['team_branch_id'] !== $user['branch_id']);
        })->values();

        return [
            'implementation' => 'SetPermissionTeam exposes all active team_user memberships except super_admin, then selects requested/default/first team.',
            'multi_team_users' => $multiTeamUsers->map(fn (array $user): array => ['id' => $user['id'], 'name' => $user['name'], 'active_team_count' => $user['active_team_count']])->all(),
            'cross_branch_memberships' => $crossBranchUsers->map(fn (array $user): array => ['id' => $user['id'], 'name' => $user['name']])->all(),
            'branch_roles_in_team_memberships' => $this->branchRoleMemberships($users),
            'finding' => $multiTeamUsers->isEmpty() && $crossBranchUsers->isEmpty() && $this->branchRoleMemberships($users) === []
                ? 'No cross-branch, multi-team, or branch-role Team memberships detected.'
                : 'Review selector memberships before cutover.',
        ];
    }

    /** @param Collection<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function branchRoleMemberships(Collection $users): array
    {
        $branchRoleNames = ['bm', 'spv', 'sas', 'sap', 'sam'];

        return $users->filter(function (array $user) use ($branchRoleNames): bool {
            return collect($user['global_roles'])->intersect($branchRoleNames)->isNotEmpty() && collect($user['team_memberships'])->isNotEmpty();
        })->map(fn (array $user): array => ['id' => $user['id'], 'name' => $user['name'], 'global_roles' => $user['global_roles'], 'team_ids' => collect($user['team_memberships'])->pluck('team_id')->all()])->values()->all();
    }

    /** @param Collection<int, object> $branches
     * @param  Collection<int, array<string, mixed>>  $teams
     * @param  Collection<int, array<string, mixed>>  $users
     * @param  Collection<int, array<string, mixed>>  $coordinators
     * @param  Collection<int, array<string, mixed>>  $sales
     * @param  Collection<int, array<string, mixed>>  $teamRoles
     * @param  array<int, array<string, mixed>>  $leads
     * @param  array<int, array<string, mixed>>  $agendas
     * @return array<int, array<string, mixed>>
     */
    private function issues(Collection $teams, Collection $users, Collection $coordinators, Collection $sales, Collection $teamRoles, array $leads, array $agendas): array
    {
        $issues = [];
        $this->addIssue($issues, 'CRITICAL', 'coordinator_no_usable_active_team', 'Coordinator role users without a Team whose coordinator_id points to them.', $coordinators->where('coordinated_team_ids', [])->count(), $coordinators->where('coordinated_team_ids', [])->map(fn (array $coordinator): array => ['id' => $coordinator['id'], 'name' => $coordinator['name']])->values()->all());
        $this->addIssue($issues, 'CRITICAL', 'lead_team_sales_mismatch', 'Leads do not consistently match the Sales PIC branch and Team membership.', count($leads), array_slice($leads, 0, 20));
        $this->addIssue($issues, 'CRITICAL', 'agenda_team_scope_mismatch', 'Agendas have owner, branch, Team, or linked Lead scope mismatches.', count($agendas), array_slice($agendas, 0, 20));

        $this->addIssue($issues, 'WARNING', 'legacy_migration_team', 'Active Team is a legacy migration container and is not a final Coordinator Team.', $teams->filter(fn (array $team): bool => $this->isLegacyTeam($team))->count(), $teams->filter(fn (array $team): bool => $this->isLegacyTeam($team))->map(fn (array $team): array => ['id' => $team['id'], 'name' => $team['name']])->values()->all());
        $this->addIssue($issues, 'WARNING', 'team_without_coordinator', 'Active Team has no coordinator_id.', $teams->where('is_active', true)->whereNull('coordinator_id')->count(), $teams->where('is_active', true)->whereNull('coordinator_id')->map(fn (array $team): array => ['id' => $team['id'], 'name' => $team['name']])->values()->all());
        $this->addIssue($issues, 'WARNING', 'team_without_project', 'Active Team has no project assignment.', $teams->where('is_active', true)->whereNull('project_id')->count(), $teams->where('is_active', true)->whereNull('project_id')->map(fn (array $team): array => ['id' => $team['id'], 'name' => $team['name']])->values()->all());
        $multipleCoordinatorTeams = $teams->filter(fn (array $team): bool => count($team['coordinator_role_users']) > 1);
        $this->addIssue($issues, 'WARNING', 'multiple_coordinator_roles_in_team', 'Active Team contains multiple users with the Coordinator role.', $multipleCoordinatorTeams->count(), $multipleCoordinatorTeams->map(fn (array $team): array => ['id' => $team['id'], 'name' => $team['name'], 'coordinators' => $team['coordinator_role_users']])->values()->all());
        $this->addIssue($issues, 'WARNING', 'anonymized_users_in_team', 'Team membership references anonymized users that need confirmation before cutover.', $users->filter(fn (array $user): bool => str_starts_with((string) $user['name'], 'Pengguna Teranomimasi'))->count());
        $membersWithoutTeamRole = $teamRoles->whereNull('role_name');
        $this->addIssue($issues, 'WARNING', 'team_member_without_team_role', 'A Team member has no Team-scoped role assignment.', $membersWithoutTeamRole->unique('user_id')->count(), $membersWithoutTeamRole->map(fn (array $member): array => ['id' => $member['user_id'], 'name' => $member['name'], 'team_id' => $member['team_id'], 'team_name' => $member['team_name']])->values()->all());
        $this->addIssue($issues, 'WARNING', 'reports_to_disagreement', 'Sales reports_to_id does not identify a Coordinator in the expected active Team.', $sales->filter(fn (array $sale): bool => $sale['reports_to_id'] !== null && ! $sale['reports_to_is_coordinator'])->count());

        $branchRoleMemberships = $this->branchRoleMemberships($users);
        $this->addIssue($issues, 'INFO', 'branch_role_team_membership', 'Branch-scoped roles are members of a Team; this is not required by the target organization model and should be reviewed separately.', count($branchRoleMemberships), $branchRoleMemberships);
        $this->addIssue($issues, 'INFO', 'sales_case_has_no_team_owner', 'Sales cases have no native team_id or sales_pic_id; process scope currently relies on project branch and linked agenda/lead.', 1);

        return $issues;
    }

    /** @param array<int, array<string, mixed>> $issues
     * @param  array<int, array<string, mixed>>  $examples
     */
    private function addIssue(array &$issues, string $severity, string $code, string $message, int $count, array $examples = []): void
    {
        if ($count === 0) {
            return;
        }

        $issues[] = ['severity' => $severity, 'code' => $code, 'message' => $message, 'count' => $count, 'examples' => $examples];
    }

    /** @param array<string, mixed> $team */
    private function isLegacyTeam(array $team): bool
    {
        return str_starts_with((string) $team['name'], 'Migrasi Tim') || str_starts_with((string) $team['code'], 'SRC-TEAM-BRANCH-');
    }
}
