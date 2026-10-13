import * as THREE from 'three';
import { SCENE_THEMES, colorToCss } from './themes.js';

export const SPATIAL_COLORS = SCENE_THEMES.light;

const canvasTexture = (draw, size = 256) => {
    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    draw(canvas.getContext('2d'), size);

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    texture.needsUpdate = true;

    return texture;
};

export const createGridTexture = (theme) => canvasTexture((context, size) => {
    context.fillStyle = colorToCss(theme.islandTop);
    context.fillRect(0, 0, size, size);
    context.strokeStyle = colorToCss(theme.gridLine);
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

export const createStripeTexture = (baseColor, stripeColor = '#ffffff') => canvasTexture((context, size) => {
    context.fillStyle = colorToCss(baseColor);
    context.fillRect(0, 0, size, size);
    context.strokeStyle = stripeColor;
    context.globalAlpha = 0.28;
    context.lineWidth = 5;

    for (let offset = -size; offset < size * 2; offset += 22) {
        context.beginPath();
        context.moveTo(offset, 0);
        context.lineTo(offset + size, size);
        context.stroke();
    }
});

const replaceTexture = (material, texture) => {
    material.map?.dispose();
    material.map = texture;
    material.needsUpdate = true;
};

export const createMaterials = (themeName = 'light') => {
    const materials = {
        islandSide: new THREE.MeshBasicMaterial(),
        islandTop: new THREE.MeshBasicMaterial(),
        branchSide: new THREE.MeshBasicMaterial(),
        branchTop: new THREE.MeshBasicMaterial(),
        teamSide: new THREE.MeshBasicMaterial(),
        teamTop: new THREE.MeshBasicMaterial(),
        unassignedSide: new THREE.MeshBasicMaterial(),
        unassignedTop: new THREE.MeshBasicMaterial(),
        road: new THREE.MeshBasicMaterial({ polygonOffset: true, polygonOffsetFactor: -1, polygonOffsetUnits: -1 }),
        roadLine: new THREE.LineBasicMaterial({ transparent: true, opacity: 0.85 }),
        treeTrunk: new THREE.MeshBasicMaterial(),
        treeDark: new THREE.MeshBasicMaterial(),
        treeLight: new THREE.MeshBasicMaterial(),
        themeName,
    };

    materials.applyTheme = (nextThemeName) => {
        const theme = SCENE_THEMES[nextThemeName] ?? SCENE_THEMES.light;
        materials.themeName = SCENE_THEMES[nextThemeName] ? nextThemeName : 'light';
        materials.islandSide.color.setHex(theme.islandSide);
        materials.branchSide.color.setHex(theme.branchSide);
        materials.teamSide.color.setHex(theme.teamSide);
        materials.unassignedSide.color.setHex(theme.unassignedSide);
        materials.unassignedTop.color.setHex(theme.unassignedTop);
        materials.road.color.setHex(theme.road);
        materials.roadLine.color.setHex(theme.roadLine);
        materials.treeTrunk.color.setHex(theme.treeTrunk);
        materials.treeDark.color.setHex(theme.treeDark);
        materials.treeLight.color.setHex(theme.treeLight);
        replaceTexture(materials.islandTop, createGridTexture(theme));
        replaceTexture(materials.branchTop, createStripeTexture(theme.branchTop));
        replaceTexture(materials.teamTop, createStripeTexture(theme.teamTop));
        replaceTexture(materials.unassignedTop, createStripeTexture(theme.unassignedTop, 'rgba(0,0,0,0.12)'));
    };

    materials.applyTheme(themeName);

    return materials;
};
