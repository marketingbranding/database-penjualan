const ROLE_NAMES = ['sales', 'koordinator_sales', 'spv', 'sas', 'freelance'];

const createMockUser = (id, branchId, teamId = null) => {
    const role = ROLE_NAMES[id % ROLE_NAMES.length];
    const isGlobal = teamId === null && id % 11 === 0;

    return {
        id,
        name: `Mock User ${String(id).padStart(3, '0')}`,
        email: `mock-user-${id}@example.test`,
        branchId,
        reportsToId: id > 1 && id % 7 !== 0 ? id - 1 : null,
        teamIds: teamId === null ? [] : [teamId],
        teamNames: teamId === null ? [] : [`Mock Tim ${String(teamId).padStart(2, '0')}`],
        globalRoles: isGlobal ? ['sas'] : [],
        roles: teamId === null || isGlobal ? [] : [{ name: role, teamId }],
    };
};

const createMockWorkspace = ({ branches = 10, teams = 50, users = 300 } = {}) => {
    const branchCount = Math.max(1, Math.floor(branches));
    const teamCount = Math.max(branchCount, Math.floor(teams));
    const userCount = Math.max(1, Math.floor(users));
    const teamsPerBranch = Math.ceil(teamCount / branchCount);
    const branchList = Array.from({ length: branchCount }, (_, index) => ({
        id: index + 1,
        code: `MOCK-${String(index + 1).padStart(2, '0')}`,
        name: `Mock Cabang ${String(index + 1).padStart(2, '0')}`,
        teams: [],
        userIds: [],
    }));
    const teamList = [];

    for (let index = 0; index < teamCount; index += 1) {
        const branch = branchList[Math.min(branchList.length - 1, Math.floor(index / teamsPerBranch))];
        const team = {
            id: index + 1,
            code: `MOCK-TEAM-${String(index + 1).padStart(2, '0')}`,
            name: `Mock Tim ${String(index + 1).padStart(2, '0')}`,
            parentId: null,
            supervisorId: null,
            coordinatorId: null,
            userIds: [],
            userCount: 0,
        };
        branch.teams.push(team);
        teamList.push({ team, branch });
    }

    const unassignedCount = Math.min(40, Math.max(0, userCount - 1));
    const assignedCount = userCount - unassignedCount;
    const crowdedTeamCount = Math.min(5, teamList.length);
    const crowdedTeamSize = Math.min(20, Math.max(0, Math.floor(assignedCount / Math.max(1, crowdedTeamCount))));
    const usersList = [];
    for (let index = 0; index < userCount; index += 1) {
        const id = index + 1;
        const isUnassigned = index < unassignedCount;
        const assignedIndex = index - unassignedCount;
        const assignmentIndex = isUnassigned
            ? null
            : assignedIndex < crowdedTeamCount * crowdedTeamSize
                ? Math.floor(assignedIndex / crowdedTeamSize)
                : crowdedTeamCount + (assignedIndex - crowdedTeamCount * crowdedTeamSize) % Math.max(1, teamList.length - crowdedTeamCount);
        const assignment = assignmentIndex === null ? null : teamList[assignmentIndex];
        const user = createMockUser(id, assignment?.branch.id ?? ((index % branchCount) + 1), assignment?.team.id ?? null);

        usersList.push(user);
        if (assignment) {
            assignment.team.userIds.push(id);
            assignment.team.userCount += 1;
            assignment.branch.userIds.push(id);
            if (user.roles[0]?.name === 'spv') {
                assignment.team.supervisorId = id;
            }
            if (user.roles[0]?.name === 'koordinator_sales') {
                assignment.team.coordinatorId = id;
            }
        }
    }

    branchList.forEach((branch) => {
        branch.userIds = [...new Set(branch.userIds)];
    });

    return {
        branches: branchList,
        users: usersList,
    };
};

export const parseMockWorkspaceQuery = (search = window.location.search) => {
    const value = new URLSearchParams(search).get('mock');
    const match = value?.match(/^(\d+)x(\d+)x(\d+)$/);

    return match
        ? { branches: Number(match[1]), teams: Number(match[2]), users: Number(match[3]) }
        : null;
};

export { createMockWorkspace };
