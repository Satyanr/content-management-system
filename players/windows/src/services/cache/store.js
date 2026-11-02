import { mkdir, readFile, readdir, rename, rm, stat, statfs, open } from 'node:fs/promises';
import { createReadStream } from 'node:fs';
import { createHash } from 'node:crypto';
import path from 'node:path';
import { atomicWrite, readJson } from '../security/storage.js';
const hashPattern = /^[a-f0-9]{64}$/;
export async function digest(file) { const hash = createHash('sha256'); for await (const chunk of createReadStream(file)) hash.update(chunk); return hash.digest('hex'); }
export class ContentStore {
    constructor(root, { maxBytes = 10 * 1024 ** 3 } = {}) { this.root = root; this.maxBytes = maxBytes; }
    blob(hash, partial = false) { if (!hashPattern.test(hash)) throw new Error('cache_hash_invalid'); return path.join(this.root, 'blobs', hash + (partial ? '.part' : '')); }
    package(revision) { if (!hashPattern.test(revision)) throw new Error('cache_revision_invalid'); return path.join(this.root, 'packages', revision + '.json'); }
    async init() { await mkdir(path.join(this.root, 'blobs'), { recursive: true }); await mkdir(path.join(this.root, 'packages'), { recursive: true }); }
    async verified(asset) {
        try { return (await stat(this.blob(asset.checksum.value))).size === asset.size && await digest(this.blob(asset.checksum.value)) === asset.checksum.value; } catch { return false; }
    }
    async complete(manifest) { for (const asset of manifest.media) if (!await this.verified(asset)) return false; return true; }
    async recover(validate) {
        for (const name of ['active', 'previous']) {
            try {
                const pointer = await readJson(path.join(this.root, name + '.json'));
                if (!pointer) continue;
                const manifest = validate(JSON.parse(await readFile(this.package(pointer.revision), 'utf8')));
                if (manifest.revision === pointer.revision && await this.complete(manifest)) {
                    if (name === 'previous') await atomicWrite(path.join(this.root, 'active.json'), JSON.stringify(pointer));
                    return manifest;
                }
            } catch {}
        }
        return null;
    }
    async promote(manifest) {
        if (!await this.complete(manifest)) throw new Error('cache_incomplete');
        await atomicWrite(this.package(manifest.revision), JSON.stringify(manifest));
        const old = await readJson(path.join(this.root, 'active.json'));
        if (old && old.revision !== manifest.revision) await atomicWrite(path.join(this.root, 'previous.json'), JSON.stringify(old));
        // Durable package first; one small atomic rename is the activation commit point.
        await atomicWrite(path.join(this.root, 'active.json'), JSON.stringify({ revision: manifest.revision }));
    }
    async usage() {
        let bytes = 0;
        for (const file of await readdir(path.join(this.root, 'blobs'))) { try { bytes += (await stat(path.join(this.root, 'blobs', file))).size; } catch {} }
        return bytes;
    }
    async reserve(manifest) {
        const assets = [...new Map(manifest.media.map(asset => [asset.checksum.value, asset])).values()];
        const expected = assets.reduce((total, asset) => total + asset.size, 0);
        if (!Number.isSafeInteger(expected) || expected > this.maxBytes) throw new Error('cache_limit_exceeded');
        await this.gc(manifest);
        let missing = 0;
        for (const asset of assets) if (!await this.verified(asset)) missing += asset.size;
        const free = await statfs(this.root);
        if (free.bavail * free.bsize < missing + 256 * 1024 ** 2 || await this.usage() + missing > this.maxBytes) throw new Error('disk_space_insufficient');
    }
    async gc(staged = null) {
        const keep = new Set(staged?.media.map(a => a.checksum.value) ?? []), revisions = new Set();
        for (const name of ['active', 'previous']) {
            const pointer = await readJson(path.join(this.root, name + '.json'));
            if (!hashPattern.test(pointer?.revision ?? '')) continue;
            revisions.add(pointer.revision);
            const saved = await readJson(this.package(pointer.revision));
            for (const asset of saved?.media ?? []) if (hashPattern.test(asset.checksum?.value ?? '')) keep.add(asset.checksum.value);
        }
        for (const file of await readdir(path.join(this.root, 'blobs'))) {
            const match = /^([a-f0-9]{64})(\.part)?$/.exec(file);
            if (match && !keep.has(match[1])) await rm(path.join(this.root, 'blobs', file), { force: true });
        }
        for (const file of await readdir(path.join(this.root, 'packages'))) {
            if (/^[a-f0-9]{64}\.json\.[a-f0-9-]{36}\.tmp$/.test(file)) { await rm(path.join(this.root, 'packages', file), { force: true }); continue; }
            const match = /^([a-f0-9]{64})\.json$/.exec(file);
            if (match && !revisions.has(match[1])) await rm(this.package(match[1]), { force: true });
        }
    }
}
