const MIN_BRANCH_SIZE = 5;
const BRANCH_GAP = 2;
const TEAM_SIZE = 4.2;
const TEAM_GAP = 1.1;
const UNASSIGNED_WIDTH = 6;
const UNASSIGNED_DEPTH = 5;
const UNASSIGNED_GAP = 2.5;
const MAX_VISIBLE_PAWNS_PER_TEAM = 12;
const MAX_VISIBLE_UNASSIGNED = 12;
const PAWN_SPACING = 1.1;
const PAWN_MIN_DISTANCE = 0.9;

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

const sortedIds = (ids) => [...new Set(ids)].sort((a, b) => Number(a) - Number(b));

const pawnGridPositions = (ids, centerX, centerZ) => {
    const visible = sortedIds(ids).slice(0, MAX_VISIBLE_PAWNS_PER_TEAM);
    const columns = Math.max(1, Math.min(4, Math.ceil(Math.sqrt(visible.length))));
    const spacing = visible.length > 1 ? PAWN_SPACING : 0;
    const rows = Math.max(1, Math.ceil(visible.length / columns));
    const width = (columns - 1) * spacing;
    const depth = (rows - 1) * spacing;

    return visible.map((userId, index) => ({
        userId,
        x: centerX - width / 2 + (index % columns) * spacing,
        z: centerZ - depth / 2 + Math.floor(index / columns) * spacing,
    }));
};

const rectsOverlapWithGap = (a, b, gap = 2) => (
    Math.abs(a.x - b.x) < (a.width + b.width) / 2 + gap
    && Math.abs(a.z - b.z) < (a.depth + b.depth) / 2 + gap
);

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
    const baseIslandWidth = Math.max(13, branchColumns * branchWidth + (branchColumns + 1) * BRANCH_GAP);
    const baseIslandDepth = Math.max(13, branchRows * branchDepth + (branchRows + 1) * BRANCH_GAP);
    const branchPositions = centeredGrid(
        branches.length,
        branchColumns,
        Math.max(branchWidth, branchDepth) + BRANCH_GAP,
        0,
        0,
    );

    const laidBranches = branches.map((branch, branchIndex) => {
        const size = branchSizes[branchIndex];
        const position = branchPositions[branchIndex] ?? { x: 0, z: 0 };
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
                x: teamPositions[teamIndex]?.x ?? position.x,
                z: teamPositions[teamIndex]?.z ?? position.z,
                width: TEAM_SIZE,
                depth: TEAM_SIZE,
            })),
        };
    });

    const unassigned = {
        id: 'unassigned',
        code: 'BELUM-DITUGASKAN',
        name: 'Belum ditugaskan',
        x: -baseIslandWidth / 2 - UNASSIGNED_WIDTH / 2 - UNASSIGNED_GAP,
        z: baseIslandDepth / 2 + UNASSIGNED_DEPTH / 2 + UNASSIGNED_GAP,
        width: UNASSIGNED_WIDTH,
        depth: UNASSIGNED_DEPTH,
        height: 0.2,
    };
    const margin = 2;
    const minX = Math.min(-baseIslandWidth / 2, unassigned.x - unassigned.width / 2);
    const maxX = Math.max(baseIslandWidth / 2, unassigned.x + unassigned.width / 2);
    const minZ = Math.min(-baseIslandDepth / 2, unassigned.z - unassigned.depth / 2);
    const maxZ = Math.max(baseIslandDepth / 2, unassigned.z + unassigned.depth / 2);

    return {
        island: {
            width: Math.max(13, maxX - minX + margin * 2),
            depth: Math.max(13, maxZ - minZ + margin * 2),
            height: 0.45,
            x: (minX + maxX) / 2,
            z: (minZ + maxZ) / 2,
        },
        branches: laidBranches,
        unassigned,
    };
};

export const layoutPawnPositions = (layout, workspace) => {
    const userMap = new Map((workspace.users ?? []).map((user) => [user.id, user]));
    const positions = new Map();
    const teamUserIds = new Set();
    const overflowByTeam = new Map();

    (layout.branches ?? []).forEach((branch) => (branch.teams ?? []).forEach((team) => {
        const ids = sortedIds(team.userIds ?? []);
        ids.forEach((userId) => teamUserIds.add(userId));
        const visible = ids.slice(0, MAX_VISIBLE_PAWNS_PER_TEAM);
        overflowByTeam.set(team.id, Math.max(0, ids.length - visible.length));
        pawnGridPositions(visible, team.x, team.z).forEach(({ userId, x, z }) => {
            if (! userMap.has(userId)) {
                return;
            }
            positions.set(userId, { x, y: 0.74, z, teamId: team.id });
        });
    }));

    const unassignedUsers = sortedIds(
        (workspace.users ?? [])
            .filter((user) => ! teamUserIds.has(user.id))
            .map((user) => user.id),
    );
    const visibleUnassigned = unassignedUsers.slice(0, MAX_VISIBLE_UNASSIGNED);
    const unassignedOverflow = Math.max(0, unassignedUsers.length - visibleUnassigned.length);
    const unassignedPlot = layout.unassigned;
    pawnGridPositions(visibleUnassigned, unassignedPlot.x, unassignedPlot.z).forEach(({ userId, x, z }) => {
        positions.set(userId, { x, y: 0.3, z, teamId: null });
    });

    return { positions, overflowByTeam, unassignedOverflow, unassignedUserIds: unassignedUsers };
};

const mulberry32 = (seed) => {
    let value = seed >>> 0;

    return () => {
        value += 0x6D2B79F5;
        let result = Math.imul(value ^ (value >>> 15), 1 | value);
        result ^= result + Math.imul(result ^ (result >>> 7), 61 | result);

        return ((result ^ (result >>> 14)) >>> 0) / 4294967296;
    };
};

const labelAnchorPoints = (layout) => {
    const points = [];
    (layout.branches ?? []).forEach((branch) => {
        points.push({ x: branch.x - branch.width / 2 + 0.55, z: branch.z + branch.depth / 2 + 0.28 });
        (branch.teams ?? []).forEach((team) => {
            points.push({ x: team.x, z: team.z });
        });
    });
    if (layout.unassigned) {
        points.push({ x: layout.unassigned.x, z: layout.unassigned.z });
    }

    return points;
};

export const layoutDecorations = (layout, seed = 42) => {
    const random = mulberry32(seed);
    const plots = [
        ...(layout.branches ?? []).flatMap((branch) => [
            { x: branch.x, z: branch.z, width: branch.width, depth: branch.depth },
            ...(branch.teams ?? []).map((team) => ({ x: team.x, z: team.z, width: team.width, depth: team.depth })),
        ]),
        ...(layout.unassigned ? [{ x: layout.unassigned.x, z: layout.unassigned.z, width: layout.unassigned.width, depth: layout.unassigned.depth }] : []),
    ];
    const labels = labelAnchorPoints(layout);
    const candidates = [];
    const step = 2;
    const centerX = layout.island.x ?? 0;
    const centerZ = layout.island.z ?? 0;
    for (let x = centerX - layout.island.width / 2 + 1; x <= centerX + layout.island.width / 2 - 1; x += step) {
        for (let z = centerZ - layout.island.depth / 2 + 1; z <= centerZ + layout.island.depth / 2 - 1; z += step) {
            const jx = x + (random() - 0.5) * 1.2;
            const jz = z + (random() - 0.5) * 1.2;
            const farFromPlots = plots.every((plot) => (
                Math.abs(jx - plot.x) > plot.width / 2 + 1.5 || Math.abs(jz - plot.z) > plot.depth / 2 + 1.5
            ));
            const farFromLabels = labels.every((label) => Math.hypot(jx - label.x, jz - label.z) >= 2);
            if (farFromPlots && farFromLabels) {
                candidates.push([jx, jz]);
            }
        }
    }
    const maxTrees = Math.max(4, Math.floor(candidates.length / 6));

    return candidates.slice(0, maxTrees);
};

export const validateLayout = (layout, positions) => {
    const errors = [];
    const plotOf = new Map();
    (layout.branches ?? []).forEach((branch) => {
        (branch.teams ?? []).forEach((team) => {
            (team.userIds ?? []).slice(0, MAX_VISIBLE_PAWNS_PER_TEAM).forEach((userId) => {
                if (positions.has(userId)) {
                    plotOf.set(userId, { x: team.x, z: team.z, width: team.width, depth: team.depth });
                }
            });
        });
    });
    if (layout.unassigned) {
        positions.forEach((position, userId) => {
            if (position.teamId === null) {
                plotOf.set(userId, { x: layout.unassigned.x, z: layout.unassigned.z, width: layout.unassigned.width, depth: layout.unassigned.depth });
            }
        });
    }

    positions.forEach((position, userId) => {
        const plot = plotOf.get(userId);
        if (! plot) {
            return;
        }
        if (Math.abs(position.x - plot.x) > plot.width / 2 - 0.2 || Math.abs(position.z - plot.z) > plot.depth / 2 - 0.2) {
            errors.push(`pawn ${userId} outside plot`);
        }
    });

    const entries = [...positions.entries()];
    for (let i = 0; i < entries.length; i += 1) {
        for (let j = i + 1; j < entries.length; j += 1) {
            const [, a] = entries[i];
            const [, b] = entries[j];
            if (a.teamId !== b.teamId) {
                continue;
            }
            if (Math.hypot(a.x - b.x, a.z - b.z) < PAWN_MIN_DISTANCE - 1e-6) {
                errors.push(`pawns ${entries[i][0]} and ${entries[j][0]} overlap`);
                i = entries.length;
                break;
            }
        }
    }

    if (layout.unassigned) {
        const allPlots = (layout.branches ?? []).flatMap((branch) => [
            { x: branch.x, z: branch.z, width: branch.width, depth: branch.depth },
            ...(branch.teams ?? []).map((team) => ({ x: team.x, z: team.z, width: team.width, depth: team.depth })),
        ]);
        allPlots.forEach((plot, index) => {
            if (rectsOverlapWithGap(plot, layout.unassigned, 2)) {
                errors.push(`unassigned overlaps plot ${index}`);
            }
        });
    }

    return errors;
};

export const PAWN_LAYOUT_RULES = Object.freeze({
    maxVisiblePerTeam: MAX_VISIBLE_PAWNS_PER_TEAM,
    maxVisibleUnassigned: MAX_VISIBLE_UNASSIGNED,
    minPawnDistance: PAWN_MIN_DISTANCE,
    unassignedSize: Object.freeze({ width: UNASSIGNED_WIDTH, depth: UNASSIGNED_DEPTH }),
});
