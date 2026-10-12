import * as THREE from 'three';
import { setPawnState } from './pawns.js';

const DRAG_DISTANCE = 8;
const DRAG_HEIGHT = 0.74;

export const createSpatialDragDrop = ({ canvas, camera, pawns, teamTargets, panel, controls, render, updateHierarchy }) => {
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const ground = new THREE.Plane(new THREE.Vector3(0, 1, 0), -DRAG_HEIGHT);
    let pointerStart = null;
    let draggedPawn = null;
    let draggedTeam = null;
    let originalPosition = null;

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
        raycaster.setFromCamera(pointer, camera);

        return raycaster.intersectObjects([...pawns.values()], true)
            .map(({ object }) => findPawn(object))
            .find(Boolean) ?? null;
    };

    const intersectTeam = (event) => {
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);
        const meshes = teamTargets.flatMap(({ meshes }) => meshes);
        const hit = raycaster.intersectObjects(meshes, true)[0];

        return hit?.object.userData?.teamId ?? null;
    };

    const setTeamHighlight = (teamId) => {
        if (draggedTeam === teamId) {
            return;
        }
        teamTargets.forEach(({ team, meshes }) => {
            meshes.forEach((mesh) => {
                mesh.material.emissive?.setHex(team.id === teamId ? 0x6b9f32 : 0x000000);
                if (mesh.material.emissiveIntensity !== undefined) {
                    mesh.material.emissiveIntensity = team.id === teamId ? 0.32 : 0;
                }
            });
        });
        draggedTeam = teamId;
        render();
    };

    const beginDrag = (pawn) => {
        draggedPawn = pawn;
        originalPosition = pawn.position.clone();
        controls.enabled = false;
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
            draggedPawn.position.x = point.x;
            draggedPawn.position.z = point.z;
            updateHierarchy();
            render();
        }
        setTeamHighlight(intersectTeam(event));
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
        setTeamHighlight(null);
        draggedPawn = null;
        originalPosition = null;
        canvas.style.cursor = '';
        controls.enabled = true;
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
        dispose: () => {
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerup', onPointerUp);
            controls.enabled = true;
        },
    };
};
