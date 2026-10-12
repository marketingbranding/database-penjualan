import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { createWorkspaceLayout } from './layout.js';
import { createMaterials, SPATIAL_COLORS } from './materials.js';
import { createPawns, createRoleSystem } from './pawns.js';
import { addWorkspaceLabels, createLabelRenderer, populateRoleLegend } from './labels.js';
import { createSpatialControls } from './controls.js';
import { createSpatialPanel } from './panel.js';
import { createSpatialSelection } from './selection.js';
import { createHierarchyLinks } from './hierarchy.js';
import { createSpatialDragDrop } from './drag-drop.js';

const createRoundedPlatform = (scene, material, topMaterial, { width, depth, height, x, z, y = 0 }) => {
    const base = new THREE.Mesh(
        new RoundedBoxGeometry(width, height, depth, 4, Math.min(0.18, height / 2)),
        material,
    );
    base.position.set(x, y + height / 2, z);
    base.receiveShadow = true;
    base.castShadow = true;
    scene.add(base);

    const top = new THREE.Mesh(
        new RoundedBoxGeometry(width - 0.08, 0.06, depth - 0.08, 4, 0.12),
        topMaterial,
    );
    top.position.set(x, y + height + 0.02, z);
    top.receiveShadow = true;
    top.castShadow = true;
    scene.add(top);

    return { base, top };
};

const addIsland = (scene, layout, materials) => {
    const { width, depth, height } = layout.island;
    createRoundedPlatform(scene, materials.islandSide, materials.islandTop, {
        width,
        depth,
        height,
        x: 0,
        z: 0,
        y: -height,
    });
};

const addRoads = (scene, layout, materials) => {
    const roadMaterial = new THREE.MeshStandardMaterial({
        color: SPATIAL_COLORS.road,
        roughness: 0.95,
        polygonOffset: true,
        polygonOffsetFactor: -1,
        polygonOffsetUnits: -1,
    });
    const dashMaterial = new THREE.LineDashedMaterial({
        color: SPATIAL_COLORS.roadLine,
        dashSize: 0.3,
        gapSize: 0.24,
        linewidth: 1,
        transparent: true,
        opacity: 0.85,
    });

    layout.branches.forEach((branch) => {
        const road = new THREE.Mesh(new THREE.PlaneGeometry(branch.width + 0.5, 0.75), roadMaterial);
        road.rotation.x = -Math.PI / 2;
        road.position.set(branch.x, 0.015, branch.z - branch.depth / 2 - 0.65);
        road.receiveShadow = true;
        scene.add(road);
    });

    const links = [];
    for (let index = 0; index < layout.branches.length - 1; index += 1) {
        const from = layout.branches[index];
        const to = layout.branches[index + 1];
        links.push(new THREE.Vector3(from.x, 0.055, from.z), new THREE.Vector3(to.x, 0.055, to.z));
    }

    if (links.length > 0) {
        const geometry = new THREE.BufferGeometry().setFromPoints(links);
        const lines = new THREE.LineSegments(geometry, dashMaterial);
        lines.computeLineDistances();
        scene.add(lines);
    }
};

const addTrees = (scene, layout, materials) => {
    const locations = [];
    const margin = Math.max(1, Math.min(layout.island.width, layout.island.depth) * 0.08);

    layout.branches.forEach((branch, index) => {
        locations.push(
            [branch.x - branch.width / 2 - 0.7, branch.z - branch.depth / 2 - 0.7],
            [branch.x + branch.width / 2 + 0.7, branch.z + branch.depth / 2 + 0.7],
        );

        if (index % 2 === 0) {
            locations.push([branch.x + branch.width / 2 + 0.7, branch.z - branch.depth / 2 - 0.7]);
        }
    });

    locations.push(
        [-layout.island.width / 2 + margin, -layout.island.depth / 2 + margin],
        [layout.island.width / 2 - margin, layout.island.depth / 2 - margin],
    );

    const trunkGeometry = new THREE.CylinderGeometry(0.08, 0.1, 0.55, 8);
    const darkGeometry = new THREE.SphereGeometry(0.36, 12, 8);
    const lightGeometry = new THREE.SphereGeometry(0.26, 12, 8);
    const trunk = new THREE.InstancedMesh(trunkGeometry, materials.treeTrunk, locations.length);
    const dark = new THREE.InstancedMesh(darkGeometry, materials.treeDark, locations.length);
    const light = new THREE.InstancedMesh(lightGeometry, materials.treeLight, locations.length);
    const matrix = new THREE.Object3D();

    locations.forEach(([x, z], index) => {
        matrix.position.set(x, 0.35, z);
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
        mesh.castShadow = true;
        mesh.receiveShadow = true;
        mesh.instanceMatrix.needsUpdate = true;
        scene.add(mesh);
    });
};

const addOrganizationPlatforms = (scene, layout, materials) => {
    const teamTargets = [];

    layout.branches.forEach((branch) => {
        createRoundedPlatform(scene, materials.branchSide, materials.branchTop, {
            width: branch.width,
            depth: branch.depth,
            height: 0.35,
            x: branch.x,
            z: branch.z,
            y: 0.06,
        });

        branch.teams.forEach((team) => {
            const platform = createRoundedPlatform(scene, materials.teamSide, materials.teamTop, {
                width: team.width,
                depth: team.depth,
                height: 0.25,
                x: team.x,
                z: team.z,
                y: 0.43,
            });
            platform.base.material = platform.base.material.clone();
            platform.top.material = platform.top.material.clone();
            platform.base.userData = { type: 'team-drop-target', teamId: team.id };
            platform.top.userData = { type: 'team-drop-target', teamId: team.id };
            teamTargets.push({ team, meshes: [platform.base, platform.top] });
        });
    });

    return teamTargets;
};

const createRenderer = (canvas) => {
    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;
    renderer.setClearColor(0x000000, 0);

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
        Math.cos(elevation) * Math.sin(azimuth) * distance,
        Math.sin(elevation) * distance,
        Math.cos(elevation) * Math.cos(azimuth) * distance,
    );
    camera.lookAt(0, 0.25, 0);

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

    const layout = createWorkspaceLayout(workspace ?? { branches: [] });
    const scene = new THREE.Scene();
    const materials = createMaterials();
    const renderer = createRenderer(canvas);
    const camera = createCamera(layout.island, canvas.clientWidth || 900, canvas.clientHeight || 700);
    const hemisphere = new THREE.HemisphereLight(0xffffff, 0xdfe6cf, 0.9);
    const directional = new THREE.DirectionalLight(0xffffff, 1.1);
    directional.position.set(-10, 18, 12);
    directional.castShadow = true;
    directional.shadow.mapSize.set(1024, 1024);
    directional.shadow.camera.left = -layout.island.width;
    directional.shadow.camera.right = layout.island.width;
    directional.shadow.camera.top = layout.island.depth;
    directional.shadow.camera.bottom = -layout.island.depth;
    directional.shadow.camera.near = 0.1;
    directional.shadow.camera.far = 100;
    scene.add(hemisphere, directional);
    addIsland(scene, layout, materials);
    addRoads(scene, layout, materials);
    const teamTargets = addOrganizationPlatforms(scene, layout, materials);
    addTrees(scene, layout, materials);
    const roleSystem = createRoleSystem(workspace, roleMeta);
    const { pawns, overflowByTeam } = createPawns(scene, layout, workspace, roleSystem);
    const hierarchy = createHierarchyLinks(scene, workspace, pawns);
    const labelRenderer = createLabelRenderer(stage, scene);
    addWorkspaceLabels(labelRenderer, layout, workspace, pawns, overflowByTeam);
    populateRoleLegend(root.querySelector('[data-spatial-role-legend]'), roleSystem);
    const legend = root.querySelector('[data-spatial-legend]');
    const legendToggle = root.querySelector('[data-spatial-legend-toggle]');
    legendToggle?.addEventListener('click', () => legend?.classList.toggle('is-expanded'));
    const renderScene = () => {
        hierarchy.update();
        renderer.render(scene, camera);
        labelRenderer.update(scene, camera, pawns);
    };
    const spatialControls = createSpatialControls({ camera, canvas, render: renderScene });
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
    const dragDrop = createSpatialDragDrop({
        canvas,
        camera,
        pawns,
        teamTargets,
        panel,
        controls: spatialControls.controls,
        render: renderScene,
        updateHierarchy: hierarchy.update,
    });

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

    if (loading) {
        loading.remove();
    }

    root.dispatchEvent(new CustomEvent('spatial:scene-ready', { detail: { scene, camera, renderer, layout, pawns } }));

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
            root.removeEventListener('spatial:focus-user', focusUser);
            root.removeEventListener('spatial:focus-team', focusTeam);
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
