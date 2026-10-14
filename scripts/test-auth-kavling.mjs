import assert from 'node:assert/strict';
import { buildPlotOrder, countLitPlots, projectIsometric, TOTAL_PLOTS } from '../resources/js/auth/kavling-grid.js';

assert.equal(countLitPlots(0, 0), 0);
assert.equal(countLitPlots(10, 8), 18);
assert.equal(countLitPlots(40, 20), TOTAL_PLOTS);
assert.equal(countLitPlots(-4, 2), 2);

const order = buildPlotOrder();
assert.equal(order.length, TOTAL_PLOTS);
assert.deepEqual(order.slice(0, 4).map(({ x, y }) => [x, y]), [[0, 0], [0, 1], [1, 0], [0, 2]]);
assert.equal(order[0].index, 0);
assert.equal(new Set(order.map(({ index }) => index)).size, TOTAL_PLOTS);

const point = projectIsometric(2, 1, 0, 46, 100, 50);
assert.deepEqual(point, { x: 146, y: 119 });

console.log(`Auth kavling pure functions passed: ${TOTAL_PLOTS} plots`);
