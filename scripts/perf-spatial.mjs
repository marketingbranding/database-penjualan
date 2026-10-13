import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const root = process.cwd();
const targetUrl = process.env.SPATIAL_URL ?? 'http://127.0.0.1:8000/admin/spatial-workspace';
const email = process.env.SPATIAL_EMAIL;
const password = process.env.SPATIAL_PASSWORD;
const storageState = process.env.SPATIAL_STORAGE_STATE;
const chromePath = process.env.CHROME_PATH ?? (
    process.platform === 'win32'
        ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
        : undefined
);
const scenarios = ['idle', 'orbit', 'zoom', 'hover', 'drag', 'theme'];
const themes = ['light', 'dark'];
const cpuRates = [1, 4];

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const createTargetUrl = () => {
    const url = new URL(targetUrl);
    url.searchParams.set('mock', '10x50x300');
    url.searchParams.set('perf', '1');

    return url.toString();
};

const getCdpSnapshot = async (client) => {
    const [performanceMetrics, heap] = await Promise.all([
        client.send('Performance.getMetrics'),
        client.send('Runtime.getHeapUsage'),
    ]);
    const metrics = Object.fromEntries(performanceMetrics.metrics.map((metric) => [metric.name, metric.value]));

    return {
        heap: {
            usedSize: heap.usedSize,
            totalSize: heap.totalSize,
        },
        performance: metrics,
    };
};

const authenticate = async (page) => {
    if (! page.url().includes('/login')) {
        return;
    }
    if (! email || ! password) {
        throw new Error('Spatial performance test needs SPATIAL_EMAIL and SPATIAL_PASSWORD, or SPATIAL_STORAGE_STATE.');
    }

    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /log in/i }).click();
    await page.waitForFunction(() => ! window.location.pathname.endsWith('/login'), null, { timeout: 10000 });
};

const loadSpatialPage = async (context, page, theme) => {
    await context.addInitScript((preferredTheme) => {
        window.localStorage.setItem('theme', preferredTheme);
    }, theme);
    await page.goto(createTargetUrl(), { waitUntil: 'domcontentloaded' });
    await authenticate(page);
    if (! page.url().includes('/admin/spatial-workspace')) {
        await page.goto(createTargetUrl(), { waitUntil: 'domcontentloaded' });
    }
    await page.waitForSelector('[data-spatial-canvas]');
    await page.waitForFunction(() => Boolean(window.__spatialDebug?.getSnapshot));
    await page.waitForTimeout(1500);
};

const getSnapshot = async (page, client) => ({
    debug: await page.evaluate(() => window.__spatialDebug.getSnapshot()),
    cdp: await getCdpSnapshot(client),
});

const runScenario = async (page, client, name) => {
    await page.evaluate((scenario) => window.__spatialDebug.reset(scenario), name);
    const initial = await getCdpSnapshot(client);
    const startedAt = performance.now();

    if (name === 'idle') {
        await page.waitForTimeout(5000);
    } else if (name === 'orbit') {
        await page.evaluate(() => window.__spatialDebug.orbit({ durationMs: 10000, turns: 1 }));
    } else if (name === 'zoom') {
        await page.evaluate(() => window.__spatialDebug.zoom(0.5, 2, 5000));
    } else if (name === 'hover') {
        const points = await page.evaluate(() => window.__spatialDebug.getPawnScreenPoints(100));
        for (const point of points) {
            await page.mouse.move(point.x, point.y);
        }
        await wait(500);
    } else if (name === 'drag') {
        const editToggle = page.locator('[data-spatial-edit-toggle]');
        if (await editToggle.count() > 0) {
            await editToggle.click();
        }
        const [pawn] = await page.evaluate(() => window.__spatialDebug.getPawnScreenPoints(1));
        const teams = await page.evaluate(() => window.__spatialDebug.getTeamScreenPoints(2));
        const team = teams[1] ?? teams[0];
        await page.mouse.move(pawn.x, pawn.y);
        await page.mouse.down();
        await page.mouse.move(team.x, team.y, { steps: 12 });
        await page.mouse.up();
        await wait(500);
    } else if (name === 'theme') {
        await page.evaluate(async () => {
            for (let index = 0; index < 20; index += 1) {
                window.OasisTheme.setTheme(index % 2 === 0 ? 'dark' : 'light');
                await new Promise((resolve) => requestAnimationFrame(resolve));
            }
        });
        await wait(500);
    }

    const result = await getSnapshot(page, client);
    result.debug.elapsedWallMs = performance.now() - startedAt;
    result.heapGrowth = result.cdp.heap.usedSize - initial.heap.usedSize;

    return result;
};

const runMatrix = async () => {
    const browser = await chromium.launch({
        executablePath: chromePath,
        headless: true,
        args: ['--disable-dev-shm-usage'],
    });
    const results = [];

    try {
        for (const theme of themes) {
            for (const cpuRate of cpuRates) {
                const context = await browser.newContext({
                    storageState: storageState || undefined,
                    viewport: { width: 1440, height: 900 },
                    colorScheme: theme,
                });
                const page = await context.newPage();
                const client = await context.newCDPSession(page);
                await client.send('Performance.enable');
                await client.send('Emulation.setCPUThrottlingRate', { rate: cpuRate });

                for (const scenario of scenarios) {
                    await loadSpatialPage(context, page, theme);
                    results.push({ theme, cpuRate, scenario, ...(await runScenario(page, client, scenario)) });
                }

                await context.close();
            }
        }
    } finally {
        await browser.close();
    }

    return results;
};

const formatBytes = (value) => `${(value / 1024 / 1024).toFixed(2)} MB`;
const formatMetric = (value) => Number.isFinite(value) ? value.toFixed(2) : 'n/a';

const buildMarkdown = (results) => {
    const lines = [
        '# Spatial Workspace performance baseline',
        '',
        `Generated: ${new Date().toISOString()}`,
        '',
        '> This is a dev-only synthetic baseline for `?mock=10x50x300&perf=1`. Headless Chromium commonly uses software rendering, so absolute GPU numbers are not representative of a production GPU.',
        '',
        '| Theme | CPU | Scenario | FPS | frame p50 | frame p95 | rendered frames/s | calls | triangles | labels | pawns | geometries | textures | heap growth | first frame |',
        '| --- | ---: | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |',
    ];

    results.forEach(({ theme, cpuRate, scenario, debug, heapGrowth }) => {
        const renderer = debug.renderer ?? {};
        lines.push(`| ${theme} | ${cpuRate}x | ${scenario} | ${formatMetric(debug.fps)} | ${formatMetric(debug.frameTimeMs.p50)} ms | ${formatMetric(debug.frameTimeMs.p95)} ms | ${formatMetric(debug.renderedFramesPerSecond)} | ${renderer.calls ?? 'n/a'} | ${renderer.triangles ?? 'n/a'} | ${renderer.labelCount ?? 'n/a'} | ${renderer.pawnCount ?? 'n/a'} | ${renderer.geometries ?? 'n/a'} | ${renderer.textures ?? 'n/a'} | ${formatBytes(heapGrowth)} | ${formatMetric(debug.firstFrameMs)} ms |`);
    });

    lines.push(
        '',
        '## CDP metrics',
        '',
        'Each JSON record includes `Performance.getMetrics` and `Runtime.getHeapUsage` snapshots. The key values retained are `TaskDuration`, `ScriptDuration`, `LayoutDuration`, `RecalcStyleDuration`, `JSHeapUsedSize`, and `Nodes` where Chromium exposes them.',
        '',
        '## Initial profile evidence',
        '',
        '1. **Per-pawn mesh fan-out:** `pawns.js` creates a capsule, head, two eye meshes, and optional crown/flag/badge meshes per user; the synthetic dataset therefore multiplies scene nodes and draw calls with user count.',
        '2. **Shadow map cost:** `scene.js` enables PCF shadows and a 1024×1024 shadow map; most static platforms, trees, and pawn meshes cast/receive shadows.',
        '3. **Full-subtree raycasting:** `selection.js` and `drag-drop.js` call `raycaster.intersectObjects([...pawns.values()], true)` on pointer movement, traversing every pawn subtree.',
        '4. **Label work on every render:** `labels.js` projects every label, rotates every pawn toward the camera, calls CSS2D render, and schedules a layout pass that reads DOM rectangles.',
        '5. **O(n²) label decluttering:** `layoutLabels()` compares each branch/team label against every previously placed label and repeatedly reads layout after writes, which becomes expensive with synthetic branch/team counts.',
    );

    return `${lines.join('\n')}\n`;
};

const main = async () => {
    const results = await runMatrix();
    const outputDirectory = path.join(root, 'docs', 'perf');
    await mkdir(outputDirectory, { recursive: true });
    const stamp = new Date().toISOString().replace(/[:.]/g, '-');
    const jsonPath = path.join(outputDirectory, `spatial-baseline-${stamp}.json`);
    const markdownPath = path.join(outputDirectory, `spatial-baseline-${stamp}.md`);
    await writeFile(jsonPath, `${JSON.stringify({ targetUrl, scenarios, themes, cpuRates, results }, null, 2)}\n`);
    await writeFile(markdownPath, buildMarkdown(results));
    console.log(`Wrote ${jsonPath}`);
    console.log(`Wrote ${markdownPath}`);
};

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
