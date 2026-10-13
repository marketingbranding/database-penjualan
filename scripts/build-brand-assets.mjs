import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const root = process.cwd();
const sourceDirectory = path.join(root, 'resources', 'brand');
const publicDirectory = path.join(root, 'public');
const publicBrandDirectory = path.join(publicDirectory, 'brand');

const ensureDirectories = async () => {
    await mkdir(sourceDirectory, { recursive: true });
    await mkdir(publicBrandDirectory, { recursive: true });
};

const cleanSvg = (svg) => svg
    .replace(/\s+xmlns:c2pa="[^"]*"/g, '')
    .replace(/<metadata[\s\S]*?<\/metadata>/gi, '')
    .replace(/\s{2,}/g, ' ')
    .trim();

const readCleanSvg = async (filename) => cleanSvg(await readFile(path.join(sourceDirectory, filename), 'utf8'));

const renderPng = async (svg, width, height = width) => sharp(Buffer.from(svg)).resize(width, height).png().toBuffer();

const renderSolidCanvas = async (width, height, background, input, inputSize) => sharp({
    create: {
        width,
        height,
        channels: 4,
        background,
    },
}).composite([{ input: await renderPng(input, inputSize), gravity: 'center' }]).png().toBuffer();

const createFaviconSvg = () => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"><rect width="120" height="120" rx="34" fill="#9ccc4e"/><circle cx="60" cy="60" r="16" fill="#fff6dc"/><circle cx="60" cy="58" r="9" fill="#f4a65c"/><circle cx="60" cy="60" r="25" fill="none" stroke="#1d2219" stroke-width="16"/></svg>`;

const createOgTagline = () => `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="100"><style>text{font-family:Poppins,Arial,sans-serif;font-size: thirtypx;font-weight:500;fill:#687064}</style><text x="600" y="62" text-anchor="middle" font-size="30">Workspace operasional</text></svg>`.replace('font-size: thirtypx', 'font-size: 30px');

const createIco = (pngBuffers) => {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(pngBuffers.length, 4);

    const directory = Buffer.alloc(pngBuffers.length * 16);
    let offset = header.length + directory.length;

    pngBuffers.forEach(({ size, buffer }, index) => {
        const entryOffset = index * 16;
        directory.writeUInt8(size === 256 ? 0 : size, entryOffset);
        directory.writeUInt8(size === 256 ? 0 : size, entryOffset + 1);
        directory.writeUInt8(0, entryOffset + 2);
        directory.writeUInt8(0, entryOffset + 3);
        directory.writeUInt16LE(1, entryOffset + 4);
        directory.writeUInt16LE(32, entryOffset + 6);
        directory.writeUInt32LE(buffer.length, entryOffset + 8);
        directory.writeUInt32LE(offset, entryOffset + 12);
        offset += buffer.length;
    });

    return Buffer.concat([header, directory, ...pngBuffers.map(({ buffer }) => buffer)]);
};

const createWebManifest = () => JSON.stringify({
    name: 'Oasis Next',
    short_name: 'Oasis',
    description: 'Workspace operasional',
    id: './',
    start_url: './',
    scope: './',
    display: 'standalone',
    theme_color: '#9ccc4e',
    background_color: '#ecece9',
    lang: 'id',
    dir: 'ltr',
    icons: [
        { src: 'android-chrome-192x192.png', sizes: '192x192', type: 'image/png' },
        { src: 'android-chrome-512x512.png', sizes: '512x512', type: 'image/png' },
        { src: 'maskable-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
    ],
}, null, 2) + '\n';

const build = async () => {
    await ensureDirectories();

    const mark = await readCleanSvg('oasis-next-mark.svg');
    const logo = await readCleanSvg('oasis-next-logo.svg');
    const sourceFiles = [
        'oasis-next-mark.svg',
        'oasis-next-mark-dark.svg',
        'oasis-next-logo.svg',
        'oasis-next-logo-dark.svg',
    ];

    await Promise.all(sourceFiles.map(async (filename) => {
        const svg = await readCleanSvg(filename);
        await writeFile(path.join(sourceDirectory, filename), svg);
        await writeFile(path.join(publicBrandDirectory, filename), svg);
    }));

    const faviconSvg = createFaviconSvg();
    await writeFile(path.join(publicDirectory, 'favicon.svg'), faviconSvg);

    const faviconPngs = await Promise.all([16, 32, 48].map(async (size) => ({
        size,
        buffer: await renderPng(faviconSvg, size),
    })));
    await writeFile(path.join(publicDirectory, 'favicon-16x16.png'), faviconPngs[0].buffer);
    await writeFile(path.join(publicDirectory, 'favicon-32x32.png'), faviconPngs[1].buffer);
    await writeFile(path.join(publicDirectory, 'favicon.ico'), createIco(faviconPngs));

    await writeFile(path.join(publicDirectory, 'apple-touch-icon.png'), await renderSolidCanvas(180, 180, '#9ccc4e', mark, 137));
    await writeFile(path.join(publicDirectory, 'android-chrome-192x192.png'), await renderSolidCanvas(192, 192, '#9ccc4e', mark, 115));
    await writeFile(path.join(publicDirectory, 'android-chrome-512x512.png'), await renderSolidCanvas(512, 512, '#9ccc4e', mark, 307));
    await writeFile(path.join(publicDirectory, 'maskable-512x512.png'), await renderSolidCanvas(512, 512, '#9ccc4e', mark, 307));
    await writeFile(path.join(publicBrandDirectory, 'email-mark.png'), await renderPng(mark, 96));

    const logoPng = await renderPng(logo, 540, 120);
    const ogImage = await sharp({
        create: {
            width: 1200,
            height: 630,
            channels: 4,
            background: '#ecece9',
        },
    }).composite([
        { input: logoPng, left: 330, top: 190 },
        { input: Buffer.from(createOgTagline()), left: 0, top: 355 },
    ]).png().toBuffer();
    await writeFile(path.join(publicDirectory, 'og-image.png'), ogImage);

    const manifest = createWebManifest();
    await writeFile(path.join(publicDirectory, 'site.webmanifest'), manifest);
    await writeFile(path.join(publicDirectory, 'manifest.webmanifest'), manifest);
};

build().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
