import * as THREE from 'three';
import { getSceneTheme } from './themes.js';

export const createHierarchyLinks = (scene, workspace, pawns, themeName = 'light') => {
    const material = new THREE.LineBasicMaterial({
        color: getSceneTheme(themeName).hierarchy,
        transparent: true,
        opacity: 0.52,
    });
    const users = new Map((workspace.users ?? []).map((user) => [user.id, user]));
    const links = [];
    const linksByUser = new Map();

    users.forEach((user) => {
        if (! user.reportsToId || ! pawns.has(user.id) || ! pawns.has(user.reportsToId)) {
            return;
        }
        const link = { userId: user.id, reportsToId: user.reportsToId, visible: true };
        links.push(link);
        for (const id of [user.id, user.reportsToId]) {
            if (! linksByUser.has(id)) {
                linksByUser.set(id, []);
            }
            linksByUser.get(id).push(link);
        }
    });

    const positions = new Float32Array(links.length * 6);
    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3).setUsage(THREE.DynamicDrawUsage));
    const lines = new THREE.LineSegments(geometry, material);
    lines.name = 'spatial-hierarchy';
    lines.renderOrder = -1;
    lines.frustumCulled = false;
    lines.matrixAutoUpdate = false;
    scene.add(lines);

    const writeLink = (index) => {
        const link = links[index];
        const child = pawns.get(link.userId);
        const parent = pawns.get(link.reportsToId);
        const offset = index * 6;
        if (! child || ! parent) {
            positions[offset] = 0;
            positions[offset + 1] = -10;
            positions[offset + 2] = 0;
            positions[offset + 3] = 0;
            positions[offset + 4] = -10;
            positions[offset + 5] = 0;
            link.visible = false;
            return;
        }
        positions[offset] = child.position.x;
        positions[offset + 1] = child.position.y + 0.78;
        positions[offset + 2] = child.position.z;
        positions[offset + 3] = parent.position.x;
        positions[offset + 4] = parent.position.y + 0.78;
        positions[offset + 5] = parent.position.z;
        link.visible = true;
    };

    const update = () => {
        links.forEach((link, index) => writeLink(index));
        geometry.attributes.position.needsUpdate = true;
        geometry.computeBoundingSphere();
    };

    update();

    return {
        links,
        applyTheme: (nextThemeName) => material.color.setHex(getSceneTheme(nextThemeName).hierarchy),
        update,
        updateForUser: (userId) => {
            const affected = linksByUser.get(userId) ?? [];
            affected.forEach((link) => writeLink(links.indexOf(link)));
            if (affected.length > 0) {
                geometry.attributes.position.needsUpdate = true;
                geometry.computeBoundingSphere();
            }
        },
        dispose: () => {
            scene.remove(lines);
            geometry.dispose();
            material.dispose();
        },
    };
};
