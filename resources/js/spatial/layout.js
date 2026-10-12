const MIN_BRANCH_SIZE = 5;
const BRANCH_GAP = 2;
const TEAM_SIZE = 4.2;
const TEAM_GAP = 1.1;

const gridDimension = (count) => Math.max(1, Math.ceil(Math.sqrt(Math.max(1, count))));

const centeredGrid = (count, columns, spacing, centerX, centerZ) => {
    const rows = Math.max(1, Math.ceil(count / columns));
    const width = (columns - 1) * spacing;
    const depth = (rows - 1) * spacing;

    return Array.from({ length: count }, (_, index) => ({
        x: centerX - width / 2 + (index % columns) * spacing,
        z: centerZ - depth / 2 + Math.floor(index / columns) * spacing,
    }));
};

export const createWorkspaceLayout = (workspace) => {
    const branches = workspace.branches ?? [];
    const branchColumns = gridDimension(branches.length);
    const branchRows = Math.max(1, Math.ceil(branches.length / branchColumns));
    const branchSizes = branches.map((branch) => {
        const teamColumns = gridDimension(branch.teams?.length ?? 0);
        const teamRows = Math.max(1, Math.ceil((branch.teams?.length ?? 0) / teamColumns));

        return {
            width: Math.max(MIN_BRANCH_SIZE, TEAM_SIZE * teamColumns + TEAM_GAP * (teamColumns - 1) + 1),
            depth: Math.max(MIN_BRANCH_SIZE, TEAM_SIZE * teamRows + TEAM_GAP * (teamRows - 1) + 1),
            teamColumns,
        };
    });
    const branchWidth = Math.max(MIN_BRANCH_SIZE, ...branchSizes.map((size) => size.width));
    const branchDepth = Math.max(MIN_BRANCH_SIZE, ...branchSizes.map((size) => size.depth));
    const islandWidth = Math.max(13, branchColumns * branchWidth + (branchColumns + 1) * BRANCH_GAP);
    const islandDepth = Math.max(13, branchRows * branchDepth + (branchRows + 1) * BRANCH_GAP);
    const branchPositions = centeredGrid(
        branches.length,
        branchColumns,
        Math.max(branchWidth, branchDepth) + BRANCH_GAP,
        0,
        0,
    );

    return {
        island: { width: islandWidth, depth: islandDepth, height: 0.45 },
        branches: branches.map((branch, branchIndex) => {
            const size = branchSizes[branchIndex];
            const position = branchPositions[branchIndex];
            const teamPositions = centeredGrid(
                branch.teams?.length ?? 0,
                size.teamColumns,
                TEAM_SIZE + TEAM_GAP,
                position.x,
                position.z,
            );

            return {
                ...branch,
                x: position.x,
                z: position.z,
                width: size.width,
                depth: size.depth,
                teams: (branch.teams ?? []).map((team, teamIndex) => ({
                    ...team,
                    x: teamPositions[teamIndex].x,
                    z: teamPositions[teamIndex].z,
                    width: TEAM_SIZE,
                    depth: TEAM_SIZE,
                })),
            };
        }),
    };
};
