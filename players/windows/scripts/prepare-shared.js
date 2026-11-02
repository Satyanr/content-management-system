import { mkdir, copyFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
const root = new URL('../../../', import.meta.url);
const target = new URL('../src/playback/vendor/', import.meta.url);
await mkdir(target, { recursive: true });
for (const file of ['studio-playback.js', 'studio-playback-renderer.js', 'studio-geometry.js']) {
    await copyFile(new URL('resources/js/' + file, root), new URL(file, target));
}
await copyFile(new URL('docs/player-api-v1.schema.json', root), new URL('../src/services/api/manifest.schema.json', import.meta.url));
console.log('Prepared unchanged Studio playback modules and Player API v1 schema.');
