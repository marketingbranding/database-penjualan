import { mountSpatialScene } from './spatial/scene.js';

const isDev = import.meta.env.DEV;

const mockUnassignedUsers = (workspace) => {
    const assignedIds = new Set((workspace.branches ?? []).flatMap((branch) => (
        branch.teams ?? []
    ).flatMap((team) => team.userIds ?? [])));

    return (workspace.users ?? []).filter((user) => ! assignedIds.has(user.id));
};

const renderMockUnassignedCard = (root, workspace) => {
    if (! isDev) {
        return;
    }

    const users = mockUnassignedUsers(workspace);
    let card = root.querySelector('[data-spatial-unassigned-card]');
    if (! card) {
        card = document.createElement('div');
        card.className = 'spatial-unassigned-card spatial-hud absolute bottom-[18px] left-5 z-10 w-full max-w-[330px] rounded-[18px] bg-surface/90 p-3.5 backdrop-blur-sm';
        card.dataset.spatialUnassignedCard = 'true';
        card.innerHTML = '<div class="flex items-center justify-between gap-3"><p class="text-xs font-semibold text-ink">Belum ditugaskan</p><span data-spatial-unassigned-count class="rounded-full bg-lime-soft px-2 py-1 text-[10px] font-semibold text-lime-dark"></span></div><div data-spatial-unassigned-list class="spatial-unassigned-list mt-2.5"></div>';
        root.querySelector('.spatial-scene-stage')?.append(card);
    }

    card.querySelector('[data-spatial-unassigned-count]')?.replaceChildren(document.createTextNode(`${users.length} user`));
    const list = card.querySelector('[data-spatial-unassigned-list]');
    if (list) {
        list.replaceChildren(...users.slice(0, 3).map((user) => {
            const person = document.createElement('div');
            person.className = 'spatial-unassigned-person';
            person.innerHTML = '<span class="spatial-unassigned-avatar"></span><span class="truncate"></span>';
            person.querySelector('.spatial-unassigned-avatar').textContent = user.name.slice(0, 1).toUpperCase();
            person.querySelector('.truncate').textContent = user.name;
            return person;
        }));
        if (users.length > 3) {
            const more = document.createElement('span');
            more.className = 'spatial-unassigned-more';
            more.textContent = `+${users.length - 3}`;
            list.append(more);
        }
    }
};

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

const initUnassignedCard = (root) => {
    const card = root.querySelector('[data-spatial-unassigned-card]');
    if (! card) {
        return;
    }
    const toggle = card.querySelector('[data-spatial-unassigned-toggle]');
    const body = card.querySelector('[data-spatial-unassigned-body]');
    const preview = card.querySelector('[data-spatial-unassigned-preview]');
    const search = card.querySelector('[data-spatial-unassigned-search]');
    const roleBar = card.querySelector('[data-spatial-unassigned-roles]');
    const list = card.querySelector('[data-spatial-unassigned-list]');
    let activeRole = '';
    const applyFilter = () => {
        const query = (search?.value ?? '').trim().toLowerCase();
        list?.querySelectorAll('[data-user-id]').forEach((item) => {
            const matchesQuery = ! query || (item.dataset.userName ?? '').includes(query);
            const roles = (item.dataset.userRoles ?? '').split(',').filter(Boolean);
            const matchesRole = ! activeRole || roles.includes(activeRole);
            item.style.display = matchesQuery && matchesRole ? '' : 'none';
        });
    };
    toggle?.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        body?.classList.toggle('hidden', expanded);
        preview?.classList.toggle('hidden', ! expanded);
    });
    search?.addEventListener('input', applyFilter);
    roleBar?.querySelectorAll('[data-role-filter]').forEach((chip) => {
        chip.addEventListener('click', () => {
            activeRole = chip.dataset.roleFilter ?? '';
            roleBar.querySelectorAll('[data-role-filter]').forEach((other) => {
                const active = other === chip;
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            applyFilter();
        });
    });
    list?.querySelectorAll('[data-spatial-unassigned-focus]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('[data-user-id]');
            const userId = Number(item?.dataset.userId);
            if (Number.isFinite(userId)) {
                root.dispatchEvent(new CustomEvent('spatial:focus-user', { detail: { userId } }));
                root.dispatchEvent(new CustomEvent('spatial:select-user', { detail: { userId } }));
            }
        });
    });
    list?.querySelectorAll('[data-spatial-unassigned-place]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('[data-user-id]');
            const userId = Number(item?.dataset.userId);
            if (Number.isFinite(userId)) {
                root.dispatchEvent(new CustomEvent('spatial:select-user', { detail: { userId } }));
                root.dispatchEvent(new CustomEvent('spatial:focus-user', { detail: { userId } }));
            }
        });
    });
};

const bootSpatialWorkspace = async () => {
    const root = document.querySelector('[data-spatial-workspace]');

    if (! root || root.dataset.spatialInitialized === 'true') {
        return;
    }

    root.dataset.spatialInitialized = 'true';
    initUnassignedCard(root);

    let workspace = window.spatialWorkspace;
    let performanceController = null;
    const params = new URLSearchParams(window.location.search);

    if (isDev && params.get('mock')) {
        const { createMockWorkspace, parseMockWorkspaceQuery } = await import('./spatial/mock-workspace.js');
        const mockConfig = parseMockWorkspaceQuery();
        if (mockConfig) {
            workspace = createMockWorkspace(mockConfig);
            root.dataset.spatialMock = 'true';
            window.__spatialMockWorkspace = workspace;
            renderMockUnassignedCard(root, workspace);
        }
    }

    if (isDev && params.get('perf') === '1') {
        const { createSpatialPerformance } = await import('./spatial/performance.js');
        performanceController = createSpatialPerformance(root);
    }

    mountSpatialScene(root, workspace, {
        globalRoleOrder: window.spatialWorkspaceGlobalRoleOrder,
        teamRoleOrder: window.spatialWorkspaceTeamRoleOrder,
    })
        .then((scene) => {
            root.__spatialPerformance = performanceController;
            return scene;
        })
        .catch((error) => {
            console.error('Spatial workspace scene render failed.', error);
            renderSceneFallback(root, workspace);
            root.dispatchEvent(new CustomEvent('spatial:scene-error', { detail: error }));
        });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSpatialWorkspace, { once: true });
} else {
    bootSpatialWorkspace();
}
