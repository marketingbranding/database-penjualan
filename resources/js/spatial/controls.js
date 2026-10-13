import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const MIN_ZOOM = 0.5;
const MAX_ZOOM = 4;
const ZOOM_STEP = 0.1;
const ANIMATION_DURATION = 280;

const easeInOut = (value) => value * value * (3 - 2 * value);

const isTypingTarget = (target) => target instanceof HTMLElement
    && ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(target.tagName);

export const createSpatialControls = ({ camera, canvas, render, setInteractionQuality = () => {}, target = null }) => {
    const controls = new OrbitControls(camera, canvas);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.enablePan = true;
    controls.screenSpacePanning = true;
    controls.enableZoom = true;
    controls.minPolarAngle = THREE.MathUtils.degToRad(25);
    controls.maxPolarAngle = THREE.MathUtils.degToRad(60);
    controls.minZoom = MIN_ZOOM;
    controls.maxZoom = MAX_ZOOM;
    controls.zoomSpeed = 0.8;
    controls.rotateSpeed = 0.55;
    controls.panSpeed = 0.7;
    controls.autoRotate = false;
    controls.mouseButtons.LEFT = THREE.MOUSE.ROTATE;
    controls.mouseButtons.RIGHT = THREE.MOUSE.PAN;
    controls.touches.ONE = THREE.TOUCH.ROTATE;
    controls.touches.TWO = THREE.TOUCH.DOLLY_PAN;
    controls.target.set(target?.x ?? 0, 0.25, target?.z ?? 0);
    controls.update();
    controls.saveState();

    const initialPosition = camera.position.clone();
    const initialTarget = controls.target.clone();
    const initialZoom = camera.zoom;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let animationFrame = null;
    let animation = null;
    let animationTimer = null;
    let renderRequested = false;

    const updateZoomLabel = () => {
        const label = document.querySelector('[data-spatial-zoom-value]');
        if (label) {
            label.textContent = `${Math.round(camera.zoom * 100)}%`;
        }
    };

    const requestFrame = () => {
        if (animationFrame === null) {
            animationFrame = requestAnimationFrame(tick);
        }
    };

    const tick = () => {
        animationFrame = null;
        const requestedBeforeUpdate = renderRequested;
        renderRequested = false;
        const changed = controls.update();

        if (animation) {
            const progress = Math.min(1, (performance.now() - animation.startedAt) / ANIMATION_DURATION);
            const eased = easeInOut(progress);
            camera.position.lerpVectors(animation.fromPosition, animation.toPosition, eased);
            controls.target.lerpVectors(animation.fromTarget, animation.toTarget, eased);
            camera.zoom = animation.fromZoom + (animation.toZoom - animation.fromZoom) * eased;
            camera.updateProjectionMatrix();
            controls.update();
            render();
            updateZoomLabel();

            if (progress >= 1) {
                finishAnimation(animation);
            } else {
                requestFrame();
                return;
            }
        }

        if (requestedBeforeUpdate || changed) {
            render();
            updateZoomLabel();
        }

        if (renderRequested || changed) {
            requestFrame();
        }
    };

    const finishAnimation = (currentAnimation) => {
        if (animation !== currentAnimation) {
            return;
        }

        camera.position.copy(currentAnimation.toPosition);
        controls.target.copy(currentAnimation.toTarget);
        camera.zoom = currentAnimation.toZoom;
        camera.updateProjectionMatrix();
        animation = null;
        controls.enabled = true;
        setInteractionQuality(false);
        controls.update();
        render();
        updateZoomLabel();
    };

    const animateTo = ({ position, target, zoom }) => {
        if (prefersReducedMotion) {
            camera.position.copy(position);
            controls.target.copy(target);
            camera.zoom = zoom;
            camera.updateProjectionMatrix();
            controls.update();
            render();
            updateZoomLabel();
            return;
        }
        animation = {
            fromPosition: camera.position.clone(),
            toPosition: position.clone(),
            fromTarget: controls.target.clone(),
            toTarget: target.clone(),
            fromZoom: camera.zoom,
            toZoom: zoom,
            startedAt: performance.now(),
        };
        if (animationTimer !== null) {
            window.clearTimeout(animationTimer);
        }
        const currentAnimation = animation;
        animationTimer = window.setTimeout(() => finishAnimation(currentAnimation), ANIMATION_DURATION + 80);
        controls.enabled = false;
        setInteractionQuality(true);
        requestFrame();
    };

    const reset = () => animateTo({ position: initialPosition, target: initialTarget, zoom: initialZoom });
    const rotateBy = (angle) => {
        const offset = camera.position.clone().sub(controls.target);
        offset.applyAxisAngle(new THREE.Vector3(0, 1, 0), angle);
        animateTo({ position: controls.target.clone().add(offset), target: controls.target.clone(), zoom: camera.zoom });
    };
    const zoomBy = (amount) => {
        setInteractionQuality(true);
        camera.zoom = THREE.MathUtils.clamp(camera.zoom + amount, MIN_ZOOM, MAX_ZOOM);
        camera.updateProjectionMatrix();
        controls.update();
        render();
        updateZoomLabel();
    };
    const focusOn = (point, zoom = 2.2) => {
        const offset = camera.position.clone().sub(controls.target);
        if (offset.lengthSq() === 0) {
            offset.set(8, 6, 8);
        }
        animateTo({
            position: point.clone().add(offset),
            target: point.clone(),
            zoom: THREE.MathUtils.clamp(Math.max(camera.zoom, zoom), MIN_ZOOM, MAX_ZOOM),
        });
    };
    const onControlChange = () => {
        renderRequested = true;
        updateZoomLabel();
        requestFrame();
    };
    const onKeyDown = (event) => {
        if (isTypingTarget(event.target)) {
            return;
        }
        if (event.key.toLowerCase() === 'q') {
            event.preventDefault();
            rotateBy(Math.PI / 2);
        } else if (event.key.toLowerCase() === 'e') {
            event.preventDefault();
            rotateBy(-Math.PI / 2);
        }
    };

    const onControlStart = () => setInteractionQuality(true);
    const onControlEnd = () => setInteractionQuality(false);
    controls.addEventListener('change', onControlChange);
    controls.addEventListener('start', onControlStart);
    controls.addEventListener('end', onControlEnd);
    document.addEventListener('keydown', onKeyDown);
    document.querySelector('[data-spatial-reset]')?.addEventListener('click', reset);
    document.querySelector('[data-spatial-rotate-left]')?.addEventListener('click', () => rotateBy(Math.PI / 2));
    document.querySelector('[data-spatial-rotate-right]')?.addEventListener('click', () => rotateBy(-Math.PI / 2));
    document.querySelector('[data-spatial-zoom-out]')?.addEventListener('click', () => zoomBy(-ZOOM_STEP));
    document.querySelector('[data-spatial-zoom-in]')?.addEventListener('click', () => zoomBy(ZOOM_STEP));
    updateZoomLabel();

    return {
        controls,
        reset,
        rotateBy,
        zoomBy,
        focusOn,
        dispose: () => {
            if (animationFrame !== null) {
                cancelAnimationFrame(animationFrame);
            }
            if (animationTimer !== null) {
                window.clearTimeout(animationTimer);
            }
            renderRequested = false;
            controls.removeEventListener('change', onControlChange);
            controls.removeEventListener('start', onControlStart);
            controls.removeEventListener('end', onControlEnd);
            document.removeEventListener('keydown', onKeyDown);
            controls.dispose();
        },
    };
};
