import { mountSpatialScene } from './spatial/scene.js';

const renderSceneFallback = (root, workspace) => {
    root.querySelector('[data-spatial-loading]')?.remove();
    const stage = root.querySelector('.spatial-scene-stage');
    if (! stage || stage.querySelector('[data-spatial-fallback]')) {
        return;
    }

    const fallback = document.createElement('div');
    fallback.className = 'spatial-scene-fallback';
    fallback.dataset.spatialFallback = 'true';
    fallback.setAttribute('role', 'status');
    fallback.append(
        Object.assign(document.createElement('h3'), { textContent: 'Peta 3D tidak tersedia' }),
        Object.assign(document.createElement('p'), { textContent: 'Browser atau perangkat ini tidak menyediakan WebGL. Struktur organisasi tetap dapat dibaca melalui daftar berikut.' }),
    );
    const list = document.createElement('ul');
    (workspace?.branches ?? []).forEach((branch) => {
        const item = document.createElement('li');
        item.textContent = `${branch.name} · ${(branch.teams ?? []).map((team) => team.name).join(', ') || 'Belum ada tim'}`;
        list.append(item);
    });
    fallback.append(list);
    stage.append(fallback);
};

const bootSpatialWorkspace = () => {
    const root = document.querySelector('[data-spatial-workspace]');

    if (! root || root.dataset.spatialInitialized === 'true') {
        return;
    }

    root.dataset.spatialInitialized = 'true';

    mountSpatialScene(root, window.spatialWorkspace, {
        globalRoleOrder: window.spatialWorkspaceGlobalRoleOrder,
        teamRoleOrder: window.spatialWorkspaceTeamRoleOrder,
    })
        .catch((error) => {
            console.error('Spatial workspace scene render failed.', error);
            renderSceneFallback(root, window.spatialWorkspace);
            root.dispatchEvent(new CustomEvent('spatial:scene-error', { detail: error }));
        });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSpatialWorkspace, { once: true });
} else {
    bootSpatialWorkspace();
}
