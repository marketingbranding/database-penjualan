import * as THREE from 'three';
import { CSS2DObject, CSS2DRenderer } from 'three/addons/renderers/CSS2DRenderer.js';
import { facePawnsToCamera } from './pawns.js';
import { getSceneTheme } from './themes.js';

const createLabel = (text, type, dotColor, overflowCount = 0) => {
    const element = document.createElement('div');
    element.className = `spatial-label spatial-label-${type}`;
    element.dataset.labelType = type;
    element.innerHTML = `<span class="spatial-label-dot" style="background:${dotColor}"></span><span></span>`;
    element.lastElementChild.textContent = text;
    element.title = text;
    if (overflowCount > 0) {
        const overflow = document.createElement('span');
        overflow.className = 'spatial-label-count';
        overflow.textContent = `+${overflowCount}`;
        element.append(overflow);
    }

    return element;
};

const displayTeamName = (name, fallback = '') => (name || fallback).replace(/^migrasi\s+/i, '').trim();

const attachLabel = (renderer, parent, text, type, dotColor, position, overflowCount = 0) => {
    const label = createLabel(text, type, dotColor, overflowCount);
    const object = new THREE.Object3D();
    object.position.copy(position);
    object.userData.labelElement = label;
    object.userData.labelType = type;
    object.add(new CSS2DObject(label));
    parent.add(object);
    renderer.labels.push(object);

    return object;
};

const labelIsVisible = (object, camera) => {
    const point = new THREE.Vector3();
    object.getWorldPosition(point);
    point.project(camera);

    return point.z >= -1 && point.z <= 1;
};

export const createLabelRenderer = (stage, scene) => {
    const renderer = new CSS2DRenderer();
    renderer.domElement.className = 'spatial-label-layer';
    renderer.domElement.setAttribute('aria-hidden', 'true');
    stage.appendChild(renderer.domElement);
    renderer.scene = scene;
    renderer.labels = [];
    renderer.focusedTeamId = null;
    renderer.layoutFrame = null;
    renderer.suspendLayout = false;
    renderer.powerSaving = false;
    renderer.setSuspended = (suspended) => {
        renderer.suspendLayout = suspended;
    };
    renderer.setPowerSaving = (enabled) => {
        renderer.powerSaving = enabled;
    };
    renderer.userLabelPool = [];
    renderer.userLabelStates = new Map();
    renderer.userLabelData = new Map();
    renderer.pawns = new Map();
    renderer.setUserLabelState = (pawn, state, active) => {
        if (! pawn) {
            return;
        }

        const userId = pawn.userData.userId;
        const current = renderer.userLabelStates.get(userId) ?? { hovered: false, selected: false };
        current[state] = active;
        renderer.userLabelStates.set(userId, current);

        if (active && ! pawn.userData.labelObject) {
            const available = renderer.userLabelPool.find((object) => {
                const ownerId = object.userData.userId;
                const ownerState = ownerId === undefined ? null : renderer.userLabelStates.get(ownerId);

                return ownerId === undefined || ! ownerState?.hovered && ! ownerState?.selected;
            });
            if (available) {
                const previousOwner = available.userData.userId;
                if (previousOwner !== undefined) {
                    const previousPawn = renderer.pawns.get(previousOwner);
                    if (previousPawn?.userData.labelObject === available) {
                        delete previousPawn.userData.labelObject;
                    }
                }
                const user = renderer.userLabelData.get(userId);
                available.userData.userId = userId;
                available.userData.teamId = pawn.userData.teamId;
                available.userData.labelElement.querySelector('span:last-of-type').textContent = user?.name ?? '';
                available.userData.labelElement.title = user?.name ?? '';
                available.position.set(0, 1.5, 0);
                pawn.add(available);
                pawn.userData.labelObject = available;
            }
        }

        const element = pawn.userData.labelObject?.userData.labelElement;
        if (element) {
            element.classList.toggle('is-hovered', current.hovered);
            element.classList.toggle('is-selected', current.selected);
        }
    };
    renderer.applyTheme = (themeName) => {
        const theme = getSceneTheme(themeName);
        renderer.labels.forEach((object) => {
            const dot = object.userData.labelElement?.querySelector('.spatial-label-dot');
            const color = theme.labelDots[object.userData.labelType];
            if (dot && color) {
                dot.style.backgroundColor = color;
            }
        });
    };

    renderer.update = (scene, camera, pawns) => {
        renderer.domElement.classList.toggle('spatial-label-layer-close', camera.zoom >= 1.6);
        renderer.domElement.classList.toggle('spatial-label-layer-focus-team', renderer.focusedTeamId !== null);
        if (camera.zoom >= 1.8 && renderer.focusedTeamId !== null) {
            renderer.pawns.forEach((pawn) => {
                if (pawn.userData.teamId === renderer.focusedTeamId) {
                    renderer.setUserLabelState(pawn, 'focused', true);
                }
            });
        }
        renderer.labels.forEach((object) => {
            const isUserLabel = object.userData.labelType === 'user';
            const isTeamLabel = object.userData.labelType === 'team';
            const pawn = isUserLabel ? pawns.get(object.userData.userId) : null;
            const isFocusedTeam = renderer.focusedTeamId !== null && object.userData.teamId === renderer.focusedTeamId;
            const userState = isUserLabel ? renderer.userLabelStates.get(object.userData.userId) : null;
            const allowZoomUserLabels = ! renderer.powerSaving && camera.zoom >= 1.8 && isFocusedTeam;
            object.visible = labelIsVisible(object, camera)
                && (! isUserLabel || userState?.hovered || userState?.selected || allowZoomUserLabels)
                && (! isTeamLabel || camera.zoom >= 1.2);
        });
        facePawnsToCamera(pawns, camera);
        renderer.render(scene, camera);
        if (! renderer.suspendLayout && renderer.layoutFrame === null) {
            renderer.layoutFrame = requestAnimationFrame(() => {
                renderer.layoutFrame = null;
                layoutLabels(renderer, stage);
            });
        }
    };

    renderer.focusTeam = (teamId) => {
        renderer.focusedTeamId = teamId;
    };

    renderer.clearFocus = () => {
        renderer.focusedTeamId = null;
    };

    renderer.dispose = () => {
        if (renderer.layoutFrame !== null) {
            cancelAnimationFrame(renderer.layoutFrame);
        }
        renderer.domElement.remove();
        renderer.labels.length = 0;
        renderer.userLabelPool.length = 0;
        renderer.userLabelStates.clear();
        renderer.userLabelData.clear();
        renderer.pawns.clear();
    };

    return renderer;
};

const layoutLabels = (renderer, stage) => {
    const stageRect = stage.getBoundingClientRect();
    const visibleLabels = renderer.labels
        .filter((object) => object.visible && object.userData.labelType !== 'user')
        .sort((left, right) => (left.userData.labelType === 'branch' ? -1 : 1) - (right.userData.labelType === 'branch' ? -1 : 1));
    const placed = [];

    visibleLabels.forEach((object) => {
        const element = object.userData.labelElement;
        element.style.marginLeft = '0px';
        element.style.marginTop = '0px';
        element.style.visibility = 'visible';
        let rect = element.getBoundingClientRect();
        const horizontalShift = rect.left < stageRect.left + 4
            ? stageRect.left + 4 - rect.left
            : rect.right > stageRect.right - 4
                ? stageRect.right - 4 - rect.right
                : 0;
        if (horizontalShift !== 0) {
            element.style.marginLeft = `${horizontalShift}px`;
            rect = element.getBoundingClientRect();
        }
        if (object.userData.labelType === 'team' || object.userData.labelType === 'unassigned') {
            const overlapsPlaced = () => placed.some((previous) => {
                const overlapWidth = Math.max(0, Math.min(rect.right, previous.right) - Math.max(rect.left, previous.left));
                const overlapHeight = Math.max(0, Math.min(rect.bottom, previous.bottom) - Math.max(rect.top, previous.top));
                const overlap = overlapWidth * overlapHeight;
                const smallerArea = Math.min(rect.width * rect.height, previous.width * previous.height);

                return smallerArea > 0 && overlap / smallerArea > 0.1;
            });
            if (overlapsPlaced()) {
                element.style.marginTop = '-12px';
                rect = element.getBoundingClientRect();
            }
            if (overlapsPlaced()) {
                element.style.visibility = 'hidden';
            }
        }
        placed.push(rect);
    });
};

export const addWorkspaceLabels = (labelRenderer, layout, workspace, pawns, overflowByTeam = new Map(), options = {}) => {
    const userPlacement = new Map();

    layout.branches.forEach((branch) => {
        attachLabel(
            labelRenderer,
            labelRenderer.scene,
            branch.name || branch.code,
            'branch',
            getSceneTheme().labelDots.branch,
            new THREE.Vector3(branch.x - branch.width / 2 + 0.55, 0.62, branch.z + branch.depth / 2 + 0.28),
        );

        branch.teams.forEach((team) => {
            const columns = Math.max(1, Math.ceil(Math.sqrt(team.userIds?.length ?? 0)));
            (team.userIds ?? []).forEach((userId, index) => {
                userPlacement.set(userId, {
                    lane: index % columns,
                    row: Math.floor(index / columns),
                    teamId: team.id,
                });
            });
            const labelObject = attachLabel(
                labelRenderer,
                labelRenderer.scene,
                displayTeamName(team.name, team.code),
                'team',
                getSceneTheme().labelDots.team,
                new THREE.Vector3(team.x, 2.0, team.z),
                overflowByTeam.get(team.id) ?? 0,
            );
            labelObject.userData.teamId = team.id;
            labelObject.userData.labelElement.addEventListener('click', (event) => {
                event.stopPropagation();
                labelObject.userData.labelElement.dispatchEvent(new CustomEvent('spatial:focus-team', { bubbles: true, detail: { teamId: team.id } }));
            });
        });
    });

    if (layout.unassigned) {
        const total = options.unassignedCount ?? 0;
        const labelObject = attachLabel(
            labelRenderer,
            labelRenderer.scene,
            total === 0 ? 'Semua user sudah ditugaskan' : `Belum ditugaskan · ${total}`,
            'unassigned',
            getSceneTheme().labelDots.unassigned ?? '#9a9a9a',
            new THREE.Vector3(layout.unassigned.x, 1.6, layout.unassigned.z),
            options.unassignedOverflow ?? 0,
        );
        labelObject.userData.teamId = null;
        labelObject.userData.unassigned = true;
    }

    const userMap = new Map((workspace.users ?? []).map((user) => [user.id, user]));
    labelRenderer.userLabelData = userMap;
    labelRenderer.pawns = pawns;
    const userLabelCapacity = Math.max(0, 100 - labelRenderer.labels.length);
    for (let index = 0; index < userLabelCapacity; index += 1) {
        const labelObject = attachLabel(
            labelRenderer,
            labelRenderer.scene,
            '',
            'user',
            getSceneTheme().labelDots.user,
            new THREE.Vector3(0, 1.5, 0),
        );
        labelObject.visible = false;
        labelObject.userData.userId = undefined;
        labelObject.userData.teamId = null;
        labelRenderer.userLabelPool.push(labelObject);
    }
};

export const populateRoleLegend = (element, roleSystem) => {
    if (! element) {
        return;
    }

    element.replaceChildren(...roleSystem.roleNames.map((role) => {
        const row = document.createElement('span');
        row.className = 'spatial-role-legend-item';
        row.innerHTML = `<span class="spatial-role-legend-dot"></span><span></span>`;
        row.querySelector('span:last-child').textContent = role;
        row.querySelector('.spatial-role-legend-dot').style.backgroundColor = `#${roleSystem.colorForRole(role).toString(16).padStart(6, '0')}`;

        return row;
    }));
};
