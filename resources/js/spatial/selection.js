import * as THREE from 'three';
import { setPawnState } from './pawns.js';

const POINTER_DISTANCE = 6;

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

export const createSpatialSelection = ({ canvas, camera, pawns, panel, controls, focusOn, render }) => {
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let hoveredPawn = null;
    let selectedPawn = null;
    let pointerStart = null;

    const updatePointer = (event) => {
        const bounds = canvas.getBoundingClientRect();
        pointer.x = ((event.clientX - bounds.left) / bounds.width) * 2 - 1;
        pointer.y = -((event.clientY - bounds.top) / bounds.height) * 2 + 1;
    };

    const intersectPawn = (event) => {
        updatePointer(event);
        raycaster.setFromCamera(pointer, camera);
        const intersections = raycaster.intersectObjects([...pawns.values()], true);

        return intersections.map(({ object }) => findPawn(object)).find(Boolean) ?? null;
    };

    const setHover = (pawn) => {
        if (hoveredPawn === pawn) {
            return;
        }
        setPawnState(hoveredPawn, 'hovered', false);
        hoveredPawn?.userData.labelObject?.userData.labelElement.classList.remove('is-hovered');
        setPawnState(pawn, 'hovered', true);
        pawn?.userData.labelObject?.userData.labelElement.classList.add('is-hovered');
        hoveredPawn = pawn;
        canvas.style.cursor = pawn ? 'pointer' : '';
        render();
    };

    const setSelected = (pawn) => {
        if (selectedPawn === pawn) {
            return;
        }
        selectedPawn?.userData.labelObject?.userData.labelElement.classList.remove('is-selected');
        setPawnState(selectedPawn, 'selected', false);
        selectedPawn = pawn;
        setPawnState(selectedPawn, 'selected', true);
        if (selectedPawn) {
            selectedPawn.userData.labelObject?.userData.labelElement.classList.add('is-selected');
            panel.selectUser(selectedPawn.userData.userId);
        } else {
            panel.selectUser(null);
        }
        render();
    };

    const onPointerDown = (event) => {
        pointerStart = { x: event.clientX, y: event.clientY };
    };
    const onPointerMove = (event) => {
        if (pointerStart && Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y) > POINTER_DISTANCE) {
            setHover(null);
            return;
        }
        setHover(intersectPawn(event));
    };
    const onPointerUp = (event) => {
        if (! pointerStart || Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y) <= POINTER_DISTANCE) {
            setSelected(intersectPawn(event));
        }
        pointerStart = null;
    };
    const onPointerLeave = () => {
        pointerStart = null;
        setHover(null);
    };

    canvas.addEventListener('pointerdown', onPointerDown);
    canvas.addEventListener('pointermove', onPointerMove);
    canvas.addEventListener('pointerup', onPointerUp);
    canvas.addEventListener('pointerleave', onPointerLeave);

    return {
        selectPawn: setSelected,
        dispose: () => {
            canvas.removeEventListener('pointerdown', onPointerDown);
            canvas.removeEventListener('pointermove', onPointerMove);
            canvas.removeEventListener('pointerup', onPointerUp);
            canvas.removeEventListener('pointerleave', onPointerLeave);
            controls.enabled = true;
        },
    };
};
