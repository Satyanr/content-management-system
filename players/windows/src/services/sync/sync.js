import { open, stat, rm, rename } from 'node:fs/promises';
import { digest } from '../cache/store.js';
import { validateManifest } from '../api/manifest.js';
import { ApiError } from '../api/client.js';

export async function download(api, store, asset, revision, signal) {
    if (await store.verified(asset)) return;
    const hash = asset.checksum.value, partial = store.blob(hash, true), final = store.blob(hash);
    let offset = await stat(partial).then(s => s.size).catch(() => 0);
    if (offset > asset.size) { await rm(partial, { force: true }); offset = 0; }
    if (offset === asset.size && await digest(partial).catch(() => '') === hash) { await rename(partial, final); return; }
    if (offset === asset.size) { await rm(partial, { force: true }); offset = 0; }
    const headers = { 'X-Content-Revision': revision };
    if (offset) Object.assign(headers, { Range: 'bytes=' + offset + '-', 'If-Range': '"' + hash + '"' });
    const response = await api.request(asset.downloadUrl, { headers, signal });
    const expectedEtag = '"' + hash + '"';
    if (![200, 206].includes(response.status) || response.headers.get('etag') !== expectedEtag || response.headers.get('x-content-revision') !== revision) { await response.body?.cancel(); throw new ApiError('download_version_invalid'); }
    if (response.status === 206 && (!offset || response.headers.get('content-range') !== `bytes ${offset}-${asset.size - 1}/${asset.size}`)) { await response.body?.cancel(); throw new ApiError('download_range_invalid'); }
    if (response.status === 200) offset = 0;
    if (response.headers.has('content-length') && Number(response.headers.get('content-length')) !== asset.size - offset) { await response.body?.cancel(); throw new ApiError('download_size_invalid'); }
    const file = await open(partial, offset ? 'a' : 'w', 0o600);
    let written = offset;
    try {
        for await (const chunk of response.body) {
            if (signal?.aborted) throw new ApiError('cancelled');
            if (written + chunk.length > asset.size) throw new ApiError('download_size_invalid');
            let cursor = 0;
            while (cursor < chunk.length) { const result = await file.write(chunk, cursor, chunk.length - cursor); cursor += result.bytesWritten; }
            written += chunk.length;
        }
        await file.sync();
    } finally { await file.close(); }
    if (written !== asset.size) throw new ApiError('download_interrupted');
    if (await digest(partial) !== hash) { await rm(partial, { force: true }); throw new ApiError('download_checksum_invalid'); }
    await rename(partial, final);
}
export class SyncManager {
    constructor({ api, store, publicId, clock, onPackage = () => {}, onState = () => {} }) { Object.assign(this, { api, store, publicId, clock, onPackage, onState }); this.active = null; this.etag = null; this.busy = false; }
    cancel() { this.controller?.abort(); }
    async sync() {
        if (this.busy) return;
        this.busy = true; this.controller = new AbortController(); const signal = this.controller.signal;
        try {
            const sent = this.clock.monotonic();
            const result = await this.api.json('/api/device/manifest', { headers: this.etag ? { 'If-None-Match': this.etag } : {}, signal });
            this.clock.align(result.response.headers.get('x-server-time-utc') ?? result.data?.serverTimeUtc, sent);
            if (result.response.status === 304) { if (!this.active) { this.etag = null; throw new ApiError('manifest_cache_missing'); } return; }
            const manifest = validateManifest(result.data, this.publicId);
            await this.store.reserve(manifest);
            const assets = [...new Map(manifest.media.map(asset => [asset.checksum.value, asset])).values()];
            let index = 0;
            const workers = Array.from({ length: 2 }, async () => {
                while (index < assets.length && !signal.aborted) await download(this.api, this.store, assets[index++], manifest.revision, signal);
            });
            const results = await Promise.allSettled(workers.map(p => p.catch(error => { this.controller.abort(); throw error; })));
            const failure = results.find(r => r.status === 'rejected'); if (failure) throw failure.reason;
            const confirmSent = this.clock.monotonic();
            const confirmed = await this.api.json('/api/device/manifest', { signal });
            const final = validateManifest(confirmed.data, this.publicId);
            this.clock.align(final.serverTimeUtc, confirmSent);
            if (final.revision !== manifest.revision) throw new ApiError('sync_revision_changed', 409);
            if (signal.aborted) throw new ApiError('cancelled');
            await this.store.promote(final);
            this.active = final; this.etag = confirmed.response.headers.get('etag');
            await this.onPackage(final); await this.store.gc();
        } finally { this.busy = false; }
    }
}
