import path from 'node:path';
import { readFile, stat, realpath } from 'node:fs/promises';
import { createReadStream } from 'node:fs';
import { Readable } from 'node:stream';
import { fileURLToPath } from 'node:url';
const source = fileURLToPath(new URL('../', import.meta.url));
const ui = new Map([
    ['/index.html', ['renderer/index.html', 'text/html']], ['/app.js', ['renderer/app.js', 'text/javascript']], ['/style.css', ['renderer/style.css', 'text/css']],
    ['/playback/metadata.js', ['playback/metadata.js', 'text/javascript']],
    ...['studio-playback.js', 'studio-playback-renderer.js', 'studio-geometry.js'].map(name => ['/playback/vendor/' + name, ['playback/vendor/' + name, 'text/javascript']]),
]);
export const csp = "default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src signage-media:; media-src signage-media:; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-src 'none'; form-action 'none'";
export function mediaHash(url) { const match = /^signage-media:\/\/cache\/([a-f0-9]{64})$/.exec(url); return match?.[1] ?? null; }
export async function uiResponse(request) {
    const url = new URL(request.url), entry = url.host === 'app' && !url.search && !url.hash ? ui.get(url.pathname) : null;
    if (!entry) return new Response('', { status: 404 });
    return new Response(await readFile(path.join(source, entry[0])), { headers: { 'Content-Type': entry[1], 'Content-Security-Policy': csp, 'X-Content-Type-Options': 'nosniff' } });
}
export async function mediaResponse(request, runtime) {
    const hash = mediaHash(request.url), asset = !runtime.blocked && runtime.syncer?.active?.media.find(a => a.checksum.value === hash);
    if (!hash || !asset) return new Response('', { status: 404 });
    const file = runtime.store.blob(hash);
    try {
        if (await realpath(file) !== file || (await stat(file)).size !== asset.size) return new Response('', { status: 404 });
        let start = 0, end = asset.size - 1, status = 200;
        const range = request.headers.get('range');
        if (range) {
            const match = /^bytes=(\d*)-(\d*)$/.exec(range);
            if (!match || (!match[1] && !match[2]) || !asset.size) return new Response('', { status: 416, headers: { 'Content-Range': 'bytes */' + asset.size } });
            if (match[1]) { start = Number(match[1]); if (match[2]) end = Math.min(end, Number(match[2])); }
            else start = Number(match[2]) > 0 ? Math.max(0, asset.size - Number(match[2])) : asset.size;
            if (start > end || start >= asset.size || !Number.isSafeInteger(start)) return new Response('', { status: 416, headers: { 'Content-Range': 'bytes */' + asset.size } });
            status = 206;
        }
        const headers = { 'Content-Type': asset.mimeType, 'Content-Length': String(Math.max(0, end - start + 1)), 'Accept-Ranges': 'bytes', 'Cache-Control': 'no-store', 'Access-Control-Allow-Origin': 'signage://app' };
        if (status === 206) headers['Content-Range'] = `bytes ${start}-${end}/${asset.size}`;
        const stream = request.method === 'HEAD' || asset.size === 0 ? null : Readable.toWeb(createReadStream(file, { start, end }));
        return new Response(stream, { status, headers });
    } catch { return new Response('', { status: 404 }); }
}
