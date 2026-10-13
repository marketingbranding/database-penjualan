import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';
import { createWorkspaceLayout } from './layout.js';
import { createMaterials } from './materials.js';
import { applyPawnTheme, createPawns, createRoleSystem } from './pawns.js';
import { addWorkspaceLabels, createLabelRenderer, populateRoleLegend } from './labels.js';
import { createSpatialControls } from './controls.js';
import { createSpatialPanel } from './panel.js';
import { createSpatialSelection } from './selection.js';
import { createHierarchyLinks } from './hierarchy.js';
import { createSpatialDragDrop } from './drag-drop.js';
import { buildPawnGrid, buildTeamPawnIndex, refreshPawnGridEntry } from './picking.js';
import { layoutDecorations } from './layout.js';
import { getSceneTheme } from './themes.js';

const createPlatformGeometry = ({ width, depth, height, x, z, y = 0 }, top = false) => {
    const geometry = new RoundedBoxGeometry(
        top ? width - 0.08 : width,
        top ? 0.06 : height,
        top ? depth - 0.08 : depth,
        1,
        top ? 0.12 : Math.min(0.18, height / 2),
    );
    geometry.translate(x, top ? y + height + 0.02 : y + height / 2, z);

    return geometry;
};

const addMergedMesh = (scene, entries, material) => {
    if (entries.length === 0) {
        return null;
    }

    const geometry = mergeGeometries(entries, false);
    entries.forEach((entry) => entry.dispose());
    const mesh = new THREE.Mesh(geometry, material);
    mesh.matrixAutoUpdate = false;
    mesh.receiveShadow = true;
    mesh.castShadow = true;
    mesh.updateMatrix();
    scene.add(mesh);

    return mesh;
};

const addIsland = (scene, layout, materials) => {
    const { width, depth, height, x = 0, z = 0 } = layout.island;
    const platform = {
        width,
        depth,
        height,
        x,
        z,
        y: -height,
    };
    addMergedMesh(scene, [createPlatformGeometry(platform)], materials.islandSide);
    addMergedMesh(scene, [createPlatformGeometry(platform, true)], materials.islandTop);
};

const addRoads = (scene, layout, materials) => {
    const roadGeometries = layout.branches.map((branch) => {
        const geometry = new THREE.PlaneGeometry(branch.width + 0.5, 0.75);
        geometry.rotateX(-Math.PI / 2);
        geometry.translate(branch.x, 0.015, branch.z - branch.depth / 2 - 0.65);

        return geometry;
    });
    addMergedMesh(scene, roadGeometries, materials.road);

    const links = [];
    for (let index = 0; index < layout.branches.length - 1; index += 1) {
        const from = layout.branches[index];
        const to = layout.branches[index + 1];
        links.push(new THREE.Vector3(from.x, 0.055, from.z), new THREE.Vector3(to.x, 0.055, to.z));
    }

    if (links.length > 0) {
        const geometry = new THREE.BufferGeometry().setFromPoints(links);
        const lines = new THREE.LineSegments(geometry, materials.roadLine);
        lines.computeLineDistances();
        lines.matrixAutoUpdate = false;
        lines.updateMatrix();
        scene.add(lines);
    }
};

const addTrees = (scene, layout, materials) => {
    const locations = layoutDecorations(layout, 42);

    const trunkGeometry = new THREE.CylinderGeometry(0.08, 0.1, 0.55, 6);
    const darkGeometry = new THREE.SphereGeometry(0.36, 8, 6);
    const lightGeometry = new THREE.SphereGeometry(0.26, 8, 6);
    const trunk = new THREE.InstancedMesh(trunkGeometry, materials.treeTrunk, locations.length);
    const dark = new THREE.InstancedMesh(darkGeometry, materials.treeDark, locations.length);
    const light = new THREE.InstancedMesh(lightGeometry, materials.treeLight, locations.length);
    const matrix = new THREE.Object3D();

    locations.forEach(([x, z], index) => {
        matrix.position.set(x, 0.35, z);
        matrix.scale.setScalar(1);
        matrix.updateMatrix();
        trunk.setMatrixAt(index, matrix.matrix);
        matrix.position.set(x, 0.82, z);
        matrix.scale.setScalar(1);
        matrix.updateMatrix();
        dark.setMatrixAt(index, matrix.matrix);
        matrix.position.set(x + 0.17, 1.02, z + 0.02);
        matrix.scale.setScalar(0.9);
        matrix.updateMatrix();
        light.setMatrixAt(index, matrix.matrix);
    });

    [trunk, dark, light].forEach((mesh) => {
        mesh.matrixAutoUpdate = false;
        mesh.instanceMatrix.needsUpdate = true;
        scene.add(mesh);
    });

    return { locations, meshes: { trunk, dark, light } };
};

const createShadowTexture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 32;
    const context = canvas.getContext('2d');
    const gradient = context.createRadialGradient(32, 16, 2, 32, 16, 28);
    gradient.addColorStop(0, 'rgba(29, 34, 25, 0.3)');
    gradient.addColorStop(1, 'rgba(29, 34, 25, 0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 64, 32);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;

    return texture;
};

const addShadowDecals = (scene, treeLocations, pawns) => {
    const positions = [
        ...treeLocations.map(([x, z]) => ({ x, y: 0.026, z, scale: 1.15 })),
        ...pawns.map((pawn) => ({ x: pawn.position.x, y: pawn.position.y + 0.015, z: pawn.position.z, scale: 0.78 })),
    ];
    const geometry = new THREE.PlaneGeometry(0.9, 0.42);
    geometry.rotateX(-Math.PI / 2);
    const material = new THREE.MeshBasicMaterial({
        alphaTest: 0.01,
        depthWrite: false,
        map: createShadowTexture(),
        transparent: true,
    });
    const decals = new THREE.InstancedMesh(geometry, material, positions.length);
    const matrix = new THREE.Object3D();

    positions.forEach(({ x, y, z, scale }, index) => {
        matrix.position.set(x, y, z);
        matrix.scale.set(scale, scale, scale);
        matrix.updateMatrix();
        decals.setMatrixAt(index, matrix.matrix);
    });
    decals.matrixAutoUpdate = false;
    decals.instanceMatrix.needsUpdate = true;
    scene.add(decals);
};

const addOrganizationPlatforms = (scene, layout, materials) => {
    const teamTargets = [];
    const branchBaseGeometries = [];
    const branchTopGeometries = [];
    const teamBaseGeometries = [];
    const teamTopGeometries = [];
    const targetGeometry = new THREE.BoxGeometry(4.2, 0.25, 4.2);
    const targetMaterial = new THREE.MeshBasicMaterial({ visible: false });
    const highlightGeometry = new RoundedBoxGeometry(4.12, 0.07, 4.12, 2, 0.12);
    const highlight = new THREE.Mesh(
        highlightGeometry,
        new THREE.MeshBasicMaterial({ color: 0x6b9f32, transparent: true, opacity: 0.62 }),
    );
    highlight.matrixAutoUpdate = false;
    highlight.visible = false;
    scene.add(highlight);

    layout.branches.forEach((branch) => {
        const platform = {
            width: branch.width,
            depth: branch.depth,
            height: 0.35,
            x: branch.x,
            z: branch.z,
            y: 0.06,
        };
        branchBaseGeometries.push(createPlatformGeometry(platform));
        branchTopGeometries.push(createPlatformGeometry(platform, true));

        branch.teams.forEach((team) => {
            const teamPlatform = {
                width: team.width,
                depth: team.depth,
                height: 0.25,
                x: team.x,
                z: team.z,
                y: 0.43,
            };
            teamBaseGeometries.push(createPlatformGeometry(teamPlatform));
            teamTopGeometries.push(createPlatformGeometry(teamPlatform, true));
            const target = new THREE.Mesh(targetGeometry, targetMaterial);
            target.position.set(team.x, 0.555, team.z);
            target.matrixAutoUpdate = false;
            target.updateMatrix();
            target.userData = { type: 'team-drop-target', teamId: team.id };
            teamTargets.push({ team, meshes: [target], highlight });
        });
    });

    addMergedMesh(scene, branchBaseGeometries, materials.branchSide);
    addMergedMesh(scene, branchTopGeometries, materials.branchTop);
    addMergedMesh(scene, teamBaseGeometries, materials.teamSide);
    addMergedMesh(scene, teamTopGeometries, materials.teamTop);

    let unassignedInfo = null;
    if (layout.unassigned) {
        const plot = {
            width: layout.unassigned.width,
            depth: layout.unassigned.depth,
            height: layout.unassigned.height ?? 0.2,
            x: layout.unassigned.x,
            z: layout.unassigned.z,
            y: 0.06,
        };
        addMergedMesh(scene, [createPlatformGeometry(plot)], materials.unassignedSide);
        addMergedMesh(scene, [createPlatformGeometry(plot, true)], materials.unassignedTop);

        const borderPoints = [
            new THREE.Vector3(plot.x - plot.width / 2, 0.29, plot.z - plot.depth / 2),
            new THREE.Vector3(plot.x + plot.width / 2, 0.29, plot.z - plot.depth / 2),
            new THREE.Vector3(plot.x + plot.width / 2, 0.29, plot.z - plot.depth / 2),
            new THREE.Vector3(plot.x + plot.width / 2, 0.29, plot.z + plot.depth / 2),
            new THREE.Vector3(plot.x + plot.width / 2, 0.29, plot.z + plot.depth / 2),
            new THREE.Vector3(plot.x - plot.width / 2, 0.29, plot.z + plot.depth / 2),
            new THREE.Vector3(plot.x - plot.width / 2, 0.29, plot.z + plot.depth / 2),
            new THREE.Vector3(plot.x - plot.width / 2, 0.29, plot.z - plot.depth / 2),
        ];
        const borderGeometry = new THREE.BufferGeometry().setFromPoints(borderPoints);
        const border = new THREE.LineSegments(
            borderGeometry,
            new THREE.LineDashedMaterial({ color: 0x8a8a8a, dashSize: 0.25, gapSize: 0.18, transparent: true, opacity: 0.9 }),
        );
        border.computeLineDistances();
        border.matrixAutoUpdate = false;
        scene.add(border);

        let nearest = null;
        let nearestDistance = Infinity;
        layout.branches.forEach((branch) => {
            const distance = Math.hypot(branch.x - plot.x, branch.z - plot.z);
            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearest = branch;
            }
        });
        if (nearest) {
            const roadGeometry = new THREE.BufferGeometry().setFromPoints([
                new THREE.Vector3(plot.x + plot.width / 2, 0.03, plot.z),
                new THREE.Vector3(nearest.x, 0.03, nearest.z - nearest.depth / 2 - 0.65),
            ]);
            const roadLink = new THREE.Line(
                roadGeometry,
                new THREE.LineDashedMaterial({ color: 0xffffff, dashSize: 0.3, gapSize: 0.24, transparent: true, opacity: 0.85 }),
            );
            roadLink.computeLineDistances();
            roadLink.matrixAutoUpdate = false;
            scene.add(roadLink);
        }

        const hitMesh = new THREE.Mesh(new THREE.BoxGeometry(plot.width, 0.3, plot.depth), targetMaterial);
        hitMesh.position.set(plot.x, 0.2, plot.z);
        hitMesh.matrixAutoUpdate = false;
        hitMesh.updateMatrix();
        hitMesh.userData = { type: 'unassigned-area' };
        scene.add(hitMesh);
        unassignedInfo = { plot, hitMesh };
    }

    return { teamTargets, unassignedInfo };
};

const createRenderer = (canvas) => {
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    const targetPixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
    let restoreTimer = null;
    renderer.setPixelRatio(targetPixelRatio);
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.setClearColor(0x000000, 0);
    renderer.setInteractionQuality = (isInteracting) => {
        if (restoreTimer !== null) {
            window.clearTimeout(restoreTimer);
            restoreTimer = null;
        }

        if (isInteracting) {
            renderer.setPixelRatio(1);
            return;
        }

        restoreTimer = window.setTimeout(() => {
            restoreTimer = null;
            renderer.setPixelRatio(targetPixelRatio);
        }, 150);
    };

    return renderer;
};

const createCamera = (island, width, height) => {
    const aspect = width / Math.max(1, height);
    const fitMultiplier = aspect < 1 ? 2.9 : 1.82;
    const viewHeight = Math.max(island.width, island.depth) * fitMultiplier;
    const camera = new THREE.OrthographicCamera(
        (-viewHeight * aspect) / 2,
        (viewHeight * aspect) / 2,
        viewHeight / 2,
        -viewHeight / 2,
        0.1,
        200,
    );
    const distance = Math.max(island.width, island.depth) * 1.75;
    const azimuth = THREE.MathUtils.degToRad(45);
    const elevation = THREE.MathUtils.degToRad(30);
    camera.position.set(
        (island.x ?? 0) + Math.cos(elevation) * Math.sin(azimuth) * distance,
        Math.sin(elevation) * distance,
        (island.z ?? 0) + Math.cos(elevation) * Math.cos(azimuth) * distance,
    );
    camera.lookAt(island.x ?? 0, 0.25, island.z ?? 0);

    return camera;
};

export const mountSpatialScene = async (root, workspace, roleMeta = {}) => {
    const canvas = root.querySelector('[data-spatial-canvas]');
    const loading = root.querySelector('[data-spatial-loading]');
    const stage = root.querySelector('.spatial-scene-stage');

    if (! canvas) {
        return null;
    }

    if (! THREE.WebGLRenderer) {
        throw new Error('WebGL renderer tidak tersedia.');
    }

    const themeName = document.documentElement.dataset.theme ?? 'light';
    const theme = getSceneTheme(themeName);
    const layout = createWorkspaceLayout(workspace ?? { branches: [] });
    const scene = new THREE.Scene();
    const materials = createMaterials(themeName);
    const renderer = createRenderer(canvas);
    const camera = createCamera(layout.island, canvas.clientWidth || 900, canvas.clientHeight || 700);
    const hemisphere = new THREE.HemisphereLight(theme.lighting.sky, theme.lighting.ground, theme.lighting.hemisphere);
    const directional = new THREE.DirectionalLight(theme.lighting.sky, theme.lighting.directional);
    directional.position.set(-10, 18, 12);
    scene.add(hemisphere, directional);
    addIsland(scene, layout, materials);
    addRoads(scene, layout, materials);
    const { teamTargets, unassignedInfo } = addOrganizationPlatforms(scene, layout, materials);
    const { locations: treeLocations, meshes: treeMeshes } = addTrees(scene, layout, materials);
    const roleSystem = createRoleSystem(workspace, roleMeta, themeName);
    const {
        pawns,
        overflowByTeam,
        unassignedOverflow,
        unassignedUserIds,
        pickingMeshes,
        getPawnFromIntersection,
        syncPawn,
    } = createPawns(scene, layout, workspace, roleSystem);
    addShadowDecals(scene, treeLocations, [...pawns.values()]);
    const hierarchy = createHierarchyLinks(scene, workspace, pawns, themeName);
    const labelRenderer = createLabelRenderer(stage, scene);
    addWorkspaceLabels(labelRenderer, layout, workspace, pawns, overflowByTeam, {
        unassignedCount: unassignedUserIds?.length ?? 0,
        unassignedOverflow: unassignedOverflow ?? 0,
    });
    const pawnGrid = buildPawnGrid(pawns);
    const pawnsByTeam = buildTeamPawnIndex(pawns);
    const refreshSharedPawnGrid = (pawn, oldX, oldZ) => {
        refreshPawnGridEntry(pawnGrid, pawn, oldX, oldZ);
    };
    populateRoleLegend(root.querySelector('[data-spatial-role-legend]'), roleSystem);
    const legend = root.querySelector('[data-spatial-legend]');
    const legendToggle = root.querySelector('[data-spatial-legend-toggle]');
    const legendContent = root.querySelector('[data-spatial-legend-content]');
    const syncLegendContent = (isExpanded) => {
        if (! legendContent) {
            return;
        }

        legendContent.style.setProperty('max-height', isExpanded ? `${legendContent.scrollHeight}px` : '0px');
        legendContent.style.setProperty('margin-top', isExpanded ? '0.625rem' : '0');
        legendContent.style.setProperty('opacity', isExpanded ? '1' : '0');
        legendContent.style.setProperty('pointer-events', isExpanded ? 'auto' : 'none');
        legendContent.style.setProperty('transform', isExpanded ? 'translateY(0)' : 'translateY(-0.35rem)');
    };

    syncLegendContent(false);
    legendToggle?.addEventListener('click', () => {
        const isExpanded = legend?.classList.toggle('is-expanded') ?? false;
        legendToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        syncLegendContent(isExpanded);
    });
    const renderTimes = [];
    let powerSaving = false;
    let powerSavingAuto = false;
    let powerButton = null;
    const trackRenderForPowerSaving = () => {
        const now = performance.now();
        renderTimes.push(now);
        while (renderTimes.length > 0 && now - renderTimes[0] > 3000) {
            renderTimes.shift();
        }
        if (! powerSaving && renderTimes.length >= 30) {
            const elapsed = (now - renderTimes[0]) / 1000;
            const fps = renderTimes.length / Math.max(0.1, elapsed);
            if (fps < 30 && elapsed >= 2.9) {
                applyPowerSaving(true, 'auto');
            }
        }
        if (import.meta.env.DEV && window.__spatialPerf) {
            window.__spatialPerf.powerSaving = powerSaving || powerSavingAuto;
        }
    };
    const renderScene = () => {
        hierarchy.update();
        renderer.render(scene, camera);
        labelRenderer.update(scene, camera, pawns);
        trackRenderForPowerSaving();
        if (import.meta.env.DEV) {
            window.__spatialPerf?.onRender?.({
                camera,
                labelCount: labelRenderer.labels.length,
                pawns,
                renderer,
                scene,
            });
        }
    };
    const spatialControls = createSpatialControls({
        camera,
        canvas,
        render: renderScene,
        setInteractionQuality: renderer.setInteractionQuality,
        target: { x: layout.island.x ?? 0, z: layout.island.z ?? 0 },
    });
    const panel = createSpatialPanel({
        root,
        workspace,
        canEdit: window.spatialWorkspaceCanEdit === true,
        updateUrl: window.spatialWorkspaceUpdateUrl,
    });
    const selection = createSpatialSelection({
        canvas,
        camera,
        pawns,
        panel,
        controls: spatialControls.controls,
        focusOn: spatialControls.focusOn,
        render: renderScene,
        pickingMeshes,
        getPawnFromIntersection,
        setPawnLabelState: labelRenderer.setUserLabelState,
        teamTargets,
        sharedPawnGrid: pawnGrid,
        sharedPawnsByTeam: pawnsByTeam,
    });
    const focusUser = (event) => {
        labelRenderer.clearFocus();
        const pawn = pawns.get(event.detail.userId);
        if (pawn) {
            spatialControls.focusOn(pawn.position.clone().add(new THREE.Vector3(0, 0.72, 0)), 2.2);
        }
    };
    const focusTeam = (event) => {
        const team = layout.branches.flatMap((branch) => branch.teams).find((candidate) => candidate.id === event.detail.teamId);
        if (team) {
            labelRenderer.focusTeam(team.id);
            spatialControls.focusOn(new THREE.Vector3(team.x, 0.75, team.z), 1.65);
        }
    };
    root.addEventListener('spatial:focus-user', focusUser);
    root.addEventListener('spatial:focus-team', focusTeam);
    const selectUser = (event) => {
        const pawn = pawns.get(event.detail.userId);
        if (pawn) {
            selection.selectPawn(pawn);
        }
    };
    root.addEventListener('spatial:select-user', selectUser);
    const unassignedLabel = () => labelRenderer.labels.find((object) => object.userData.unassigned);
    const refreshUnassignedLabel = (remaining) => {
        const label = unassignedLabel();
        if (! label) {
            return;
        }
        const text = remaining === 0 ? 'Semua user sudah ditugaskan' : `Belum ditugaskan · ${remaining}`;
        label.userData.labelElement.querySelector('span:last-of-type').textContent = text;
        label.userData.labelElement.title = text;
    };
    const refreshUnassignedCard = (remaining) => {
        const count = root.querySelector('[data-spatial-unassigned-count]');
        if (count) {
            count.textContent = `${remaining} user`;
        }
    };
    const movePawnToTeam = (userId, teamId) => {
        const pawn = pawns.get(userId);
        const team = layout.branches.flatMap((branch) => branch.teams).find((candidate) => candidate.id === teamId);
        if (! pawn || ! team || pawn.userData.teamId !== null) {
            return;
        }
        const teammates = [...pawns.values()].filter((candidate) => candidate.userData.teamId === teamId);
        const columns = Math.max(1, Math.min(4, Math.ceil(Math.sqrt(teammates.length + 1))));
        const index = teammates.length;
        const spacing = 1.1;
        const rows = Math.max(1, Math.ceil((teammates.length + 1) / columns));
        const width = (columns - 1) * spacing;
        const depth = (rows - 1) * spacing;
        const oldX = pawn.position.x;
        const oldZ = pawn.position.z;
        pawn.position.set(
            team.x - width / 2 + (index % columns) * spacing,
            0.74,
            team.z - depth / 2 + Math.floor(index / columns) * spacing,
        );
        pawn.userData.teamId = teamId;
        syncPawn(pawn);
        refreshSharedPawnGrid(pawn, oldX, oldZ);
        hierarchy.updateForUser(userId);
        const remaining = [...pawns.values()].filter((candidate) => candidate.userData.teamId === null).length;
        refreshUnassignedLabel(remaining);
        refreshUnassignedCard(remaining);
        renderScene();
    };
    const originalStageAssignment = panel.stageAssignment.bind(panel);
    panel.stageAssignment = (userId, teamId) => {
        const result = originalStageAssignment(userId, teamId);
        if (result) {
            movePawnToTeam(userId, teamId);
        }

        return result;
    };
    const dragDrop = createSpatialDragDrop({
        canvas,
        camera,
        pawns,
        teamTargets,
        panel,
        controls: spatialControls.controls,
        render: renderScene,
        updateHierarchy: hierarchy.update,
        updateHierarchyForUser: hierarchy.updateForUser,
        setLabelsSuspended: labelRenderer.setSuspended,
        themeName,
        pickingMeshes,
        getPawnFromIntersection,
        syncPawn,
        refreshPawnGrid: refreshSharedPawnGrid,
        sharedPawnGrid: pawnGrid,
        sharedPawnsByTeam: pawnsByTeam,
        unassignedPlot: layout.unassigned ?? null,
        setInteractionQuality: renderer.setInteractionQuality,
    });

    const applyTheme = (nextThemeName) => {
        const nextTheme = getSceneTheme(nextThemeName);
        materials.applyTheme(nextThemeName);
        hemisphere.color.setHex(nextTheme.lighting.sky);
        hemisphere.groundColor.setHex(nextTheme.lighting.ground);
        hemisphere.intensity = nextTheme.lighting.hemisphere;
        directional.color.setHex(nextTheme.lighting.sky);
        directional.intensity = nextTheme.lighting.directional;
        roleSystem.setTheme(nextThemeName);
        applyPawnTheme(pawns, workspace, roleSystem, nextThemeName);
        hierarchy.applyTheme(nextThemeName);
        dragDrop.applyTheme(nextThemeName);
        labelRenderer.applyTheme(nextThemeName);
        populateRoleLegend(root.querySelector('[data-spatial-role-legend]'), roleSystem);
        root.dataset.spatialTheme = nextThemeName;
        renderScene();
    };
    const onThemeChange = (event) => applyTheme(event.detail?.theme ?? document.documentElement.dataset.theme ?? 'light');
    window.addEventListener('themechange', onThemeChange);
    root.dataset.spatialTheme = themeName;

    let resizeFrame = null;
    let renderedWidth = 0;
    let renderedHeight = 0;
    const resize = () => {
        if (resizeFrame !== null) {
            return;
        }

        resizeFrame = requestAnimationFrame(() => {
            resizeFrame = null;
            const width = Math.max(1, canvas.clientWidth);
            const height = Math.max(1, canvas.clientHeight);
            if (width === renderedWidth && height === renderedHeight) {
                return;
            }
            renderedWidth = width;
            renderedHeight = height;
            const aspect = width / height;
            const aspectFitMultiplier = aspect < 1 ? 2.9 : 1.82;
            const viewHeight = Math.max(layout.island.width, layout.island.depth) * aspectFitMultiplier;
            camera.left = (-viewHeight * aspect) / 2;
            camera.right = (viewHeight * aspect) / 2;
            camera.top = viewHeight / 2;
            camera.bottom = -viewHeight / 2;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height, false);
            labelRenderer.setSize(width, height);
            renderScene();
        });
    };

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(canvas);
    resize();
    renderScene();

    const powerSavingKey = 'spatial-power-saving';
    const storedPowerSaving = (() => {
        try {
            return window.localStorage.getItem(powerSavingKey);
        } catch {
            return null;
        }
    })();
    const applyPowerSaving = (enabled, source = 'manual') => {
        powerSaving = enabled;
        if (source === 'auto') {
            powerSavingAuto = enabled;
        }
        renderer.setPixelRatio(enabled ? 1 : Math.min(window.devicePixelRatio || 1, 1.5));
        labelRenderer.setPowerSaving(enabled);
        if (treeMeshes.light) {
            treeMeshes.light.visible = ! enabled;
        }
        powerButton?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        powerButton?.classList.toggle('is-active', enabled);
        try {
            window.localStorage.setItem(powerSavingKey, enabled ? '1' : '0');
        } catch {
            // abaikan kegagalan penyimpanan lokal
        }
        renderScene();
    };
    powerButton = document.createElement('button');
    powerButton.type = 'button';
    powerButton.className = 'spatial-power-button spatial-mode-button';
    powerButton.textContent = 'Mode hemat';
    powerButton.setAttribute('aria-pressed', powerSaving ? 'true' : 'false');
    powerButton.addEventListener('click', () => applyPowerSaving(! powerSaving, 'manual'));
    root.querySelector('[data-spatial-toolbar]')?.append(powerButton);
    if (storedPowerSaving === '1') {
        applyPowerSaving(true, 'manual');
    } else if (storedPowerSaving !== '0') {
        const weakDevice = (navigator.hardwareConcurrency ?? 8) <= 4 || (navigator.deviceMemory ?? 8) <= 4;
        if (weakDevice) {
            applyPowerSaving(true, 'auto');
        }
    }

    if (loading) {
        loading.remove();
    }

    const sceneReadyDetail = { scene, camera, renderer, layout, pawns };
    if (import.meta.env.DEV) {
        Object.assign(sceneReadyDetail, {
            canvas,
            controls: spatialControls.controls,
            render: renderScene,
            teamTargets,
        });
    }
    root.dispatchEvent(new CustomEvent('spatial:scene-ready', { detail: sceneReadyDetail }));

    return {
        scene,
        camera,
        renderer,
        layout,
        controls: spatialControls.controls,
        dispose: () => {
            resizeObserver.disconnect();
            if (resizeFrame !== null) {
                cancelAnimationFrame(resizeFrame);
            }
            selection.dispose();
            dragDrop.dispose();
            panel.dispose();
            spatialControls.dispose();
            hierarchy.dispose();
            window.removeEventListener('themechange', onThemeChange);
            root.removeEventListener('spatial:focus-user', focusUser);
            root.removeEventListener('spatial:focus-team', focusTeam);
            root.removeEventListener('spatial:select-user', selectUser);
            powerButton?.remove();
            labelRenderer.dispose();
            renderer.dispose();
            scene.traverse((object) => {
                if (object.geometry) {
                    object.geometry.dispose();
                }
                if (object.material) {
                    const objectMaterials = Array.isArray(object.material) ? object.material : [object.material];
                    objectMaterials.forEach((material) => {
                        material.map?.dispose();
                        material.dispose();
                    });
                }
            });
        },
    };
};
