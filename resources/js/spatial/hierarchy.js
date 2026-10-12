import * as THREE from 'three';

const LINK_COLOR = 0x6b9f32;

export const createHierarchyLinks = (scene, workspace, pawns) => {
    const material = new THREE.LineBasicMaterial({
        color: LINK_COLOR,
        transparent: true,
        opacity: 0.52,
    });
    const links = [];
    const users = new Map((workspace.users ?? []).map((user) => [user.id, user]));

    const updateLink = (link) => {
        const child = pawns.get(link.userId);
        const parent = pawns.get(link.reportsToId);
        if (! child || ! parent) {
            link.line.visible = false;
            return;
        }

        const start = child.position.clone();
        const end = parent.position.clone();
        start.y += 0.78;
        end.y += 0.78;
        link.line.geometry.setFromPoints([start, end]);
        link.line.geometry.computeBoundingSphere();
        link.line.visible = true;
    };

    users.forEach((user) => {
        if (! user.reportsToId || ! pawns.has(user.id) || ! pawns.has(user.reportsToId)) {
            return;
        }

        const line = new THREE.Line(new THREE.BufferGeometry(), material);
        line.name = `spatial-hierarchy-${user.id}`;
        line.userData = { type: 'hierarchy-link', userId: user.id, reportsToId: user.reportsToId };
        line.renderOrder = -1;
        scene.add(line);
        const link = { line, userId: user.id, reportsToId: user.reportsToId };
        links.push(link);
        updateLink(link);
    });

    return {
        links,
        update: () => links.forEach(updateLink),
        dispose: () => {
            links.forEach(({ line }) => {
                scene.remove(line);
                line.geometry.dispose();
            });
            material.dispose();
        },
    };
};
