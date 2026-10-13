import * as THREE from 'three';

const SAMPLE_WINDOW_MS = 250;

const percentile = (values, ratio) => {
    if (values.length === 0) {
        return 0;
    }

    const sorted = [...values].sort((left, right) => left - right);
    return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * ratio))];
};

const formatNumber = (value) => Number.isFinite(value) ? Math.round(value).toLocaleString('en-US') : '0';

const createHud = (root) => {
    const hud = document.createElement('aside');
    hud.className = 'spatial-perf-hud';
    hud.setAttribute('aria-label', 'Spatial performance development metrics');
    hud.innerHTML = '<strong>DEV PERF</strong><pre></pre>';
    hud.style.cssText = 'position:absolute;top:.75rem;right:.75rem;z-index:30;min-width:19rem;border:1px solid rgb(var(--c-accent-dark)/55%);border-radius:.75rem;background:rgb(var(--c-inverse)/92%);color:rgb(var(--c-inverse-text));font:600 .65rem/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;padding:.65rem .75rem;pointer-events:none;';
    hud.querySelector('strong').style.cssText = 'color:rgb(var(--c-accent));display:block;font:700 .65rem/1.2 Poppins,ui-sans-serif,system-ui,sans-serif;margin-bottom:.35rem;';
    hud.querySelector('pre').style.cssText = 'margin:0;white-space:pre-wrap;';
    root.append(hud);

    return { hud, output: hud.querySelector('pre') };
};

const screenPoint = (object, camera, canvas) => {
    const world = new THREE.Vector3();
    object.getWorldPosition(world);
    world.project(camera);
    const rect = canvas.getBoundingClientRect();

    return {
        x: rect.left + (world.x + 1) * rect.width / 2,
        y: rect.top + (-world.y + 1) * rect.height / 2,
    };
};

export const createSpatialPerformance = (root) => {
    const { hud, output } = createHud(root);
    const startedAt = performance.now();
    let sceneDetail = null;
    let firstFrameMs = null;
    let renderCount = 0;
    let intervals = [];
    let lastFrameAt = null;
    let latestInfo = null;
    let rafId = null;
    let hudTimer = null;
    let scenarioStartedAt = startedAt;

    const state = {
        name: 'idle',
        frameTimes: [],
        frameSamples: 0,
    };

    const frameSampler = (timestamp) => {
        if (lastFrameAt !== null) {
            const interval = timestamp - lastFrameAt;
            intervals.push(interval);
            state.frameTimes.push(interval);
        }
        lastFrameAt = timestamp;
        state.frameSamples += 1;
        rafId = requestAnimationFrame(frameSampler);
    };

    const reset = (name = 'scenario') => {
        state.name = name;
        state.frameTimes = [];
        state.frameSamples = 0;
        intervals = [];
        renderCount = 0;
        scenarioStartedAt = performance.now();
    };

    const onRender = ({ renderer, scene, camera, pawns, labelCount }) => {
        renderCount += 1;
        if (firstFrameMs === null) {
            firstFrameMs = performance.now() - startedAt;
        }
        latestInfo = {
            calls: renderer.info.render.calls,
            triangles: renderer.info.render.triangles,
            geometries: renderer.info.memory.geometries,
            textures: renderer.info.memory.textures,
            materials: new Set(),
            labelCount,
            pawnCount: pawns.size,
        };
        scene.traverse((object) => {
            const materials = Array.isArray(object.material) ? object.material : [object.material];
            materials.filter(Boolean).forEach((material) => latestInfo.materials.add(material));
        });
        latestInfo.materials = latestInfo.materials.size;
    };

    const getSnapshot = () => {
        const frameTimeValues = state.frameTimes.length > 0 ? state.frameTimes : intervals;
        const p50 = percentile(frameTimeValues, 0.5);
        const p95 = percentile(frameTimeValues, 0.95);
        const elapsed = Math.max(1, performance.now() - scenarioStartedAt);
        const average = frameTimeValues.length > 0
            ? frameTimeValues.reduce((sum, value) => sum + value, 0) / frameTimeValues.length
            : 0;
        const fps = average > 0 ? 1000 / average : 0;

        return {
            scenario: state.name,
            elapsedMs: elapsed,
            fps,
            frameTimeMs: { average, p50, p95 },
            sampledFrames: state.frameSamples,
            renderedFrames: renderCount,
            renderedFramesPerSecond: renderCount * 1000 / elapsed,
            firstFrameMs,
            renderer: latestInfo ? { ...latestInfo, materials: latestInfo.materials } : null,
        };
    };

    const renderHud = () => {
        const snapshot = getSnapshot();
        const info = snapshot.renderer ?? {};
        output.textContent = [
            `scenario ${snapshot.scenario}`,
            `FPS ${snapshot.fps.toFixed(1)} · frame avg/p50/p95 ${snapshot.frameTimeMs.average.toFixed(1)}/${snapshot.frameTimeMs.p50.toFixed(1)}/${snapshot.frameTimeMs.p95.toFixed(1)} ms`,
            `renders ${formatNumber(snapshot.renderedFrames)} · calls ${formatNumber(info.calls)} · tris ${formatNumber(info.triangles)}`,
            `memory geo/tex/mat ${formatNumber(info.geometries)}/${formatNumber(info.textures)}/${formatNumber(info.materials)}`,
            `DOM labels ${formatNumber(info.labelCount)} · pawns ${formatNumber(info.pawnCount)} · quality standard`,
        ].join('\n');
    };

    const waitForScene = () => new Promise((resolve) => {
        if (sceneDetail) {
            resolve(sceneDetail);
            return;
        }
        root.addEventListener('spatial:scene-ready', (event) => resolve(event.detail), { once: true });
    });

    const animateCamera = async ({ durationMs = 1000, update }) => {
        const detail = await waitForScene();
        const started = performance.now();
        detail.controls.enabled = false;

        await new Promise((resolve) => {
            const tick = (timestamp) => {
                const progress = Math.min(1, (timestamp - started) / durationMs);
                update(progress, detail);
                detail.render();
                if (progress < 1) {
                    requestAnimationFrame(tick);
                } else {
                    detail.controls.enabled = true;
                    resolve();
                }
            };
            requestAnimationFrame(tick);
        });
    };

    const api = {
        getSnapshot,
        reset,
        getScene: () => sceneDetail,
        getPawnScreenPoints: async (limit = 100) => {
            const detail = await waitForScene();
            return [...detail.pawns.values()].slice(0, limit).map((pawn) => ({
                userId: pawn.userData.userId,
                ...screenPoint(pawn, detail.camera, detail.canvas),
            }));
        },
        getTeamScreenPoints: async (limit = 1) => {
            const detail = await waitForScene();
            return detail.teamTargets.slice(0, limit).map(({ team }) => {
                const marker = new THREE.Object3D();
                marker.position.set(team.x, 0.75, team.z);

                return {
                    teamId: team.id,
                    ...screenPoint(marker, detail.camera, detail.canvas),
                };
            });
        },
        orbit: (options = {}) => {
            const turns = options.turns ?? 1;
            return animateCamera({
                durationMs: options.durationMs ?? 10000,
                update: (progress, detail) => {
                    const offset = detail.initialCameraOffset.clone().applyAxisAngle(new THREE.Vector3(0, 1, 0), Math.PI * 2 * turns * progress);
                    detail.camera.position.copy(detail.controls.target).add(offset);
                    detail.camera.lookAt(detail.controls.target);
                    detail.camera.updateProjectionMatrix();
                },
            });
        },
        zoom: (from = 0.5, to = 2, durationMs = 5000) => animateCamera({
            durationMs,
            update: (progress, detail) => {
                detail.camera.zoom = from + (to - from) * progress;
                detail.camera.updateProjectionMatrix();
            },
        }),
    };

    const onSceneReady = (event) => {
        sceneDetail = event.detail;
        sceneDetail.initialCameraOffset = sceneDetail.camera.position.clone().sub(sceneDetail.controls.target);
    };

    root.addEventListener('spatial:scene-ready', onSceneReady);
    window.__spatialPerf = { onRender };
    window.__spatialDebug = api;
    rafId = requestAnimationFrame(frameSampler);
    hudTimer = window.setInterval(renderHud, SAMPLE_WINDOW_MS);
    renderHud();

    return {
        api,
        dispose: () => {
            root.removeEventListener('spatial:scene-ready', onSceneReady);
            if (rafId !== null) {
                cancelAnimationFrame(rafId);
            }
            if (hudTimer !== null) {
                clearInterval(hudTimer);
            }
            if (window.__spatialPerf?.onRender === onRender) {
                delete window.__spatialPerf;
            }
            delete window.__spatialDebug;
            hud.remove();
        },
    };
};
