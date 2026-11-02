import { MetadataPool } from './vendor/studio-playback-renderer.js';
// Only unknown Auto durations need a probe. Limit simultaneous local decoder probes to two.
export class PlayerMetadataQueue {
    constructor({ onResult, factory = options => new MetadataPool(options) }) { Object.assign(this, { onResult, factory }); this.pending = []; this.active = new Set(); this.dead = false; }
    load(manifest) {
        const seen = new Set();
        for (const page of manifest.pages) for (const zone of page.zones) for (const item of zone.items) {
            if (item.type === 'video' && item.available && !item.duration && !item.knownDuration && !seen.has(item.src)) { seen.add(item.src); this.pending.push(item); }
        }
        this.drain();
    }
    drain() {
        while (!this.dead && this.active.size < 2 && this.pending.length) {
            const item = this.pending.shift();
            const pool = this.factory({ onResult: (src, info) => {
                if (this.dead) return;
                this.onResult(src, info);
                if (info.status !== 'loading') queueMicrotask(() => { pool.destroy(); this.active.delete(pool); this.drain(); });
            } });
            this.active.add(pool); pool.load({ pages: [{ zones: [{ items: [item] }] }] });
        }
    }
    destroy() { this.dead = true; this.pending = []; for (const pool of this.active) pool.destroy(); this.active.clear(); }
}
