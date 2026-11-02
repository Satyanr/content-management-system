import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, rm, readFile, writeFile, stat } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { createServer } from 'node:http';
import { createHash } from 'node:crypto';
import { CredentialVault, OperatorLock, atomicWrite } from '../src/services/security/storage.js';
import { SafeLogger } from '../src/services/security/logger.js';
import { DeviceApi, cmsOrigin, backoff } from '../src/services/api/client.js';
import { ServerClock, validateManifest } from '../src/services/api/manifest.js';
import { ContentStore } from '../src/services/cache/store.js';
import { download, SyncManager } from '../src/services/sync/sync.js';
import { PlayerRuntime } from '../src/main/runtime.js';
import { mediaHash, mediaResponse } from '../src/main/protocol.js';
import { adaptPlaylist, segmentAt, playbackKey } from '../src/playback/adapter.js';
import { PlaybackEngine } from '../src/playback/vendor/studio-playback.js';
import { mediaTransformStyle } from '../src/playback/vendor/studio-geometry.js';
import { mediaTransformStyle as studioMediaStyle } from '../../../resources/js/studio-geometry.js';
import { PlayerMetadataQueue } from '../src/playback/metadata.js';
import { manifest, bytes, hash, publicId, token } from './fixtures.js';

async function fixture(t) {
    const root = await mkdtemp(path.join(os.tmpdir(), 'cms-player-unit-'));
    t.after(() => rm(root, { recursive: true, force: true }));
    const store = new ContentStore(path.join(root, 'content')); await store.init();
    return { root, store };
}
const secure = { isAsyncEncryptionAvailable: async () => true, encryptStringAsync: async text => Buffer.from(text).map(byte => byte ^ 173), decryptStringAsync: async encrypted => ({ result: Buffer.from(encrypted).map(byte => byte ^ 173).toString(), shouldReEncrypt: false }) };

test('credential vault requires encryption, never saves plaintext, and survives restart', async t => {
    const { root } = await fixture(t), vault = new CredentialVault(root, secure);
    await vault.save({ token, publicId });
    assert.equal((await readFile(path.join(root, 'credentials.bin'))).includes(Buffer.from(token)), false);
    assert.deepEqual(await new CredentialVault(root, secure).load(), { token, publicId });
    await assert.rejects(new CredentialVault(root, { isAsyncEncryptionAvailable: async () => false }).save({ token }), /secure_storage/);
    await assert.rejects(new CredentialVault(root, { ...secure, getSelectedStorageBackend: () => 'basic_text' }).load(), /secure_storage/);
});

test('successful pairing never exposes credential in status or settings and errors preserve identity', async t => {
    const { root } = await fixture(t); let mode = 'valid';
    const original = globalThis.fetch;
    globalThis.fetch = async (_url, options) => {
        assert.equal(options.headers.Authorization, undefined);
        return mode === 'valid' ? Response.json({ device: { public_id: publicId }, credential: token }) : Response.json({ error: { code: 'invalid_code' } }, { status: 422 });
    };
    t.after(() => { globalThis.fetch = original; });
    const runtime = new PlayerRuntime({ root, safeStorage: secure, version: 'test' }); await runtime.init();
    await runtime.activate({ origin: 'https://cms.example.test', code: 'A'.repeat(20) }); runtime.stop();
    assert.equal(runtime.identity.publicId, publicId);
    assert.equal(JSON.stringify(runtime.snapshot()).includes(token), false);
    mode = 'expired'; await assert.rejects(runtime.activate({ origin: 'https://cms.example.test', code: 'B'.repeat(20) }), /invalid_code/);
    assert.equal(runtime.identity.publicId, publicId);
});

test('CMS origin rejects insecure production, arbitrary endpoints, query secrets, and credentials in URLs', async () => {
    for (const url of ['http://cms.example.test', 'https://user:password@cms.test', 'https://cms.test/path', 'https://cms.test/?token=x', 'file:///test']) assert.throws(() => cmsOrigin(url));
    assert.equal(cmsOrigin('http://127.0.0.1:8000', true), 'http://127.0.0.1:8000');
    assert.throws(() => cmsOrigin('http://other.test', true));
    await assert.rejects(new DeviceApi('https://cms.test', { token }).request('//evil.test/api/device/manifest'), /api_path_invalid/);
});

test('real mock CMS redirect never receives Authorization on another origin', async t => {
    let leaked = false;
    const foreign = createServer((req, res) => { leaked = !!req.headers.authorization; res.end('{}'); });
    await new Promise(resolve => foreign.listen(0, '127.0.0.1', resolve));
    const cms = createServer((_req, res) => { res.writeHead(302, { location: 'http://127.0.0.1:' + foreign.address().port + '/api/device/manifest' }); res.end(); });
    await new Promise(resolve => cms.listen(0, '127.0.0.1', resolve));
    t.after(() => { cms.close(); foreign.close(); });
    const api = new DeviceApi('http://127.0.0.1:' + cms.address().port, { token, allowLocalHttp: true });
    await assert.rejects(api.request('/api/device/manifest'), /redirect_rejected/); assert.equal(leaked, false);
});

test('manifest schema UUID references intervals and untrusted download URLs are validated', () => {
    assert.equal(validateManifest(manifest(), publicId).schemaVersion, 1);
    for (const mutate of [m => m.schemaVersion = 2, m => m.device.publicId = '00000000-0000-4000-8000-000000000002', m => m.media[0].downloadUrl = 'https://evil.test/file', m => m.playlists[0].pages[0].zones[0].items[0].mediaId = 2, m => m.schedulePlan[0].endUtc = m.planStartUtc, m => m.revision = '../escape', m => m.media[0].checksum = null]) {
        const input = manifest(); mutate(input); assert.throws(() => validateManifest(input, publicId));
    }
});

test('server clock uses monotonic time, persists offset, and rejects rollback after reboot', () => {
    let mono = 10, wall = 100000; const clock = new ServerClock({ monotonic: () => mono, wall: () => wall });
    clock.align('2026-11-02T00:00:00Z'); const anchor = clock.now(); mono += 5000; wall += 99999999;
    assert.equal(clock.now(), anchor + 5000);
    const saved = clock.save(); const restored = new ServerClock({ monotonic: () => 0, wall: () => wall + 1000 }); assert.equal(restored.restore(saved), true); assert.equal(restored.now(), saved.utc + 1000);
    assert.equal(new ServerClock({ wall: () => saved.wall - 60000 }).restore(saved), false);
});

test('download verifies SHA256 and resumes partial bytes with strong If-Range', async t => {
    const { store } = await fixture(t), m = manifest(); await writeFile(store.blob(hash, true), bytes.subarray(0, 4));
    const api = new DeviceApi('https://cms.test', { token, fetcher: async (_url, options) => {
        assert.equal(options.headers.Range, 'bytes=4-'); assert.equal(options.headers['If-Range'], '"' + hash + '"'); assert.equal(options.headers['X-Content-Revision'], m.revision);
        return new Response(bytes.subarray(4), { status: 206, headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision, 'content-range': `bytes 4-${bytes.length - 1}/${bytes.length}` } });
    } });
    await download(api, store, m.media[0], m.revision); assert.equal(await store.verified(m.media[0]), true);
});

test('resume rejected with 200 truncates partial, while checksum errors never become blobs', async t => {
    const { store } = await fixture(t), m = manifest(); await writeFile(store.blob(hash, true), bytes.subarray(0, 3));
    const response = body => new Response(body, { headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision } });
    await download({ request: async () => response(bytes) }, store, m.media[0], m.revision); assert.equal(await store.verified(m.media[0]), true);
    await rm(store.blob(hash));
    await assert.rejects(download({ request: async () => response(Buffer.alloc(bytes.length)) }, store, m.media[0], m.revision), /checksum/);
    assert.equal(await store.verified(m.media[0]), false);
});

test('interrupted download persists .part and cancellation cannot promote', async t => {
    const { store } = await fixture(t), m = manifest();
    await assert.rejects(download({ request: async () => new Response(bytes.subarray(0, 4), { headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision } }) }, store, m.media[0], m.revision), /interrupted/);
    assert.equal((await stat(store.blob(hash, true))).size, 4);
    await assert.rejects(store.promote(m), /incomplete/);
});

test('atomic promotion, crash recovery, previous package and corrupt-file rejection', async t => {
    const { store } = await fixture(t), m = manifest(); await writeFile(store.blob(hash), bytes); await store.promote(m);
    await atomicWrite(store.package('b'.repeat(64)), JSON.stringify({ incomplete: true })); // crash before pointer commit
    assert.equal((await store.recover(v => validateManifest(v, publicId))).revision, m.revision);
    const next = manifest({ revision: 'c'.repeat(64) }); await store.promote(next); await writeFile(path.join(store.root, 'active.json'), '{');
    assert.equal((await store.recover(v => validateManifest(v, publicId))).revision, m.revision);
    await writeFile(store.blob(hash), Buffer.alloc(bytes.length)); assert.equal(await store.recover(v => validateManifest(v, publicId)), null);
});

test('sync handles manifest 200 then 304 and confirms revision before atomic activation', async t => {
    const { store } = await fixture(t), m = manifest(); let requests = 0;
    const api = { json: async (_url, options) => {
        requests++; const unchanged = options?.headers?.['If-None-Match'];
        return { response: new Response(unchanged ? null : '{}', { status: unchanged ? 304 : 200, headers: { etag: 'W/"' + m.revision + '"', 'x-server-time-utc': m.serverTimeUtc } }), data: unchanged ? null : structuredClone(m) };
    }, request: async () => new Response(bytes, { headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision } }) };
    const syncer = new SyncManager({ api, store, publicId, clock: new ServerClock() });
    await syncer.sync(); assert.equal(syncer.active.revision, m.revision); await syncer.sync(); assert.equal(requests, 3);
});

test('revision changed during staging retains prior complete package', async t => {
    const { store } = await fixture(t), old = manifest(), next = manifest({ revision: 'b'.repeat(64) }); await writeFile(store.blob(hash), bytes); await store.promote(old);
    let requests = 0; const api = { json: async () => ({ response: new Response('{}', { headers: { 'x-server-time-utc': next.serverTimeUtc } }), data: ++requests === 1 ? next : manifest({ revision: 'c'.repeat(64) }) }) };
    const syncer = new SyncManager({ api, store, publicId, clock: new ServerClock() });
    await assert.rejects(syncer.sync(), /sync_revision_changed/); assert.equal((await store.recover(v => v)).revision, old.revision);
});

test('offline scheduler transitions and expires using supplied UTC without recurrence calculation', () => {
    const m = manifest(), from = Date.parse(m.planStartUtc), mid = from + 120000;
    m.schedulePlan[0].endUtc = new Date(mid).toISOString(); m.schedulePlan.push({ startUtc: new Date(mid).toISOString(), endUtc: m.validUntilUtc, state: 'no_schedule', schedule: null });
    assert.equal(segmentAt(m, from + 1).state, 'scheduled'); assert.equal(segmentAt(m, mid).state, 'no_schedule'); assert.equal(segmentAt(m, Date.parse(m.validUntilUtc)).state, 'expired');
    assert.equal(segmentAt(m, null).state, 'clock_unavailable');
    const current = segmentAt(m, from + 1), key = playbackKey(current, m.media); m.revision = 'b'.repeat(64); current.startUtc = new Date(from + 500).toISOString(); assert.equal(playbackKey(current, m.media), key);
});

test('adapter uses verified local scheme and shared engine sequences concurrent Windows with auto/manual duration', () => {
    const m = manifest(), layout = m.playlists[0], first = layout.pages[0].zones[0];
    first.items.push({ ...first.items[0], id: 2, sort_order: 2, type: 'video', duration: null, knownDuration: 4 });
    layout.pages[0].zones.push({ ...structuredClone(first), id: 2 });
    const adapted = adaptPlaylist(layout, m.media); assert.ok(adapted.pages[0].zones[0].items[0].src.startsWith('signage-media://cache/'));
    let now = 0; const engine = new PlaybackEngine(adapted, { now: () => now, raf: () => 1, cancel: () => {} }); engine.play(); now = 11000;
    assert.equal(engine.step().windows.length, 2); assert.equal(engine.snapshot().windows[0].item.id, 2); now = 15000; assert.equal(engine.step().pageTime, 0); engine.destroy();
});

test('custom media protocol rejects traversal and unrelated hashes and serves bounded Range', async t => {
    const { store } = await fixture(t), m = manifest(); await writeFile(store.blob(hash), bytes);
    const runtime = { store, blocked: false, syncer: { active: m } };
    for (const url of ['signage-media://cache/../../credentials.bin', 'file:///secret', 'signage-media://cache/' + hash + '?path=x']) assert.equal(mediaHash(url), null);
    const response = await mediaResponse(new Request('signage-media://cache/' + hash, { headers: { range: 'bytes=2-5' } }), runtime); assert.equal(response.status, 206); assert.equal(await response.text(), bytes.subarray(2, 6).toString());
    runtime.blocked = true; assert.equal((await mediaResponse(new Request('signage-media://cache/' + hash), runtime)).status, 404);
});

test('401 stops credentials, retry jitter is bounded, and outage recovery is possible', async () => {
    let unauthorized = 0, online = false;
    const api = new DeviceApi('https://cms.test', { token, onUnauthorized: () => unauthorized++, fetcher: async () => { if (!online) throw new Error('network'); return Response.json({ error: { code: 'invalid_token' } }, { status: 401 }); } });
    await assert.rejects(api.request('/api/device/manifest'), /cms_unreachable/); online = true;
    await assert.rejects(api.request('/api/device/manifest'), /invalid_token/); assert.equal(api.token, null); assert.equal(unauthorized, 1);
    assert.ok(backoff(20, 60000, () => 0) <= 900000); assert.ok(backoff(1, 120000) >= 120000);
});

test('operator PIN is hashed, expires and rate limits five failed attempts', () => {
    let now = 0; const lock = new OperatorLock(null, () => now); const stored = lock.set('123456'); assert.notEqual(stored.hash, '123456'); now = 300001; assert.equal(lock.unlocked, false);
    for (let i = 0; i < 5; i++) assert.throws(() => lock.unlock('000000'), /pin_invalid/);
    assert.throws(() => lock.unlock('123456'), /rate_limited/); now += 60001; assert.equal(lock.unlock('123456'), true);
});

test('metadata queue probes at most two decoders and releases resources on completion or stop', async () => {
    let current = 0, maximum = 0, destroyed = 0; const callbacks = [];
    const queue = new PlayerMetadataQueue({ onResult: () => {}, factory: ({ onResult }) => ({ load: () => { current++; maximum = Math.max(maximum, current); callbacks.push(() => onResult('src', { status: 'error' })); }, destroy: () => { current--; destroyed++; } }) });
    queue.load({ pages: [{ zones: [{ items: Array.from({ length: 8 }, (_, id) => ({ type: 'video', available: true, src: 'src' + id, duration: null, knownDuration: null })) }] }] });
    assert.equal(current, 2); callbacks.shift()(); await new Promise(resolve => setImmediate(resolve)); assert.equal(current, 2); queue.destroy();
    assert.equal(current, 0); assert.equal(maximum, 2); assert.equal(destroyed, 3);
});

test('429 Retry-After and unsupported schema are machine errors without server text leakage', async () => {
    const api = new DeviceApi('https://cms.test', { token, fetcher: async () => Response.json({ message: token, error: { code: 'rate_limited' } }, { status: 429, headers: { 'retry-after': '120' } }) });
    await assert.rejects(api.request('/api/device/manifest'), error => error.retryAfter === 120000 && error.code === 'rate_limited' && !error.message.includes(token));
    api.fetcher = async () => Response.json({ error: { code: 'unsupported_schema_version' } }, { status: 406 });
    await assert.rejects(api.request('/api/device/manifest'), error => error.status === 406);
});

test('GC preserves active blobs, removes unreferenced data and enforces cache budgets', async t => {
    const { store } = await fixture(t), m = manifest(); await writeFile(store.blob(hash), bytes); await store.promote(m);
    const garbage = store.blob('f'.repeat(64)); await writeFile(garbage, 'garbage'); await store.gc();
    await assert.rejects(stat(garbage), { code: 'ENOENT' }); assert.equal(await store.verified(m.media[0]), true);
    store.maxBytes = 1; await assert.rejects(store.reserve(m), /cache_limit/);
});

test('sync recovers from server outage and two workers never exceed download concurrency', async t => {
    const { store } = await fixture(t), m = manifest(); let online = false, current = 0, maximum = 0;
    const contents = new Map([[1, bytes]]);
    for (let id = 2; id <= 5; id++) {
        const content = Buffer.from('unique-content-' + id); contents.set(id, content);
        m.media.push({ ...m.media[0], id, downloadUrl: '/api/device/media/' + id, size: content.length, checksum: { algorithm: 'sha256', value: createHash('sha256').update(content).digest('hex') } });
    }
    const api = { json: async () => { if (!online) throw new Error('cms_unreachable'); return { response: new Response('{}', { headers: { 'x-server-time-utc': m.serverTimeUtc } }), data: m }; },
        request: async endpoint => { current++; maximum = Math.max(maximum, current); await new Promise(resolve => setTimeout(resolve, 10)); current--; const id = Number(endpoint.split('/').at(-1)); const asset = m.media.find(a => a.id === id); return new Response(contents.get(id), { headers: { etag: '"' + asset.checksum.value + '"', 'x-content-revision': m.revision } }); } };
    const syncer = new SyncManager({ api, store, publicId, clock: new ServerClock() });
    await assert.rejects(syncer.sync(), /cms_unreachable/); online = true; await syncer.sync(); assert.equal(syncer.active.revision, m.revision); assert.equal(maximum, 2);
});

test('cancelled download cannot create a verified active blob', async t => {
    const { store } = await fixture(t), m = manifest(), cancellation = new AbortController(); cancellation.abort();
    await assert.rejects(download({ request: async () => new Response(bytes, { headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision } }) }, store, m.media[0], m.revision, cancellation.signal), /cancelled/);
    assert.equal(await store.verified(m.media[0]), false);
});

test('identical content IDs share one download blob without concurrent partial-file races', async t => {
    const { store } = await fixture(t), m = manifest(); m.media.push({ ...m.media[0], id: 2, downloadUrl: '/api/device/media/2' }); let requests = 0;
    const api = { json: async () => ({ response: new Response('{}', { headers: { 'x-server-time-utc': m.serverTimeUtc } }), data: m }), request: async () => { requests++; return new Response(bytes, { headers: { etag: '"' + hash + '"', 'x-content-revision': m.revision } }); } };
    await new SyncManager({ api, store, publicId, clock: new ServerClock() }).sync();
    assert.equal(requests, 1); assert.equal(await store.usage(), bytes.length);
});

test('rotating logger accepts only sanitized codes and never stores supplied secrets or URLs', async t => {
    const { root } = await fixture(t), logger = new SafeLogger(path.join(root, 'logs'));
    logger.log(token); logger.log('https://cms.test/?secret=code'); logger.log('invalid_token'); await logger.queue;
    const saved = await readFile(path.join(root, 'logs', 'player.log'), 'utf8');
    assert.equal(saved.includes(token), false); assert.equal(saved.includes('secret='), false); assert.ok(saved.includes('invalid_token'));
});

test('optional media transform is identical in Player and Studio while legacy layouts remain supported',()=>{
    const m=manifest(),item=m.playlists[0].pages[0].zones[0].items[0];
    const legacy=adaptPlaylist(m.playlists[0],m.media);assert.equal(legacy.pages[0].zones[0].items[0].mediaTransform,undefined);
    item.mediaTransform={scale:2.5,offset_x:20,offset_y:-10,fit_mode:'cover',keep_aspect_ratio:true};
    validateManifest(m,publicId);
    const mapped=adaptPlaylist(m.playlists[0],m.media).pages[0].zones[0].items[0];assert.deepEqual(mapped.mediaTransform,item.mediaTransform);
    assert.deepEqual(mediaTransformStyle(mapped.mediaTransform),studioMediaStyle(item.mediaTransform));
    item.mediaTransform.scale=6;assert.throws(()=>validateManifest(m,publicId));
});
