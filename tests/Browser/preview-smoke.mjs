import { chromium } from '../../players/windows/node_modules/playwright-core/index.mjs';
import { spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
const base = process.env.PREVIEW_BASE_URL || 'http://127.0.0.1:8147';
const tag = randomBytes(6).toString('hex');
const php = input => {
    const result = spawnSync('php', ['tests/Browser/preview-fixture.php'], { input: JSON.stringify({ tag, ...input }), encoding: 'utf8' });
    if (result.status !== 0) throw new Error('Fixture PHP failed: ' + result.stderr.slice(0, 250));
    if (!result.stdout.trim().startsWith('{')) throw new Error(result.stdout.match(/fixture_[a-z_]+/)?.[0] ?? 'fixture_setup_failed');
    return JSON.parse(result.stdout);
};
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let created = false;
try {
    const generator = await browser.newPage();
    let video = await generator.evaluate(async () => {
        const canvas = document.createElement('canvas'); canvas.width = 320; canvas.height = 180;
        const mime = 'video/mp4;codecs=avc1.42001E';
        if (!MediaRecorder.isTypeSupported(mime)) throw new Error('H264 MediaRecorder unavailable');
        const stream = canvas.captureStream(15), recorder = new MediaRecorder(stream, { mimeType: mime }), chunks = [];
        recorder.ondataavailable = event => chunks.push(event.data);
        const done = new Promise(resolve => recorder.onstop = resolve);
        const ctx = canvas.getContext('2d'); let frame = 0;
        const timer = setInterval(() => { ctx.fillStyle = frame++ % 2 ? '#dd5533' : '#22aa88'; ctx.fillRect(0, 0, 320, 180); ctx.fillStyle = '#ffffff'; ctx.fillRect(frame % 250, 30, 40, 50); }, 66);
        recorder.start(); await new Promise(resolve => setTimeout(resolve, 2200)); recorder.stop(); await done; clearInterval(timer); stream.getTracks().forEach(track => track.stop());
        const bytes = new Uint8Array(await new Blob(chunks).arrayBuffer()); return btoa(String.fromCharCode(...bytes));
    });
    await generator.close();
    if (!video) {
        const response = await fetch('https://raw.githubusercontent.com/chromium/chromium/main/media/test/data/bear.mp4');
        if (!response.ok) throw new Error('H264 fixture download failed');
        video = Buffer.from(await response.arrayBuffer()).toString('base64');
    }
    created = true;
    console.log(JSON.stringify({ fixtureBytes: Buffer.from(video, 'base64').length, containerHeader: Buffer.from(video, 'base64').subarray(0, 24).toString('hex') }));
    const fixture = php({ action: 'setup', video });
    const context = await browser.newContext(), page = await context.newPage(), errors = [], responses = [];
    page.on('pageerror', error => errors.push(error.message.slice(0, 250)));
    page.on('response', response => { if (response.url().includes('/stream')) responses.push({ status: response.status(), type: response.headers()['content-type'], range: response.headers()['content-range'] ?? null }); });
    await page.goto(base + '/login'); await page.locator('input[type=email]').fill(fixture.email); await page.locator('input[type=password]').fill(fixture.password);
    await page.getByRole('button', { name: /log in/i }).click(); await page.waitForURL('**/admin/dashboard');
    await page.goto(base + '/admin/playlists/' + fixture.playlist + '/studio');
    await page.getByTitle(/Open Playback Preview|Buka Pratinjau Playback/).click();
    const dialog = page.getByRole('dialog', { name: /^(Playback Preview|Pratinjau Playback)$/ });
    await dialog.getByRole('button', { name: /^(Play|Putar)$/ }).click();
    await page.waitForTimeout(10000);
    const snapshot = await page.locator('[x-data="playlistStudio"]').evaluate(element => {
        const data = window.Alpine.$data(element);
        return { metadata: Object.values(data.metadata), state: data.playback.state, windows: data.playback.windows.map(w => ({ state: w.state, duration: w.duration })), time: data.playback.time };
    });
    const frames = await dialog.locator('video').evaluateAll(nodes => nodes.map(video => ({ width: video.videoWidth, ready: video.readyState, time: video.currentTime, paused: video.paused, frames: video.getVideoPlaybackQuality?.().totalVideoFrames ?? 0 })));
    const range = await context.request.get(base + '/admin/media-assets/' + fixture.media + '/stream', { headers: { Range: 'bytes=0-63' } });
    const full = await context.request.get(base + '/admin/media-assets/' + fixture.media + '/stream');
    if (full.status() !== 200 || !Buffer.from(await full.body()).equals(Buffer.from(video, 'base64'))) throw new Error('Full stream byte mismatch');
    const foreign = await context.request.get(base + '/admin/media-assets/' + fixture.foreign + '/stream');
    console.log(JSON.stringify({ snapshot, frames, errors, responses, rangeStatus: range.status(), rangeLength: (await range.body()).length, foreignStatus: foreign.status() }, null, 2));
    if (!process.env.PREVIEW_DIAGNOSE_ONLY && (errors.length || snapshot.metadata.some(m => m.status !== 'ready') || frames.length !== 2 || frames.some(f => f.width === 0 || f.frames === 0 || f.paused) || range.status() !== 206 || foreign.status() !== 404)) throw new Error('Preview browser acceptance failed');
    const openCase = async id => {
        await page.goto(base + '/admin/playlists/' + id + '/studio', { waitUntil: 'domcontentloaded' });
        await page.waitForFunction(() => {
            const el=document.querySelector('[x-data="playlistStudio"]');const ui=el && window.Alpine?.$data(el);
            return !!ui?.playbackManifest && !ui.loadingPlayback && !ui.busy;
        });
        await page.getByTitle(/Open Playback Preview|Buka Pratinjau Playback/).click();
        await page.getByRole('dialog', { name: /^(Playback Preview|Pratinjau Playback)$/ }).getByRole('button', { name: /^(Play|Putar)$/ }).click();
    };
    await openCase(fixture.invalid);
    await page.waitForFunction(() => Object.values(window.Alpine.$data(document.querySelector('[x-data="playlistStudio"]')).metadata).some(info => info.status === 'error'));
    const invalid = await page.locator('[x-data="playlistStudio"]').evaluate(el => Object.values(window.Alpine.$data(el).metadata));
    if (!invalid.some(info => /codec|decod|source/i.test(info.message))) throw new Error('Missing invalid-media diagnostic');
    await page.route('**/admin/media-assets/' + fixture.media + '/stream', async route => {
        await new Promise(resolve => setTimeout(resolve, 11000)); await route.abort().catch(() => {});
    });
    await openCase(fixture.timeout);
    await page.waitForFunction(() => Object.values(window.Alpine.$data(document.querySelector('[x-data="playlistStudio"]')).metadata).some(info => info.status === 'error'), null, { timeout: 12000 });
    const timeout = await page.locator('[x-data="playlistStudio"]').evaluate(el => {
        const ui = window.Alpine.$data(el); return { metadata: Object.values(ui.metadata), windows: ui.playback.windows.map(w => w.state) };
    });
    if (!timeout.metadata.some(info => /timed out/i.test(info.message)) || timeout.windows.includes('loading')) throw new Error('Unbounded metadata timeout');
    console.log(JSON.stringify({ invalid, timeout, errorCasesPassed: true }, null, 2));
} finally { await browser.close(); if (created) php({ action: 'cleanup' }); }
