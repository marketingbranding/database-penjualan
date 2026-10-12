import * as THREE from 'three';

export const SPATIAL_COLORS = {
    islandTop: 0xf4f6ee,
    islandSide: 0xd6dbc6,
    islandShadowSide: 0xc3c9b0,
    branchTop: 0xf4a65c,
    branchSide: 0xd3802d,
    teamTop: 0x9ccc4e,
    teamSide: 0x6fa030,
    road: 0xe6e4d8,
    roadLine: 0xffffff,
    gridLine: 0xe3e6d8,
    treeTrunk: 0x8f6a3e,
    treeDark: 0x6fa030,
    treeLight: 0x9ccc4e,
};

const canvasTexture = (draw, size = 256) => {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    draw(canvas.getContext('2d'), size);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 8;
    texture.needsUpdate = true;

    return texture;
};

export const createGridTexture = () => canvasTexture((context, size) => {
    context.fillStyle = '#f4f6ee';
    context.fillRect(0, 0, size, size);
    context.strokeStyle = '#e3e6d8';
    context.lineWidth = 1;

    for (let index = 0; index <= size; index += size / 8) {
        context.beginPath();
        context.moveTo(index, 0);
        context.lineTo(index, size);
        context.stroke();
        context.beginPath();
        context.moveTo(0, index);
        context.lineTo(size, index);
        context.stroke();
    }
});

export const createStripeTexture = (baseColor) => canvasTexture((context, size) => {
    context.fillStyle = baseColor;
    context.fillRect(0, 0, size, size);
    context.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    context.lineWidth = 5;

    for (let offset = -size; offset < size * 2; offset += 22) {
        context.beginPath();
        context.moveTo(offset, 0);
        context.lineTo(offset + size, size);
        context.stroke();
    }
});

export const createMaterials = () => ({
    islandSide: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.islandSide, roughness: 0.85 }),
    islandTop: new THREE.MeshStandardMaterial({ map: createGridTexture(), roughness: 0.9 }),
    branchSide: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.branchSide, roughness: 0.85 }),
    branchTop: new THREE.MeshStandardMaterial({ map: createStripeTexture('#f4a65c'), roughness: 0.85 }),
    teamSide: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.teamSide, roughness: 0.85 }),
    teamTop: new THREE.MeshStandardMaterial({ map: createStripeTexture('#9ccc4e'), roughness: 0.85 }),
    road: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.road, roughness: 0.9 }),
    treeTrunk: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.treeTrunk, roughness: 0.95 }),
    treeDark: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.treeDark, roughness: 0.85 }),
    treeLight: new THREE.MeshStandardMaterial({ color: SPATIAL_COLORS.treeLight, roughness: 0.85 }),
});
