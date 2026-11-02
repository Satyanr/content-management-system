import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import { readFileSync } from 'node:fs';
const ajv = new Ajv2020({ strict: false, allErrors: false, validateFormats: true });
addFormats(ajv);
const validate = ajv.compile(JSON.parse(readFileSync(new URL('./manifest.schema.json', import.meta.url))));
const validateV2 = ajv.compile(JSON.parse(readFileSync(new URL('./manifest-v2.schema.json', import.meta.url))));
const hex = /^[a-f0-9]{64}$/;
export function validateManifest(data, publicId) {
    if (!(data?.schemaVersion === 2 ? validateV2(data) : validate(data)) || data.device.publicId !== publicId) throw new Error('manifest_invalid');
    if (data.playlists.length > 500 || data.media.length > 10000 || data.schedulePlan.length > 10000) throw new Error('manifest_limit');
    const media = new Map();
    for (const asset of data.media) {
        if (!Number.isSafeInteger(asset.id) || media.has(asset.id) || asset.downloadUrl !== '/api/device/media/' + asset.id || !asset.available || !Number.isSafeInteger(asset.size) || asset.size < 0 || !hex.test(asset.checksum?.value ?? '')) throw new Error('manifest_media_invalid');
        media.set(asset.id, asset);
    }
    const playlists = new Map();
    const pageIds = new Set(), zoneIds = new Set(), itemIds = new Set();
    const unique = (id, seen) => { if (!Number.isSafeInteger(id) || id < 1 || seen.has(id)) throw new Error('manifest_id_invalid'); seen.add(id); };
    for (const playlist of data.playlists) {
        if (playlists.has(playlist.id) || playlist.pages.length > 1000 || playlist.width > 32768 || playlist.height > 32768 || !/^#[a-fA-F0-9]{6}$/.test(playlist.background)) throw new Error('manifest_layout_invalid');
        playlists.set(playlist.id, playlist);
        for (const page of playlist.pages) {
            unique(page.id, pageIds);
            if (page.zones.length > 1000 || !/^#[a-fA-F0-9]{6}$/.test(page.background)) throw new Error('manifest_layout_invalid');
            for (const zone of page.zones) {
                unique(zone.id, zoneIds);
                if (zone.items.length > 10000 || zone.width < 1 || zone.height < 1) throw new Error('manifest_layout_invalid');
                for (const item of zone.items) {
                    unique(item.id, itemIds);
                    if(data.schemaVersion===2 && (!Number.isSafeInteger(item.completedCount)||item.completedCount<0||!Number.isSafeInteger(item.maxPlays)||item.maxPlays<0))throw new Error('manifest_quota_invalid');
                    if (!item.available || !media.has(item.mediaId) || media.get(item.mediaId).type !== item.type) throw new Error('manifest_reference_invalid');
                    if (item.durationMode === 'auto' ? item.type !== 'video' || item.duration !== null : item.duration === null) throw new Error('manifest_duration_invalid');
                }
            }
        }
    }
    const from = Date.parse(data.planStartUtc), until = Date.parse(data.validUntilUtc), now = Date.parse(data.serverTimeUtc);
    if (!(until > from) || until - from > 168 * 3600000 || now < from || now >= until) throw new Error('manifest_time_invalid');
    let end = from;
    for (const segment of data.schedulePlan) {
        const start = Date.parse(segment.startUtc), next = Date.parse(segment.endUtc);
        if (start !== end || next <= start || next > until || (data.schemaVersion === 2 ? (segment.playlistId !== null && !playlists.has(segment.playlistId)) : (segment.state === 'scheduled' ? !segment.schedule || !playlists.has(segment.schedule.playlistId) : segment.schedule !== null))) throw new Error('manifest_schedule_invalid');
        if(data.schemaVersion===2 && ((segment.state==='playing')!==(segment.playlistId!==null) || (segment.schedule && segment.schedule.playlistId!==segment.playlistId)))throw new Error('manifest_selection_invalid');
        end = next;
    }
    if (end !== until) throw new Error('manifest_schedule_invalid');
    const current = data.schedulePlan.find(s => Date.parse(s.startUtc) <= now && now < Date.parse(s.endUtc));
    if (data.state !== current.state || (data.currentSchedule?.id ?? null) !== (current.schedule?.id ?? null) || data.currentPlaylistId !== (data.schemaVersion === 2 ? current.playlistId : (current.schedule?.playlistId ?? null))) throw new Error('manifest_current_invalid');
    if(data.schemaVersion===2 && (data.fallbackSelection.playlistId!==data.fallbackPlaylistId || (data.fallbackPlaylistId!==null && !playlists.has(data.fallbackPlaylistId))))throw new Error('manifest_fallback_invalid');
    return data;
}
export class ServerClock {
    constructor({ monotonic = () => performance.now(), wall = () => Date.now() } = {}) { Object.assign(this, { monotonic, wall }); this.anchor = null; }
    align(utc, sentAt = this.monotonic()) {
        const now = this.monotonic(), remote = Date.parse(utc);
        if (!Number.isFinite(remote)) throw new Error('server_clock_invalid');
        this.anchor = { utc: remote + Math.max(0, now - sentAt) / 2, mono: now };
    }
    restore(saved) {
        // After reboot no monotonic continuity exists. Fail closed on wall-clock rollback.
        if (!saved || !Number.isFinite(saved.utc) || !Number.isFinite(saved.wall) || this.wall() < saved.wall - 5000) return false;
        this.anchor = { utc: saved.utc + Math.max(0, this.wall() - saved.wall), mono: this.monotonic() }; return true;
    }
    now() { return this.anchor ? this.anchor.utc + this.monotonic() - this.anchor.mono : null; }
    save() { return { utc: this.now(), wall: this.wall() }; }
}
