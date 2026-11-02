import path from 'node:path';
import { rm } from 'node:fs/promises';
import { CredentialVault, atomicWrite, readJson, OperatorLock } from '../services/security/storage.js';
import { SafeLogger } from '../services/security/logger.js';
import { DeviceApi, backoff } from '../services/api/client.js';
import { ServerClock, validateManifest } from '../services/api/manifest.js';
import { ContentStore } from '../services/cache/store.js';
import { SyncManager } from '../services/sync/sync.js';
import { segmentAt, playbackKey, adaptPlaylist } from '../playback/adapter.js';

export class PlayerRuntime {
    constructor({ root, safeStorage, version, allowLocalHttp = false, display = () => ({ width: 1920, height: 1080 }), notify = () => {} }) {
        Object.assign(this, { root, version, allowLocalHttp, display, notify });
        this.vault = new CredentialVault(root, safeStorage); this.store = new ContentStore(path.join(root, 'content')); this.clock = new ServerClock(); this.logger = new SafeLogger(path.join(root, 'logs'));
        this.state = { online: false, lastError: null, lastSync: null, cacheBytes: 0 }; this.settings = { kiosk: false, alwaysOnTop: false, autoStart: false, displayId: null, muted: true, volume: 0.5 };
        this.timers = new Map(); this.stopped = false; this.blocked = false;
    }
    async init() {
        await this.store.init();
        const saved = await readJson(path.join(this.root, 'settings.json'), {});
        for (const name of ['kiosk', 'alwaysOnTop', 'autoStart', 'muted']) if (typeof saved[name] === 'boolean') this.settings[name] = saved[name];
        if (Number.isSafeInteger(saved.displayId)) this.settings.displayId = saved.displayId;
        if (Number.isFinite(saved.volume) && saved.volume >= 0 && saved.volume <= 1) this.settings.volume = saved.volume;
        const pin = saved.pin && /^[a-f0-9]{64}$/.test(saved.pin.hash ?? '') && /^[a-f0-9]{32}$/.test(saved.pin.salt ?? '') ? saved.pin : null;
        this.lock = new OperatorLock(pin);
        const metadata = await readJson(path.join(this.root, 'sync-metadata.json'));
        if (typeof metadata?.lastSync === 'string' && Number.isFinite(Date.parse(metadata.lastSync))) this.state.lastSync = metadata.lastSync;
        this.state.cacheBytes = await this.store.usage();
        this.blocked = !!await readJson(path.join(this.root, 'blocked.json'));
        this.clock.restore(await readJson(path.join(this.root, 'clock.json')));
        try { this.identity = await this.vault.load(); } catch (error) { this.error(error); }
        if (this.identity && !this.blocked) {
            try {
                this.configure();
                this.syncer.active = await this.store.recover(m => validateManifest(m, this.identity.publicId));
                this.schedule('sync', () => this.synchronize(), 50); this.schedule('heartbeat', () => this.heartbeat(), 100);
            } catch (error) { this.error(error); }
        }
    }
    configure() {
        const api = new DeviceApi(this.identity.origin, { token: this.identity.token, allowLocalHttp: this.allowLocalHttp, onUnauthorized: code => { if (this.api === api) this.revoke(code); } });
        this.api = api;
        this.syncer = new SyncManager({ api: this.api, store: this.store, publicId: this.identity.publicId, clock: this.clock, onPackage: () => this.notify() });
    }
    schedule(name, task, delay) {
        clearTimeout(this.timers.get(name));
        if (!this.stopped && !this.blocked) this.timers.set(name, setTimeout(() => { this.timers.delete(name); void task(); }, delay));
    }
    error(error) { const code = /^[a-z_0-9]{1,80}$/.test(error.code ?? error.message) ? (error.code ?? error.message) : 'unexpected_error'; this.state.lastError = code; this.logger.log(code); }
    revoke(code) {
        this.blocked = true; this.api.token = null; this.syncer?.cancel();
        for (const timer of this.timers.values()) clearTimeout(timer); this.timers.clear();
        this.state.online = false; this.state.lastError = code; this.logger.log(code);
        void atomicWrite(path.join(this.root, 'blocked.json'), JSON.stringify({ blocked: true })).catch(() => {}); this.notify();
    }
    async activate({ origin, code }) {
        if (this.activating) throw new Error('activation_busy');
        this.activating = true;
        try {
        if (this.identity && !this.lock.unlocked) throw new Error('operator_locked');
        await this.vault.available();
        const api = new DeviceApi(origin, { allowLocalHttp: this.allowLocalHttp });
        const { data } = await api.json('/api/device/activate', { method: 'POST', authenticated: false, body: { activation_code: code, platform: 'windows', player_version: this.version, screen_width: this.display().width, screen_height: this.display().height } });
        if (!/^dvc_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(data.credential ?? '') || !/^[a-f0-9-]{36}$/.test(data.device?.public_id ?? '')) throw new Error('activation_response_invalid');
        const identity = { origin: api.origin, token: data.credential, publicId: data.device.public_id };
        // If save fails after one-use activation, operator must issue a new re-pair code.
        await this.vault.save(identity); this.syncer?.cancel();
        for (const timer of this.timers.values()) clearTimeout(timer); this.timers.clear();
        this.identity = identity; this.blocked = false; await rm(path.join(this.root, 'blocked.json'), { force: true }); this.configure();
        this.schedule('sync', () => this.synchronize(), 0); this.schedule('heartbeat', () => this.heartbeat(), 100); this.notify();
        } finally { this.activating = false; }
    }
    async synchronize() {
        if (!this.api || this.blocked || this.stopped || this.syncer.busy) return;
        const syncer = this.syncer;
        let delay = 60000;
        try {
            await syncer.sync();
            if (this.blocked || syncer !== this.syncer || this.stopped) return;
            this.state.online = true; this.state.lastSync = new Date(this.clock.now()).toISOString(); this.state.lastError = null; this.syncFailures = 0;
            this.state.cacheBytes = await this.store.usage(); await atomicWrite(path.join(this.root, 'clock.json'), JSON.stringify(this.clock.save()));
            await atomicWrite(path.join(this.root, 'sync-metadata.json'), JSON.stringify({ lastSync: this.state.lastSync }));
            delay = Math.max(30000, Math.min(300000, this.syncer.active?.pollIntervalSeconds * 1000 || 60000));
        } catch (error) { if (syncer !== this.syncer || this.stopped) return; this.error(error); this.state.online = false; delay = backoff(this.syncFailures = (this.syncFailures ?? 0) + 1, error.retryAfter); }
        this.notify(); this.schedule('sync', () => this.synchronize(), delay);
    }
    async heartbeat() {
        if (!this.api || this.blocked || this.stopped) return;
        const api = this.api;
        let delay = 60000;
        try {
            const { data } = await api.json('/api/device/heartbeat', { method: 'POST', body: { platform: 'windows', player_version: this.version, screen_width: this.display().width, screen_height: this.display().height, uptime_seconds: Math.floor(process.uptime()) } });
            this.heartbeatFailures = 0; delay = Math.max(30000, Math.min(300000, Number(data.heartbeat_interval_seconds) * 1000 || 60000));
        } catch (error) { if (api !== this.api || this.stopped) return; this.error(error); delay = backoff(this.heartbeatFailures = (this.heartbeatFailures ?? 0) + 1, error.retryAfter); }
        if (api !== this.api || this.stopped) return;
        this.schedule('heartbeat', () => this.heartbeat(), delay);
    }
    current() { return this.blocked ? { state: 'reactivation_required' } : segmentAt(this.syncer?.active, this.clock.now()); }
    snapshot() {
        const segment = this.current(), active = this.syncer?.active;
        return { version: this.version, configured: !!this.identity, deviceId: this.identity?.publicId ?? null, cmsUrl: this.identity?.origin ?? '', ...this.state,
            revision: active?.revision ?? null, settings: this.settings, pinConfigured: !!this.lock?.saved, operatorUnlocked: this.lock?.unlocked ?? true,
            playback: { state: segment.state, schedule: segment.schedule ? { id: segment.schedule.id, name: segment.schedule.name } : null,
                key: playbackKey(segment, active?.media ?? []), startUtc: segment.startUtc ?? null, utc: this.clock.now(),
                layout: segment.playlist ? adaptPlaylist(segment.playlist, active.media) : null } };
    }
    async saveSettings(input) {
        if (!this.lock.unlocked) throw new Error('operator_locked');
        this.settings = { ...this.settings, ...input }; delete this.settings.pin;
        if (input.pin) this.lock.set(input.pin);
        await atomicWrite(path.join(this.root, 'settings.json'), JSON.stringify({ ...this.settings, pin: this.lock.saved })); this.notify();
    }
    async integrity() {
        if (!this.syncer?.active || this.syncer.busy || this.blocked) return;
        if (!await this.store.complete(this.syncer.active)) { this.syncer.active = null; this.syncer.etag = null; this.state.lastError = 'cache_corrupt'; this.notify(); this.schedule('sync', () => this.synchronize(), 0); }
    }
    stop() { this.stopped = true; this.syncer?.cancel(); for (const timer of this.timers.values()) clearTimeout(timer); this.timers.clear(); }
}
