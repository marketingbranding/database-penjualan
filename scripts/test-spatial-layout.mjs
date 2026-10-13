import { createWorkspaceLayout, layoutDecorations, layoutPawnPositions, validateLayout } from '../resources/js/spatial/layout.js';

const rand = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

const randomWorkspace = (index) => {
    if (index === 0) {
        return { branches: [], users: [] };
    }
    if (index === 1) {
        return {
            branches: [{ id: 1, name: 'Cabang', code: 'C1', teams: [], userIds: [] }],
            users: [],
        };
    }
    if (index === 2) {
        const users = Array.from({ length: 100 }, (_, i) => ({ id: i + 1, name: `User ${i + 1}` }));

        return {
            branches: [{ id: 1, name: 'Cabang', code: 'C1', teams: [{ id: 1, name: 'Tim', code: 'T1', userIds: users.map((u) => u.id) }], userIds: users.map((u) => u.id) }],
            users,
        };
    }
    const branchCount = rand(0, 10);
    const branches = [];
    let userId = 1;
    const users = [];
    for (let b = 0; b < branchCount; b += 1) {
        const teamCount = rand(0, 8);
        const teams = [];
        for (let t = 0; t < teamCount; t += 1) {
            const size = Math.random() < 0.05 ? 100 : rand(0, 20);
            const userIds = [];
            for (let u = 0; u < size; u += 1) {
                users.push({ id: userId, name: `User ${userId}` });
                userIds.push(userId);
                userId += 1;
            }
            teams.push({ id: b * 100 + t + 1, name: `Tim ${b}-${t}`, code: `T${b}-${t}`, userIds });
        }
        branches.push({ id: b + 1, name: `Cabang ${b + 1}`, code: `C${b + 1}`, teams, userIds: teams.flatMap((t) => t.userIds) });
    }
    const unassignedCount = rand(0, 40);
    for (let u = 0; u < unassignedCount; u += 1) {
        users.push({ id: userId, name: `Unassigned ${userId}` });
        userId += 1;
    }

    return { branches, users };
};

const failures = [];
const total = 500;

for (let i = 0; i < total; i += 1) {
    const workspace = randomWorkspace(i);
    const layout = createWorkspaceLayout(workspace);
    const first = layoutPawnPositions(layout, workspace);
    const second = layoutPawnPositions(layout, workspace);
    const firstEntries = [...first.positions.entries()];
    const secondEntries = [...second.positions.entries()];
    if (JSON.stringify(firstEntries) !== JSON.stringify(secondEntries)) {
        failures.push(`case ${i}: non-deterministic positions`);
        continue;
    }
    const errors = validateLayout(layout, first.positions);
    if (errors.length > 0) {
        failures.push(`case ${i}: ${errors.slice(0, 3).join('; ')}`);
        continue;
    }
    const perTeam = new Map();
    first.positions.forEach((position, userId) => {
        const key = position.teamId ?? 'unassigned';
        perTeam.set(key, (perTeam.get(key) ?? 0) + 1);
    });
    for (const [team, count] of perTeam) {
        if (count > 12) {
            failures.push(`case ${i}: team ${team} shows ${count} pawns`);
            break;
        }
    }
    const decor = layoutDecorations(layout, 42);
    const plots = [
        ...(layout.branches ?? []).flatMap((branch) => [
            { x: branch.x, z: branch.z, width: branch.width, depth: branch.depth },
            ...(branch.teams ?? []).map((team) => ({ x: team.x, z: team.z, width: team.width, depth: team.depth })),
        ]),
        { x: layout.unassigned.x, z: layout.unassigned.z, width: layout.unassigned.width, depth: layout.unassigned.depth },
    ];
    const badDecor = decor.find(([x, z]) => plots.some((plot) => (
        Math.abs(x - plot.x) <= plot.width / 2 + 1.5 && Math.abs(z - plot.z) <= plot.depth / 2 + 1.5
    )));
    if (badDecor) {
        failures.push(`case ${i}: decor too close to plot`);
    }
}

if (failures.length > 0) {
    console.error(`${failures.length}/${total} layout cases failed:`);
    failures.slice(0, 20).forEach((failure) => console.error(`- ${failure}`));
    process.exit(1);
}

console.log(`All ${total} spatial layout cases passed.`);
