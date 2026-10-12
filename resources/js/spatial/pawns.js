import * as THREE from 'three';

const ROLE_COLORS = [0x1d2219, 0xf4a65c, 0x9ccc4e, 0xf3e35f, 0x5f9a2a, 0xc9e58f, 0xd3d6cd];
const FALLBACK_ROLE_COLOR = 0xd3d6cd;
const MAX_VISIBLE_PAWNS_PER_TEAM = 12;
const PAWN_SPACING = 1.1;

const getRoleNames = (user) => [
    ...(user.globalRoles ?? []),
    ...(user.roles ?? []).map((role) => role.name),
].filter(Boolean);

export const createRoleSystem = (workspace, roleMeta = {}) => {
    const roleNames = [...new Set((workspace.users ?? []).flatMap(getRoleNames))];
    const roleColors = new Map(roleNames.map((role, index) => [role, ROLE_COLORS[index] ?? FALLBACK_ROLE_COLOR]));
    const globalRoleOrder = roleMeta.globalRoleOrder ?? [];
    const highestGlobalRole = globalRoleOrder[0] ?? null;

    return {
        roleNames,
        colorForUser: (user) => {
            const role = getRoleNames(user)[0];

            return roleColors.get(role) ?? FALLBACK_ROLE_COLOR;
        },
        rolesForUser: getRoleNames,
        hasCrown: (user) => highestGlobalRole !== null && (user.globalRoles ?? []).includes(highestGlobalRole),
        hasManagerFlag: (user, team = null) => (user.globalRoles ?? []).some((role) => role !== highestGlobalRole)
            || team?.supervisorId === user.id
            || team?.coordinatorId === user.id,
        colorForRole: (role) => roleColors.get(role) ?? FALLBACK_ROLE_COLOR,
    };
};

const addEyes = (head) => {
    const eyeMaterial = new THREE.MeshBasicMaterial({ color: 0x1d2219 });
    const eyeGeometry = new THREE.SphereGeometry(0.028, 8, 6);
    [-0.07, 0.07].forEach((x) => {
        const eye = new THREE.Mesh(eyeGeometry, eyeMaterial);
        eye.position.set(x, 0.02, 0.225);
        head.add(eye);
    });
};

const addCrown = (pawn) => {
    const crownMaterial = new THREE.MeshStandardMaterial({ color: 0xf3e35f, roughness: 0.65 });
    const crown = new THREE.Mesh(new THREE.ConeGeometry(0.14, 0.12, 5), crownMaterial);
    crown.position.y = 1.28;
    crown.rotation.y = Math.PI / 5;
    pawn.add(crown);
};

const addFlag = (pawn) => {
    const poleMaterial = new THREE.MeshStandardMaterial({ color: 0xf4a65c, roughness: 0.8 });
    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, 0.28, 6), poleMaterial);
    pole.position.set(0.25, 1.02, 0);
    pawn.add(pole);

    const flag = new THREE.Mesh(new THREE.PlaneGeometry(0.16, 0.1), poleMaterial);
    flag.position.set(0.32, 1.11, 0);
    flag.rotation.y = Math.PI / 2;
    pawn.add(flag);
};

const addBadge = (pawn, color, x) => {
    const badge = new THREE.Mesh(
        new THREE.SphereGeometry(0.07, 8, 6),
        new THREE.MeshStandardMaterial({ color, roughness: 0.7 }),
    );
    badge.position.set(x, 0.62, 0.22);
    pawn.add(badge);
};

export const createPawn = (user, roleSystem, team = null) => {
    const pawn = new THREE.Group();
    pawn.name = `spatial-pawn-${user.id}`;
    pawn.userData = { type: 'user', userId: user.id };

    const selectionRing = new THREE.Mesh(
        new THREE.TorusGeometry(0.38, 0.045, 8, 24),
        new THREE.MeshBasicMaterial({ color: 0x6b9f32, transparent: true, opacity: 0.95 }),
    );
    selectionRing.rotation.x = -Math.PI / 2;
    selectionRing.position.y = -0.68;
    selectionRing.visible = false;
    selectionRing.name = 'selection-ring';
    pawn.userData.selectionRing = selectionRing;
    pawn.add(selectionRing);

    const bodyMaterial = new THREE.MeshStandardMaterial({
        color: roleSystem.colorForUser(user),
        roughness: 0.85,
        metalness: 0,
    });
    const body = new THREE.Mesh(new THREE.CapsuleGeometry(0.23, 0.42, 6, 12), bodyMaterial);
    body.position.y = 0.52;
    body.castShadow = true;
    body.receiveShadow = true;
    pawn.add(body);

    const head = new THREE.Group();
    head.position.y = 1.02;
    const headMesh = new THREE.Mesh(
        new THREE.SphereGeometry(0.24, 16, 12),
        new THREE.MeshStandardMaterial({ color: 0xfff1d6, roughness: 0.88 }),
    );
    headMesh.castShadow = true;
    head.add(headMesh);
    addEyes(head);
    pawn.add(head);

    if (roleSystem.hasCrown(user)) {
        addCrown(pawn);
    } else if (roleSystem.hasManagerFlag(user, team)) {
        addFlag(pawn);
    }

    if (team?.supervisorId === user.id) {
        addBadge(pawn, 0xf3e35f, -0.18);
    } else if (team?.coordinatorId === user.id) {
        addBadge(pawn, 0xf4a65c, -0.18);
    }

    pawn.scale.setScalar(1);

    return pawn;
};

const assignUserPositions = (layout, workspace) => {
    const userMap = new Map((workspace.users ?? []).map((user) => [user.id, user]));
    const positions = new Map();

    const overflowByTeam = new Map();

    layout.branches.forEach((branch) => branch.teams.forEach((team) => {
        const ids = team.userIds ?? [];
        const visibleIds = ids.slice(0, MAX_VISIBLE_PAWNS_PER_TEAM);
        overflowByTeam.set(team.id, Math.max(0, ids.length - visibleIds.length));
        const columns = Math.max(1, Math.min(4, Math.ceil(Math.sqrt(visibleIds.length))));
        const spacing = visibleIds.length > 1 ? PAWN_SPACING : 0;
        const rows = Math.max(1, Math.ceil(visibleIds.length / columns));
        const width = (columns - 1) * spacing;
        const depth = (rows - 1) * spacing;

        visibleIds.forEach((userId, index) => {
            if (! userMap.has(userId)) {
                return;
            }

            positions.set(userId, {
                x: team.x - width / 2 + (index % columns) * spacing,
                y: 0.74,
                z: team.z - depth / 2 + Math.floor(index / columns) * spacing,
                teamId: team.id,
            });
        });
    }));

    const unassigned = (workspace.users ?? []).filter((user) => ! positions.has(user.id));
    unassigned.forEach((user, index) => {
        const columns = Math.max(1, Math.ceil(Math.sqrt(unassigned.length)));
        const spacing = Math.min(PAWN_SPACING, 2.2 / (columns - 1 || 1));
        positions.set(user.id, {
            x: -layout.island.width / 2 + 1.5 + (index % columns) * spacing,
            y: 0.24,
            z: layout.island.depth / 2 - 1.5 - Math.floor(index / columns) * spacing,
            teamId: null,
        });
    });

    return { positions, overflowByTeam };
};

export const createPawns = (scene, layout, workspace, roleSystem) => {
    const { positions, overflowByTeam } = assignUserPositions(layout, workspace);
    const teamMap = new Map(layout.branches.flatMap((branch) => branch.teams.map((team) => [team.id, team])));
    const pawns = new Map();

    (workspace.users ?? []).forEach((user) => {
        const position = positions.get(user.id);
        if (! position) {
            return;
        }

        const pawn = createPawn(user, roleSystem, teamMap.get(position.teamId) ?? null);
        pawn.position.set(position.x, position.y, position.z);
        pawn.userData.teamId = position.teamId;
        scene.add(pawn);
        pawns.set(user.id, pawn);
    });

    return { pawns, positions, overflowByTeam };
};

export const facePawnsToCamera = (pawns, camera) => {
    pawns.forEach((pawn) => {
        pawn.rotation.y = Math.atan2(camera.position.x - pawn.position.x, camera.position.z - pawn.position.z);
    });
};

export const setPawnState = (pawn, state, active) => {
    if (! pawn) {
        return;
    }

    if (state === 'selected') {
        pawn.userData.selectionRing.visible = active;
        pawn.userData.selectionRing.material.opacity = active ? 0.95 : 0;
    }

    if (state === 'hovered') {
        pawn.userData.hovered = active;
        pawn.scale.setScalar(active ? 1.08 : 1);
    }
};
