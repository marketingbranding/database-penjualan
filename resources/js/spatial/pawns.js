import * as THREE from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';
import { layoutPawnPositions } from './layout.js';
import { getSceneTheme } from './themes.js';

const FACING_STEP = THREE.MathUtils.degToRad(1);
const UP = new THREE.Vector3(0, 1, 0);
const WHITE = new THREE.Color(0xfff1d6);
const EYE = new THREE.Color(0x1d2219);

const getRoleNames = (user) => [
    ...(user.globalRoles ?? []),
    ...(user.roles ?? []).map((role) => role.name),
].filter(Boolean);

export const createRoleSystem = (workspace, roleMeta = {}, themeName = 'light') => {
    const roleNames = [...new Set((workspace.users ?? []).flatMap(getRoleNames))];
    let roleColors = new Map();
    let activeThemeName = themeName;
    const globalRoleOrder = roleMeta.globalRoleOrder ?? [];
    const highestGlobalRole = globalRoleOrder[0] ?? null;
    const setTheme = (nextThemeName) => {
        const theme = getSceneTheme(nextThemeName);
        activeThemeName = nextThemeName;
        const fallback = theme.roleColors.at(-1);
        roleColors = new Map(roleNames.map((role, index) => [role, theme.roleColors[index] ?? fallback]));
    };

    setTheme(themeName);

    return {
        roleNames,
        colorForUser: (user) => {
            const role = getRoleNames(user)[0];

            return roleColors.get(role) ?? getSceneTheme(activeThemeName).roleColors.at(-1);
        },
        rolesForUser: getRoleNames,
        hasCrown: (user) => highestGlobalRole !== null && (user.globalRoles ?? []).includes(highestGlobalRole),
        hasManagerFlag: (user, team = null) => (user.globalRoles ?? []).some((role) => role !== highestGlobalRole)
            || team?.supervisorId === user.id
            || team?.coordinatorId === user.id,
        colorForRole: (role) => roleColors.get(role) ?? getSceneTheme(activeThemeName).roleColors.at(-1),
        getThemeName: () => activeThemeName,
        setTheme,
    };
};

const createHeadGeometry = () => {
    const head = new THREE.SphereGeometry(0.24, 10, 8);
    const leftEye = new THREE.SphereGeometry(0.028, 6, 4);
    const rightEye = leftEye.clone();
    leftEye.translate(-0.07, 0.02, 0.225);
    rightEye.translate(0.07, 0.02, 0.225);
    const geometries = [head, leftEye, rightEye];
    const merged = mergeGeometries(geometries, false);
    const colors = [];

    geometries.forEach((geometry, index) => {
        const color = index === 0 ? WHITE : EYE;
        for (let vertex = 0; vertex < geometry.attributes.position.count; vertex += 1) {
            colors.push(color.r, color.g, color.b);
        }
        geometry.dispose();
    });

    merged.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));
    merged.computeBoundingSphere();

    return merged;
};

const createFlagGeometry = () => {
    const pole = new THREE.CylinderGeometry(0.018, 0.018, 0.28, 6);
    const flag = new THREE.PlaneGeometry(0.16, 0.1);
    const flagTransform = new THREE.Matrix4()
        .makeRotationY(Math.PI / 2)
        .setPosition(0.32, 1.11, 0);
    pole.translate(0.25, 1.02, 0);
    flag.applyMatrix4(flagTransform);
    const geometry = mergeGeometries([pole, flag], false);
    pole.dispose();
    flag.dispose();

    return geometry;
};

const createInstanceMesh = (geometry, material, count, pawnIds) => {
    const mesh = new THREE.InstancedMesh(geometry, material, count);
    mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
    mesh.castShadow = true;
    mesh.receiveShadow = true;
    mesh.userData.pawnIds = pawnIds;

    return mesh;
};

const assignUserPositions = (layout, workspace) => layoutPawnPositions(layout, workspace);

const createAccessoryMeshes = (pawns, users, teamMap, roleSystem) => {
    const materials = {
        crown: new THREE.MeshLambertMaterial({ color: 0xf3e35f }),
        flag: new THREE.MeshLambertMaterial({ color: 0xf4a65c, side: THREE.DoubleSide }),
        supervisor: new THREE.MeshLambertMaterial({ color: 0xf3e35f }),
        coordinator: new THREE.MeshLambertMaterial({ color: 0xf4a65c }),
    };
    const meshes = {
        crown: createInstanceMesh(new THREE.ConeGeometry(0.14, 0.12, 5), materials.crown, users.length, []),
        flag: createInstanceMesh(createFlagGeometry(), materials.flag, users.length, []),
        supervisor: createInstanceMesh(new THREE.SphereGeometry(0.07, 6, 4), materials.supervisor, users.length, []),
        coordinator: createInstanceMesh(new THREE.SphereGeometry(0.07, 6, 4), materials.coordinator, users.length, []),
    };

    Object.values(meshes).forEach((mesh) => {
        mesh.visible = false;
        for (let index = 0; index < users.length; index += 1) {
            setInstanceMatrix(mesh, index, new THREE.Vector3(), 0, 0);
        }
        mesh.instanceMatrix.needsUpdate = true;
    });

    users.forEach((user, index) => {
        const pawn = pawns.get(user.id);
        const team = teamMap.get(pawn.userData.teamId) ?? null;
        const accessory = {};

        if (roleSystem.hasCrown(user)) {
            accessory.crown = index;
            meshes.crown.userData.pawnIds[index] = user.id;
            meshes.crown.visible = true;
        } else if (roleSystem.hasManagerFlag(user, team)) {
            accessory.flag = index;
            meshes.flag.userData.pawnIds[index] = user.id;
            meshes.flag.visible = true;
        }

        if (team?.supervisorId === user.id) {
            accessory.supervisor = index;
            meshes.supervisor.userData.pawnIds[index] = user.id;
            meshes.supervisor.visible = true;
        } else if (team?.coordinatorId === user.id) {
            accessory.coordinator = index;
            meshes.coordinator.userData.pawnIds[index] = user.id;
            meshes.coordinator.visible = true;
        }

        pawn.userData.accessory = accessory;
    });

    return meshes;
};

const setInstanceMatrix = (mesh, index, position, rotation, scale = 1) => {
    if (index === undefined || index === null) {
        return;
    }

    const matrix = new THREE.Matrix4();
    const quaternion = new THREE.Quaternion().setFromAxisAngle(UP, rotation);
    matrix.compose(position, quaternion, new THREE.Vector3(scale, scale, scale));
    mesh.setMatrixAt(index, matrix);
};

export const createPawns = (scene, layout, workspace, roleSystem) => {
    const { positions, overflowByTeam, unassignedOverflow, unassignedUserIds } = assignUserPositions(layout, workspace);
    const teamMap = new Map(layout.branches.flatMap((branch) => branch.teams.map((team) => [team.id, team])));
    const pawns = new Map();
    const users = (workspace.users ?? []).filter((user) => positions.has(user.id));
    const pawnIds = users.map((user) => user.id);
    const bodyGeometry = new THREE.CapsuleGeometry(0.23, 0.42, 4, 8);
    const bodyMaterial = new THREE.MeshLambertMaterial({ vertexColors: true });
    const headGeometry = createHeadGeometry();
    const headMaterial = new THREE.MeshLambertMaterial({ vertexColors: true });
    const bodyMesh = createInstanceMesh(bodyGeometry, bodyMaterial, users.length, pawnIds);
    const headMesh = createInstanceMesh(headGeometry, headMaterial, users.length, pawnIds);
    const selectionRing = new THREE.Mesh(
        new THREE.TorusGeometry(0.38, 0.045, 8, 20),
        new THREE.MeshBasicMaterial({ color: getSceneTheme(roleSystem.getThemeName()).selection, transparent: true, opacity: 0.95 }),
    );
    selectionRing.rotation.x = -Math.PI / 2;
    selectionRing.visible = false;
    selectionRing.userData.ownerId = null;

    scene.add(bodyMesh, headMesh, selectionRing);

    users.forEach((user, index) => {
        const position = positions.get(user.id);
        const pawn = new THREE.Object3D();
        pawn.name = `spatial-pawn-${user.id}`;
        pawn.position.set(position.x, position.y, position.z);
        pawn.userData = {
            type: 'user',
            userId: user.id,
            teamId: position.teamId,
            instanceIndex: index,
            hovered: false,
            selectionRing,
        };
        scene.add(pawn);
        pawns.set(user.id, pawn);
    });

    const instanceMeshes = {
        body: bodyMesh,
        head: headMesh,
        ...createAccessoryMeshes(pawns, users, teamMap, roleSystem),
    };
    pawns.instanceMeshes = instanceMeshes;
    pawns.instanceState = { lastCameraAzimuth: null };

    const syncPawn = (pawn) => {
        const index = pawn.userData.instanceIndex;
        const scale = pawn.userData.hovered ? 1.08 : 1;
        const position = pawn.position;
        const rotation = pawn.rotation.y;
        setInstanceMatrix(bodyMesh, index, new THREE.Vector3(position.x, position.y + 0.52, position.z), rotation, scale);
        setInstanceMatrix(headMesh, index, new THREE.Vector3(position.x, position.y + 1.02, position.z), rotation, scale);

        const accessory = pawn.userData.accessory ?? {};
        setInstanceMatrix(instanceMeshes.crown, accessory.crown, new THREE.Vector3(position.x, position.y, position.z), rotation, scale);
        setInstanceMatrix(instanceMeshes.flag, accessory.flag, new THREE.Vector3(position.x, position.y, position.z), rotation, scale);
        setInstanceMatrix(instanceMeshes.supervisor, accessory.supervisor, new THREE.Vector3(position.x, position.y, position.z), rotation, scale);
        setInstanceMatrix(instanceMeshes.coordinator, accessory.coordinator, new THREE.Vector3(position.x, position.y, position.z), rotation, scale);

        if (selectionRing.userData.ownerId === pawn.userData.userId) {
            selectionRing.position.copy(position);
        }
    };

    users.forEach((user, index) => {
        const pawn = pawns.get(user.id);
        pawn.userData.sync = () => syncPawn(pawn);
        bodyMesh.setColorAt(index, new THREE.Color(roleSystem.colorForUser(user)));
        syncPawn(pawn);
    });

    [bodyMesh, headMesh, ...Object.values(instanceMeshes).filter((mesh) => mesh !== bodyMesh && mesh !== headMesh)].forEach((mesh) => {
        mesh.instanceMatrix.needsUpdate = true;
        mesh.computeBoundingSphere();
    });
    bodyMesh.instanceColor.needsUpdate = true;

    const getPawnFromIntersection = (intersection) => {
        const userId = intersection?.object?.userData?.pawnIds?.[intersection.instanceId];

        return userId === undefined ? null : pawns.get(userId) ?? null;
    };

    return {
        pawns,
        overflowByTeam,
        unassignedOverflow,
        unassignedUserIds,
        pickingMeshes: [bodyMesh, headMesh],
        getPawnFromIntersection,
        syncPawn,
    };
};

export const applyPawnTheme = (pawns, workspace, roleSystem, themeName) => {
    const theme = getSceneTheme(themeName);
    const userMap = new Map((workspace.users ?? []).map((user) => [user.id, user]));
    const bodyMesh = pawns.instanceMeshes?.body;

    pawns.forEach((pawn, userId) => {
        const user = userMap.get(userId);
        if (bodyMesh && user) {
            bodyMesh.setColorAt(pawn.userData.instanceIndex, new THREE.Color(roleSystem.colorForUser(user)));
        }
        pawn.userData.selectionRing?.material.color.setHex(theme.selection);
    });

    if (bodyMesh?.instanceColor) {
        bodyMesh.instanceColor.needsUpdate = true;
    }
};

export const facePawnsToCamera = (pawns, camera) => {
    const azimuth = Math.atan2(camera.position.x, camera.position.z);
    const previous = pawns.instanceState?.lastCameraAzimuth;
    let delta = previous === null || previous === undefined ? Infinity : Math.abs(Math.atan2(Math.sin(azimuth - previous), Math.cos(azimuth - previous)));

    if (delta < FACING_STEP) {
        return;
    }

    pawns.instanceState.lastCameraAzimuth = azimuth;
    pawns.forEach((pawn) => {
        pawn.rotation.y = Math.atan2(camera.position.x - pawn.position.x, camera.position.z - pawn.position.z);
        pawn.userData.sync?.();
    });
    Object.values(pawns.instanceMeshes ?? {}).forEach((mesh) => {
        mesh.instanceMatrix.needsUpdate = true;
    });
};

export const setPawnState = (pawn, state, active) => {
    if (! pawn) {
        return;
    }

    if (state === 'selected') {
        const ring = pawn.userData.selectionRing;
        if (active) {
            ring.userData.ownerId = pawn.userData.userId;
            ring.position.copy(pawn.position);
            ring.visible = true;
        } else if (ring.userData.ownerId === pawn.userData.userId) {
            ring.userData.ownerId = null;
            ring.visible = false;
        }
    }

    if (state === 'hovered') {
        pawn.userData.hovered = active;
        pawn.userData.sync?.();
    }
};
