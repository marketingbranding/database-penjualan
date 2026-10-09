import * as THREE from 'three';
import { DragControls } from 'three/addons/controls/DragControls.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const root = document.querySelector('[data-spatial-workspace]');
const data = window.spatialWorkspace;
const loading = root.querySelector('[data-spatial-loading]');
const error = root.querySelector('[data-spatial-error]');
const labelLayer = root.querySelector('[data-spatial-labels]');
const inspector = document.querySelector('[data-spatial-inspector]');
const empty = document.querySelector('[data-spatial-empty]');
const detail = document.querySelector('[data-spatial-detail]');
const kind = document.querySelector('[data-spatial-kind]');
const title = document.querySelector('[data-spatial-title]');
const subtitle = document.querySelector('[data-spatial-subtitle]');
const content = document.querySelector('[data-spatial-content]');
const toggleEdit = document.querySelector('[data-spatial-toggle-edit]');
const mode = document.querySelector('[data-spatial-mode]');
const actions = document.querySelector('[data-spatial-actions]');
const teamSelect = document.querySelector('[data-spatial-team]');
const reportsToSelect = document.querySelector('[data-spatial-reports-to]');
const saveButton = document.querySelector('[data-spatial-save]');
const saveStatus = document.querySelector('[data-spatial-save-status]');
const users = new Map(data.users.map((user) => [user.id, user]));
const branches = new Map(data.branches.map((branch) => [branch.id, branch]));
const teams = new Map(data.branches.flatMap((branch) => branch.teams).map((team) => [team.id, team]));
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const clickable = [];
const draggableUsers = [];
const teamMeshes = [];
const sceneLabels = [];
let selected;
let selectedUser;
let editMode = false;

try {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(42, 1, 0.1, 200);
    const controls = new OrbitControls(camera, renderer.domElement);
    const rootGroup = new THREE.Group();

    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor(0x102d26, 1);
    root.appendChild(renderer.domElement);
    loading.remove();
    camera.position.set(10, 8.5, 14);
    controls.target.set(0, 0.4, -1.5);
    controls.enableDamping = false;
    controls.minDistance = 7;
    controls.maxDistance = 30;
    controls.maxPolarAngle = Math.PI / 2.05;

    scene.add(new THREE.AmbientLight(0x6de8ff, 1.8));
    const keyLight = new THREE.DirectionalLight(0x8ffff0, 4);
    keyLight.position.set(-8, 14, 10);
    scene.add(keyLight);
    scene.add(rootGroup);

    const floor = new THREE.Mesh(
        new THREE.PlaneGeometry(42, 30),
        new THREE.MeshBasicMaterial({ color: 0x061d2a, transparent: true, opacity: 0.92 }),
    );
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -0.2;
    rootGroup.add(floor);

    const grid = new THREE.GridHelper(42, 42, 0x1de4ff, 0x0b566a);
    grid.position.y = -0.18;
    grid.material.transparent = true;
    grid.material.opacity = 0.48;
    rootGroup.add(grid);

    const branchGeometry = new THREE.BoxGeometry(5.8, 0.2, 3.8);
    const teamGeometry = new THREE.BoxGeometry(3.25, 0.18, 2.25);
    const userGeometry = new THREE.ConeGeometry(0.3, 1.5, 6);
    const voxelGeometry = new THREE.BoxGeometry(0.42, 0.42, 0.42);
    const branchMaterial = createHologramMaterial(0xffbd4a, 0.9);
    const teamMaterial = createHologramMaterial(0x27e6d0, 0.82);
    const userMaterial = createHologramMaterial(0xff5f9e, 0.95);

    data.branches.forEach((branch, branchIndex) => {
        const branchX = (branchIndex - (data.branches.length - 1) / 2) * 8;
        const branchPosition = new THREE.Vector3(branchX, 0.55, -4);
        createVoxelTerrain(branchX, -4, 12, 7, branchIndex * 31 + branch.id, 0xffbd4a, 0.12, 1.05);
        const branchMesh = addNode(branchGeometry, branchMaterial, branchPosition.toArray(), {
            kind: 'Cabang', id: branch.id, label: branch.name,
        });
        branchMesh.userData.details = branch;
        addSceneLabel(branchMesh, branch.name, 'branch');

        branch.teams.forEach((team, teamIndex) => {
            const teamX = branchX + ((teamIndex % 3) - 1) * 2.9;
            const teamZ = -1.2 + Math.floor(teamIndex / 3) * 3.3;
            const teamPosition = new THREE.Vector3(teamX, 0.85, teamZ);
            createVoxelTerrain(teamX, teamZ, 7, 5, teamIndex * 17 + team.id, 0x27e6d0, 0.42, 1.55);
            addConnector(branchPosition, teamPosition, 0xffbd4a);
            const teamMesh = addNode(teamGeometry, teamMaterial, teamPosition.toArray(), {
                kind: 'Tim', id: team.id, label: team.name,
            });
            teamMesh.userData.details = team;
            teamMeshes.push(teamMesh);
            addSceneLabel(teamMesh, team.name, 'team');

            team.userIds.slice(0, 8).forEach((userId, userIndex) => {
                const user = users.get(userId);
                if (!user) return;
                const userX = teamX + ((userIndex % 4) - 1.5) * 0.9;
                const userZ = teamZ + 1.25 + Math.floor(userIndex / 4) * 1.1;
                const userPosition = new THREE.Vector3(userX, 1.45, userZ);
                addConnector(teamPosition, userPosition, 0xff5f9e);
                const userMesh = addNode(userGeometry, userMaterial, userPosition.toArray(), {
                    kind: 'User', id: user.id, label: user.name,
                });
                userMesh.userData.details = user;
                userMesh.userData.teamId = team.id;
                userMesh.userData.origin = userMesh.position.clone();
                draggableUsers.push(userMesh);
                const userLabel = addSceneLabel(userMesh, user.name, 'user');
                userLabel.style.display = 'none';
                userMesh.userData.labelElement = userLabel;
            });
        });
    });

    function createHologramMaterial(color, opacity) {
        return new THREE.ShaderMaterial({
            uniforms: {
                uColor: { value: new THREE.Color(color) },
                uOpacity: { value: opacity },
                uSelected: { value: 0 },
                uTime: { value: 0 },
            },
            vertexShader: `
                varying vec3 vWorldPosition;
                varying vec3 vNormal;
                void main() {
                    vWorldPosition = (modelMatrix * vec4(position, 1.0)).xyz;
                    vNormal = normalize(normalMatrix * normal);
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
            fragmentShader: `
                uniform vec3 uColor;
                uniform float uOpacity;
                uniform float uSelected;
                uniform float uTime;
                varying vec3 vWorldPosition;
                varying vec3 vNormal;
                void main() {
                    float scan = 0.5 + 0.5 * sin(vWorldPosition.y * 11.0 - uTime * 2.8);
                    float rim = 1.0 - abs(dot(normalize(vNormal), vec3(0.0, 0.0, 1.0)));
                    float pulse = 0.92 + 0.12 * sin(uTime * 2.0);
                    vec3 color = uColor * (1.0 + rim * 0.8 + uSelected * 0.9);
                    float alpha = clamp(uOpacity * (0.78 + scan * 0.12 + rim * 0.18 + uSelected * 0.2), 0.72, 1.0);
                    gl_FragColor = vec4(color * pulse, alpha);
                }
            `,
            transparent: true,
            blending: THREE.NormalBlending,
            depthTest: true,
            depthWrite: true,
            side: THREE.DoubleSide,
        });
    }

    function createVoxelTerrain(centerX, centerZ, width, depth, seed, color, baseY, heightScale) {
        const count = width * depth * 5;
        const terrain = new THREE.InstancedMesh(voxelGeometry, createHologramMaterial(color, 0.92), count);
        const dummy = new THREE.Object3D();
        let index = 0;

        for (let x = 0; x < width; x++) {
            for (let z = 0; z < depth; z++) {
                const wave = Math.sin((x + seed) * 0.78) + Math.cos((z - seed) * 0.64);
                const height = Math.max(1, Math.floor(2 + (wave + 2) * heightScale));
                for (let y = 0; y < height; y++) {
                    dummy.position.set(
                        centerX + (x - (width - 1) / 2) * 0.36,
                        baseY + y * 0.32 + 0.16,
                        centerZ + (z - (depth - 1) / 2) * 0.36,
                    );
                    dummy.scale.set(1, 1, 1);
                    dummy.updateMatrix();
                    terrain.setMatrixAt(index++, dummy.matrix);
                }
            }
        }

        terrain.count = index;
        terrain.instanceMatrix.needsUpdate = true;
        rootGroup.add(terrain);
    }

    function addConnector(start, end, color) {
        const geometry = new THREE.BufferGeometry().setFromPoints([start, end]);
        const material = new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.48, blending: THREE.AdditiveBlending });
        const line = new THREE.Line(geometry, material);
        rootGroup.add(line);
    }

    function addNode(geometry, material, position, userData) {
        const mesh = new THREE.Mesh(geometry, material.clone());
        mesh.position.set(...position);
        mesh.userData = userData;
        rootGroup.add(mesh);
        clickable.push(mesh);
        return mesh;
    }

    function addSceneLabel(object, text, type) {
        const label = document.createElement('div');
        label.className = `absolute -translate-x-1/2 whitespace-nowrap rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider shadow-lg backdrop-blur ${type === 'branch' ? 'border-amber-300/50 bg-amber-950/80 text-amber-200' : type === 'team' ? 'border-cyan-300/50 bg-cyan-950/80 text-cyan-100' : 'border-pink-300/50 bg-pink-950/80 text-pink-100'}`;
        label.textContent = text;
        labelLayer.appendChild(label);
        sceneLabels.push({ label, object, offset: type === 'user' ? 1.05 : 0.8 });
        return label;
    }

    function updateLabels() {
        sceneLabels.forEach(({ label, object, offset }) => {
            const position = object.position.clone();
            position.y += offset;
            position.project(camera);
            const visible = position.z > -1 && position.z < 1;
            label.style.display = visible && label.style.display !== 'none' ? 'block' : label.style.display;
            label.style.left = `${(position.x * 0.5 + 0.5) * root.clientWidth}px`;
            label.style.top = `${(-position.y * 0.5 + 0.5) * root.clientHeight}px`;
        });
    }

    const timer = new THREE.Timer();
    timer.connect(document);

    function render() {
        updateLabels();
        renderer.render(scene, camera);
    }

    function animate(timestamp) {
        timer.update(timestamp);
        const elapsed = timer.getElapsed();
        rootGroup.traverse((object) => {
            if (object.material?.uniforms?.uTime) {
                object.material.uniforms.uTime.value = elapsed;
            }
        });
        render();
    }

    function resize() {
        const width = root.clientWidth;
        const height = root.clientHeight;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        render();
    }

    function select(object) {
        if (selected?.material?.uniforms?.uSelected) selected.material.uniforms.uSelected.value = 0;
        selected = object;
        if (selected?.material?.uniforms?.uSelected) selected.material.uniforms.uSelected.value = 1;
        showDetail(object.userData.kind, object.userData.details);
    }

    function populateAssignment(user) {
        if (!actions || !teamSelect || !reportsToSelect) return;
        teamSelect.replaceChildren();
        reportsToSelect.replaceChildren();
        teams.forEach((team) => {
            const option = new Option(`${team.code} · ${team.name}`, team.id, false, user.teamIds.includes(team.id));
            teamSelect.appendChild(option);
        });
        reportsToSelect.appendChild(new Option('Tanpa atasan', '', false, !user.reportsToId));
        users.forEach((candidate) => {
            if (candidate.id === user.id) return;
            reportsToSelect.appendChild(new Option(candidate.name, candidate.id, false, candidate.id === user.reportsToId));
        });
        actions.classList.toggle('hidden', !editMode);
    }

    function showDetail(type, item) {
        inspector.classList.remove('hidden');
        empty.classList.add('hidden');
        detail.classList.remove('hidden');
        if (actions) actions.classList.add('hidden');
        sceneLabels.forEach(({ label, object }) => {
            if (object.userData.kind === 'User') label.style.display = 'none';
        });
        kind.textContent = type;
        title.textContent = item.name;
        content.replaceChildren();

        if (type === 'Cabang') {
            subtitle.textContent = `${item.code} · ${item.teams.length} tim`;
            addRow('Anggota', `${item.userIds.length} user`);
            item.teams.forEach((team) => addRow(team.name, `${team.userCount} anggota`));
        } else if (type === 'Tim') {
            const coordinator = users.get(item.coordinatorId);
            const supervisor = users.get(item.supervisorId);
            subtitle.textContent = `${item.code} · ${item.userCount} anggota`;
            addRow('Koordinator', coordinator?.name ?? 'Belum ditetapkan');
            addRow('Supervisor', supervisor?.name ?? 'Belum ditetapkan');
            addRow('Anggota', item.userIds.map((id) => users.get(id)?.name).filter(Boolean).join(', ') || 'Belum ada anggota');
        } else {
            selectedUser = item;
            subtitle.textContent = item.email;
            addRow('Branch', branches.get(item.branchId)?.name ?? 'Belum ditetapkan');
            addRow('Team', item.teamNames.join(', ') || 'Belum ditetapkan');
            addRow('Atasan', users.get(item.reportsToId)?.name ?? 'Belum ditetapkan');
            addRow('Global role', item.globalRoles.join(', ') || 'Tidak ada');
            addRow('Team role', item.roles.map((role) => role.name).join(', ') || 'Tidak ada');
            selected?.userData.labelElement && (selected.userData.labelElement.style.display = 'block');
            populateAssignment(item);
        }
    }

    function addRow(label, value) {
        const row = document.createElement('div');
        row.className = 'rounded-xl bg-marison-cream-50 p-3';
        row.innerHTML = '<p class="text-[10px] font-bold uppercase tracking-widest text-gray-500"></p><p class="mt-1 text-sm font-semibold text-marison-ink-950"></p>';
        row.children[0].textContent = label;
        row.children[1].textContent = value;
        content.appendChild(row);
    }

    function pointerPosition(event) {
        const bounds = renderer.domElement.getBoundingClientRect();
        pointer.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
        pointer.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
    }

    renderer.domElement.addEventListener('pointermove', (event) => {
        pointerPosition(event);
        raycaster.setFromCamera(pointer, camera);
        renderer.domElement.style.cursor = raycaster.intersectObjects(clickable).length ? 'pointer' : 'grab';
    });
    renderer.domElement.addEventListener('click', (event) => {
        pointerPosition(event);
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(clickable)[0];
        if (hit) select(hit.object);
    });
    const dragControls = new DragControls(draggableUsers, camera, renderer.domElement);
    dragControls.enabled = false;
    dragControls.addEventListener('dragstart', () => {
        controls.enabled = false;
    });
    dragControls.addEventListener('dragend', (event) => {
        controls.enabled = true;
        const nearestTeam = teamMeshes
            .map((teamMesh) => ({ teamMesh, distance: teamMesh.position.distanceTo(event.object.position) }))
            .sort((a, b) => a.distance - b.distance)[0];

        if (!nearestTeam || nearestTeam.distance > 2.6) {
            event.object.position.copy(event.object.userData.origin);
            render();
            return;
        }

        const user = users.get(event.object.userData.id);
        if (!user) return;
        select(event.object);
        teamSelect.value = String(nearestTeam.teamMesh.userData.id);
        event.object.position.copy(event.object.userData.origin);
        render();
    });
    if (toggleEdit) {
        toggleEdit.addEventListener('click', () => {
            editMode = !editMode;
            mode.textContent = editMode ? 'Edit mode' : 'View mode';
            toggleEdit.textContent = editMode ? 'View mode' : 'Edit mode';
            dragControls.enabled = editMode;
            if (selectedUser) populateAssignment(selectedUser);
        });
    }
    if (saveButton) {
        saveButton.addEventListener('click', async () => {
            if (!selectedUser || !teamSelect.value) return;
            saveButton.disabled = true;
            saveStatus.classList.remove('hidden');
            saveStatus.className = 'mt-3 text-xs text-gray-500';
            saveStatus.textContent = 'Menyimpan assignment...';
            try {
                const response = await fetch(window.spatialWorkspaceUpdateUrl.replace('__USER__', selectedUser.id), {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ team_id: teamSelect.value, reports_to_id: reportsToSelect.value || null }),
                });
                if (!response.ok) throw new Error('Assignment gagal disimpan.');
                saveStatus.className = 'mt-3 text-xs text-emerald-700';
                saveStatus.textContent = 'Tersimpan. Memuat ulang workspace...';
                window.setTimeout(() => window.location.reload(), 500);
            } catch (exception) {
                saveStatus.className = 'mt-3 text-xs text-red-700';
                saveStatus.textContent = exception.message;
                saveButton.disabled = false;
            }
        });
    }
    controls.addEventListener('change', render);
    window.addEventListener('resize', resize);
    resize();
    renderer.setAnimationLoop(animate);
} catch (exception) {
    loading.remove();
    error.classList.remove('hidden');
    error.classList.add('grid');
    error.textContent = 'Spatial Workspace membutuhkan dukungan WebGL pada browser ini.';
    console.error(exception);
}
