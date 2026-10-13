import * as THREE from 'three';
import { setPawnState } from './pawns.js';
import { buildPawnGrid, buildTeamPawnIndex, pickPawnAnalytic, pickTeamAnalytic } from './picking.js';
import { getSceneTheme } from './themes.js';

const DRAG_DISTANCE = 8;
const DRAG_HEIGHT = 0.74;

export const createSpatialDragDrop = ({ canvas, camera, pawns, teamTargets, panel, controls, render, updateHierarchy, updateHierarchyForUser = null, setLabelsSuspended = () => {}, themeName = 'light', pickingMeshes = null, getPawnFromIntersection = null, syncPawn = null, refreshPawnGrid = () => {}, sharedPawnGrid = null, sharedPawnsByTeam = null, unassignedPlot = null, setInteractionQuality = () => {} }) => {
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const ground = new THREE.Plane(new THREE.Vector3(0, 1, 0), -DRAG_HEIGHT);
    let pointerStart = null;
    let draggedPawn = null;
    let draggedTeam = null;
    let originalPosition = null;
    let activeTheme = getSceneTheme(themeName);
    const pawnGrid = sharedPawnGrid ?? buildPawnGrid(pawns);
    const pawnsByTeam = sharedPawnsByTeam ?? buildTeamPawnIndex(pawns);
    const tooltip = document.createElement('div');
    tooltip.className = 'spatial-drag-tooltip';
    tooltip.textContent = 'Tidak bisa melepas tim dari sini';
    tooltip.style.cssText = 'position:absolute;z-index:30;display:none;pointer-events:none;background:rgb(var(--c-inverse));color:rgb(var(--c-inverse-text));font-size:11px;padding:.3rem .6rem;border-radius:.5rem;';
    canvas.parentElement?.append(tooltip);
    const pointInUnassigned = (point) => {
        if (! point || ! unassignedPlot) {
            return false;
        }

        return Math.abs(point.x - unassignedPlot.x) <= unassignedPlot.width / 2
            && Math.abs(point.z - unassignedPlot.z) <= unassignedPlot.depth / 2;
    };
    const showTooltip = (event) => {
        const stageRect = canvas.getBoundingClientRect();
        tooltip.style.display = 'block';
        tooltip.style.left = `${event.clientX - stageRect.left + 12}px`;
        tooltip.style.top = `${event.clientY - stageRect.top + 12}px`;
    };
    const hideTooltip = () => {
        tooltip.style.display = 'none';
    };
    const notifyGridMove = (pawn, oldX, oldZ) => {
        refreshPawnGrid(pawn, oldX, oldZ);
    };
    const updatePointer = (event) => {
        const bounds = canvas.getBoundingClientRect();
        pointer.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
        pointer.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
    };

    const findPawn = (object) => {
        let current = object;
        while (current) {
            if (current.userData?.type === 'user') {
                return current;
            }
            current = current.parent;
        }

        return null;
    };

    const intersectPawn = (event) => {
        updatePointer(event);
        const analytic = pickPawnAnalytic({ pointer, camera, pawns, pawnGrid, teamTargets, byTeam: pawnsByTeam });
        if (analytic) {
            return analytic;
        }
        raycaster.setFromCamera(pointer, camera);

        return raycaster.intersectObjects(pickingMeshes ?? [...pawns.values()], true)
            .map((intersection) => getPawnFromIntersection?.(intersection) ?? findPawn(intersection.object))
            .find(Boolean) ?? null;
    };

    const intersectTeam = (event) => {
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);
        const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), -0.555);
        const point = new THREE.Vector3();
        if (raycaster.ray.intersectPlane(plane, point)) {
            const analyticTeam = pickTeamAnalytic(point, teamTargets);
            if (analyticTeam !== null) {
                return analyticTeam;
            }
        }
        const meshes = teamTargets.flatMap(({ meshes }) => meshes);
        const hit = raycaster.intersectObjects(meshes, true)[0];

        return hit?.object.userData?.teamId ?? null;
    };

    const setTeamHighlight = (teamId, shouldRender = true) => {
        if (draggedTeam === teamId) {
            return;
        }
        const activeTarget = teamTargets.find(({ team }) => team.id === teamId);
        const highlight = activeTarget?.highlight ?? teamTargets[0]?.highlight;
        if (highlight) {
            highlight.visible = activeTarget !== undefined;
            if (activeTarget) {
                highlight.position.set(activeTarget.team.x, 0.69, activeTarget.team.z);
                highlight.updateMatrix();
                highlight.material.color.setHex(activeTheme.teamHighlight);
            }
        }
        draggedTeam = teamId;
        if (shouldRender) {
            render();
        }
    };

    const beginDrag = (pawn) => {
        draggedPawn = pawn;
        originalPosition = pawn.position.clone();
        controls.enabled = false;
        setInteractionQuality(true);
        setLabelsSuspended(true);
        setPawnState(pawn, 'hovered', true);
        canvas.style.cursor = 'grabbing';
    };

    const moveDrag = (event) => {
        if (! draggedPawn) {
            return;
        }
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);
        const point = new THREE.Vector3();
        if (raycaster.ray.intersectPlane(ground, point)) {
            const oldX = draggedPawn.position.x;
            const oldZ = draggedPawn.position.z;
            draggedPawn.position.x = point.x;
            draggedPawn.position.z = point.z;
            syncPawn?.(draggedPawn);
            notifyGridMove(draggedPawn, oldX, oldZ);
            if (pointInUnassigned(point)) {
                showTooltip(event);
            } else {
                hideTooltip();
            }
            if (updateHierarchyForUser) {
                updateHierarchyForUser(draggedPawn.userData.userId);
            } else {
                updateHierarchy();
            }
        }
        setTeamHighlight(intersectTeam(event), false);
        render();
    };

    const endDrag = (event) => {
        if (! draggedPawn) {
            pointerStart = null;
            return;
        }
        const pawn = draggedPawn;
        const teamId = intersectTeam(event);
        const accepted = teamId !== null && panel.stageAssignment(pawn.userData.userId, teamId);
        if (! accepted) {
            pawn.position.copy(originalPosition);
        }
        setPawnState(pawn, 'hovered', false);
        setTeamHighlight(null, false);
        hideTooltip();
        draggedPawn = null;
        originalPosition = null;
        canvas.style.cursor = '';
        controls.enabled = true;
        setInteractionQuality(false);
        setLabelsSuspended(false);
        updateHierarchy();
        render();
        pointerStart = null;
    };

    const onPointerDown = (event) => {
        if (! panel.isEditMode()) {
            return;
        }
        const pawn = intersectPawn(event);
        pointerStart = pawn ? { x: event.clientX, y: event.clientY, pawn } : null;
    };

    const onPointerMove = (event) => {
        if (! pointerStart) {
            return;
        }
        if (! draggedPawn && Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y) > DRAG_DISTANCE) {
            beginDrag(pointerStart.pawn);
        }
        moveDrag(event);
    };

    const onPointerUp = (event) => {
        if (draggedPawn) {
            endDrag(event);
        } else {
            pointerStart = null;
        }
    };

    canvas.addEventListener('pointerdown', onPointerDown);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerup', onPointerUp);

    return {
        applyTheme: (nextThemeName) => {
            activeTheme = getSceneTheme(nextThemeName);
            if (draggedTeam !== null) {
                const currentTeam = draggedTeam;
                draggedTeam = null;
                setTeamHighlight(currentTeam);
            }
        },
        dispose: () => {
            tooltip.remove();
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerup', onPointerUp);
            controls.enabled = true;
        },
    };
};
