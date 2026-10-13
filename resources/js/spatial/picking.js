import * as THREE from 'three';

const PICK_PLANE_Y = 0.74;
const PICK_RADIUS = 0.65;
const CELL_SIZE = 1.5;

const cellKey = (x, z) => `${Math.floor(x / CELL_SIZE)},${Math.floor(z / CELL_SIZE)}`;

export const buildPawnGrid = (pawns) => {
    const grid = new Map();
    pawns.forEach((pawn) => {
        const key = cellKey(pawn.position.x, pawn.position.z);
        if (! grid.has(key)) {
            grid.set(key, []);
        }
        grid.get(key).push(pawn);
    });

    return grid;
};

export const refreshPawnGridEntry = (grid, pawn, oldX, oldZ) => {
    const oldKey = cellKey(oldX, oldZ);
    const nextKey = cellKey(pawn.position.x, pawn.position.z);
    if (oldKey === nextKey) {
        return grid;
    }
    const bucket = grid.get(oldKey);
    if (bucket) {
        const index = bucket.indexOf(pawn);
        if (index >= 0) {
            bucket.splice(index, 1);
        }
        if (bucket.length === 0) {
            grid.delete(oldKey);
        }
    }
    if (! grid.has(nextKey)) {
        grid.set(nextKey, []);
    }
    grid.get(nextKey).push(pawn);

    return grid;
};

const planePoint = (raycaster, pointer, camera, target) => {
    raycaster.setFromCamera(pointer, camera);
    const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), -PICK_PLANE_Y);

    return raycaster.ray.intersectPlane(plane, target);
};

export const pickTeamAnalytic = (point, teamTargets, padding = 0.6) => {
    if (! point) {
        return null;
    }

    for (const { team } of teamTargets) {
        const halfWidth = (team.width ?? 4.2) / 2 + padding;
        const halfDepth = (team.depth ?? 4.2) / 2 + padding;
        if (Math.abs(point.x - team.x) <= halfWidth && Math.abs(point.z - team.z) <= halfDepth) {
            return team.id;
        }
    }

    return null;
};

export const pickPawnAnalytic = ({ pointer, camera, pawns, pawnGrid, teamTargets, byTeam }) => {
    const raycaster = new THREE.Raycaster();
    const target = new THREE.Vector3();
    const point = planePoint(raycaster, pointer, camera, target);
    if (! point) {
        return null;
    }

    const teamId = teamTargets ? pickTeamAnalytic(point, teamTargets) : null;
    let candidates = [];
    if (teamId !== null && byTeam?.has(teamId)) {
        candidates = byTeam.get(teamId);
    } else {
        const gx = Math.floor(point.x / CELL_SIZE);
        const gz = Math.floor(point.z / CELL_SIZE);
        for (let dx = -1; dx <= 1; dx += 1) {
            for (let dz = -1; dz <= 1; dz += 1) {
                const bucket = pawnGrid?.get(`${gx + dx},${gz + dz}`);
                if (bucket) {
                    candidates.push(...bucket);
                }
            }
        }
        if (candidates.length === 0) {
            candidates = [...pawns.values()];
        }
    }

    let best = null;
    let bestDistance = PICK_RADIUS * PICK_RADIUS;
    candidates.forEach((pawn) => {
        const dx = pawn.position.x - point.x;
        const dz = pawn.position.z - point.z;
        const distance = dx * dx + dz * dz;
        if (distance <= bestDistance) {
            bestDistance = distance;
            best = pawn;
        }
    });

    return best;
};

export const buildTeamPawnIndex = (pawns) => {
    const byTeam = new Map();
    pawns.forEach((pawn) => {
        const teamId = pawn.userData.teamId;
        if (teamId === null || teamId === undefined) {
            return;
        }
        if (! byTeam.has(teamId)) {
            byTeam.set(teamId, []);
        }
        byTeam.get(teamId).push(pawn);
    });

    return byTeam;
};
