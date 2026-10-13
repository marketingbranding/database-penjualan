import { access, readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const root = process.cwd();
const failures = [];

const filePath = (relativePath) => path.join(root, relativePath);
const collectFiles = async (directory) => {
    const entries = await readdir(filePath(directory), { withFileTypes: true });
    const files = [];
    for (const entry of entries) {
        const relativePath = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await collectFiles(relativePath));
        } else {
            files.push(relativePath);
        }
    }
    return files;
};
const expectFile = async (relativePath) => {
    try {
        await access(filePath(relativePath));
    } catch {
        failures.push(`missing ${relativePath}`);
    }
};

const expectPngSize = async (relativePath, width, height) => {
    try {
        const metadata = await sharp(filePath(relativePath)).metadata();
        if (metadata.width !== width || metadata.height !== height) {
            failures.push(`${relativePath} is ${metadata.width}x${metadata.height}, expected ${width}x${height}`);
        }
    } catch (error) {
        failures.push(`${relativePath} could not be read: ${error.message}`);
    }
};

const readIcoSizes = async (relativePath) => {
    try {
        const buffer = await readFile(filePath(relativePath));
        const count = buffer.readUInt16LE(4);
        const sizes = [];
        for (let index = 0; index < count; index += 1) {
            const offset = 6 + index * 16;
            sizes.push(buffer[offset] || 256);
        }
        return sizes;
    } catch (error) {
        failures.push(`${relativePath} could not be read: ${error.message}`);
        return [];
    }
};

const luminance = ([red, green, blue]) => [red, green, blue]
    .map((value) => value / 255)
    .map((value) => value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4)
    .reduce((total, value, index) => total + value * [0.2126, 0.7152, 0.0722][index], 0);

const contrast = (foreground, background) => {
    const foregroundLuminance = luminance(foreground);
    const backgroundLuminance = luminance(background);
    const light = Math.max(foregroundLuminance, backgroundLuminance);
    const dark = Math.min(foregroundLuminance, backgroundLuminance);

    return (light + 0.05) / (dark + 0.05);
};

const verify = async () => {
    const expectedFiles = [
        'public/brand/oasis-next-logo.svg',
        'public/brand/oasis-next-logo-dark.svg',
        'public/brand/oasis-next-mark.svg',
        'public/brand/oasis-next-mark-dark.svg',
        'public/brand/email-mark.png',
        'public/favicon.svg',
        'public/favicon.ico',
        'public/favicon-16x16.png',
        'public/favicon-32x32.png',
        'public/apple-touch-icon.png',
        'public/android-chrome-192x192.png',
        'public/android-chrome-512x512.png',
        'public/maskable-512x512.png',
        'public/og-image.png',
        'public/site.webmanifest',
    ];

    await Promise.all(expectedFiles.map(expectFile));
    await expectPngSize('public/brand/email-mark.png', 96, 96);
    await expectPngSize('public/favicon-16x16.png', 16, 16);
    await expectPngSize('public/favicon-32x32.png', 32, 32);
    await expectPngSize('public/apple-touch-icon.png', 180, 180);
    await expectPngSize('public/android-chrome-192x192.png', 192, 192);
    await expectPngSize('public/android-chrome-512x512.png', 512, 512);
    await expectPngSize('public/maskable-512x512.png', 512, 512);
    await expectPngSize('public/og-image.png', 1200, 630);

    const icoSizes = await readIcoSizes('public/favicon.ico');
    [16, 32, 48].forEach((size) => {
        if (! icoSizes.includes(size)) {
            failures.push(`public/favicon.ico does not contain ${size}px`);
        }
    });

    for (const filename of ['oasis-next-logo.svg', 'oasis-next-logo-dark.svg', 'oasis-next-mark.svg', 'oasis-next-mark-dark.svg']) {
        const relativePath = `public/brand/${filename}`;
        const contents = await readFile(filePath(relativePath), 'utf8');
        if (contents.includes('<metadata') || contents.includes('c2pa')) {
            failures.push(`${relativePath} still contains metadata`);
        }
        if (Buffer.byteLength(contents) >= 10 * 1024) {
            failures.push(`${relativePath} is larger than 10KB`);
        }
    }

    try {
        const manifest = JSON.parse(await readFile(filePath('public/site.webmanifest'), 'utf8'));
        if (manifest.name !== 'Oasis Next' || manifest.short_name !== 'Oasis' || manifest.theme_color !== '#9ccc4e' || manifest.background_color !== '#ecece9') {
            failures.push('site.webmanifest has incorrect brand metadata');
        }
        for (const icon of manifest.icons ?? []) {
            await expectFile(`public/${icon.src.replace(/^\//, '')}`);
        }
    } catch (error) {
        failures.push(`site.webmanifest is invalid: ${error.message}`);
    }

    const uiFiles = [
        ...await collectFiles('resources/views'),
        'public/offline.html',
        'public/site.webmanifest',
        'public/manifest.webmanifest',
    ];
    for (const relativePath of uiFiles) {
        const contents = await readFile(filePath(relativePath), 'utf8');
        if (contents.match(/marison/i)) {
            failures.push(`${relativePath} contains a visible legacy brand name`);
        }
    }

    const lightSurface = [255, 255, 255];
    const darkSurface = [24, 29, 22];
    if (contrast([29, 34, 25], lightSurface) < 4.5 || contrast([236, 239, 230], darkSurface) < 4.5) {
        failures.push('Oasis wordmark contrast is below 4.5:1');
    }
    if (contrast([95, 154, 42], lightSurface) < 3 || contrast([156, 204, 78], darkSurface) < 3) {
        failures.push('Next wordmark contrast is below 3:1');
    }

    if (failures.length > 0) {
        failures.forEach((failure) => console.error(`FAIL ${failure}`));
        process.exitCode = 1;
        return;
    }

    console.log('PASS brand assets, dimensions, manifest, SVG metadata, UI brand scan, and contrast checks');
};

verify();
