// Compresses the PNGs in docs/images/ and docs/public/ in place: the
// screenshots, and the share card and touch icon.
//
//   npm run images
//
// A 256-colour palette takes a mailbox screenshot to about a third of its size
// with no visible difference: the UI is flat colour and text, which a palette
// holds well. Lossless recompression alone saves nothing on these.
//
// An image that already has a palette is skipped rather than quantised again,
// which would shave a little more detail off on every run. Otherwise a file is
// only replaced when the result is meaningfully smaller.

import { readdir, readFile, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import sharp from 'sharp';

const directories = ['docs/images', 'docs/public'];
const minimumSaving = 0.02;

async function pngsIn(directory) {
    try {
        return (await readdir(directory))
            .filter((name) => name.toLowerCase().endsWith('.png'))
            .map((name) => join(directory, name));
    } catch (error) {
        if (error.code === 'ENOENT') {
            return [];
        }

        throw error;
    }
}

const kb = (bytes) => `${(bytes / 1024).toFixed(1)} KB`;

for (const file of (await Promise.all(directories.map(pngsIn))).flat()) {
    const original = await readFile(file);

    if ((await sharp(original).metadata()).isPalette) {
        console.log(`${file}: ${kb(original.length)}, already compressed`);
        continue;
    }

    const compressed = await sharp(original).png({ palette: true, quality: 90, compressionLevel: 9, effort: 10 }).toBuffer();

    if (compressed.length < original.length * (1 - minimumSaving)) {
        await writeFile(file, compressed);
        console.log(`${file}: ${kb(original.length)} -> ${kb(compressed.length)}`);
    } else {
        console.log(`${file}: ${kb(original.length)}, already compressed`);
    }
}
