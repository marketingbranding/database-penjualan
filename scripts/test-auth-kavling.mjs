import assert from 'node:assert/strict';
import { buildPlotOrder, countLitPlots, projectIsometric, TOTAL_PLOTS } from '../resources/js/auth/kavling-grid.js';
import { cascadeNoteCount, frequencyForRank, fullGridTrigger, shouldPlayCountChange } from '../resources/js/auth/login-sound.js';

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

const pentatonic = [0, 2, 4, 7, 9];
const frequencies = Array.from({ length: 10 }, (_, rank) => frequencyForRank(rank));
assert.deepEqual(frequencies.slice(0, 5).map((frequency) => Math.round(12 * Math.log2(frequency / 220)) % 12), pentatonic);
assert.equal(countLitPlots(8, 4), countLitPlots(4, 8));
assert.deepEqual(shouldPlayCountChange(4, 5, -Infinity, 100), { type: 'tok', count: 1 });
assert.deepEqual(shouldPlayCountChange(4, 6, 100, 120), { type: 'none', count: 0 });
assert.deepEqual(shouldPlayCountChange(4, 6, 100, 141), { type: 'cascade', count: 2 });
assert.equal(cascadeNoteCount(20), 6);
assert.deepEqual(fullGridTrigger(48, 49, true), { play: true, armed: false });
assert.deepEqual(fullGridTrigger(49, 49, false), { play: false, armed: false });
assert.deepEqual(fullGridTrigger(49, 20, false), { play: false, armed: true });

console.log(`Auth kavling pure functions passed: ${TOTAL_PLOTS} plots`);
