import { _electron as electron } from '@playwright/test';
import { mkdtemp, readFile, appendFile, realpath, stat } from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { summarizeJournal, videoStalled } from './soak-metrics.js';

// A fresh dedicated unpackaged Player only. Never attach to or modify an operator Player.
const args = Object.fromEntries(process.argv.slice(2).map(arg => { const [key, ...value] = arg.replace(/^--/, '').split('='); return [key, value.join('=') || 'true']; }));
const seconds = Number(args.seconds ?? 86400), interval = Number(args.interval ?? 30);
if (!Number.isFinite(seconds) || seconds < 5 || seconds > 72 * 3600 || !Number.isFinite(interval) || interval < 5 || interval > 3600) throw Error('seconds/interval out of range');
const tempRoot = await realpath(os.tmpdir());
const directory = await mkdtemp(path.join(tempRoot, 'cms-soak-'));
const log = path.join(directory, 'samples.jsonl');
console.log(JSON.stringify({ runDirectory: directory, seconds, interval, mode: 'dedicated-unpackaged', warning: 'Use only a dedicated test Device and test media.' }));
const launchArgs = ['.', '--test-data-dir=' + directory];
if (args.hidden === 'true') launchArgs.push('--smoke-test');
if (args['allow-localhost-http'] === 'true') launchArgs.push('--allow-localhost-http');
const env = { ...process.env }; delete env.ELECTRON_RUN_AS_NODE;
let application, stopping = false, previous = new Map(), previousLoads = null, previousGone = null;
const finish = () => { stopping = true; };
process.on('SIGINT', finish); process.on('SIGTERM', finish);

async function journalSummary() {
    const file = path.join(directory, 'playback', 'playback.journal');
    try {
        const info = await stat(file);
        if (info.size > 64 * 1024 * 1024) return { available: false, reason: 'journal_too_large' };
        return summarizeJournal(await readFile(file, 'utf8'));
    } catch (error) { return { available: false, reason: error.code === 'ENOENT' ? 'journal_not_created' : 'journal_read_failed' }; }
}

try {
    application = await electron.launch({ args: launchArgs, cwd: process.cwd(), env });
    let page = await application.firstWindow();
    await application.evaluate(({ app, BrowserWindow }) => {
        globalThis.__soak = { heartbeats: 0, heartbeatFailures: 0, lastHeartbeatUtc: null, rendererGone: 0, rendererLoads: 0 };
        const original = globalThis.fetch;
        globalThis.fetch = async (...input) => {
            let heartbeat = false;
            try { heartbeat = new URL(input[0]).pathname === '/api/device/heartbeat'; } catch {}
            try { const response = await Reflect.apply(original, globalThis, input); if (heartbeat) { if (response.ok) { globalThis.__soak.heartbeats++; globalThis.__soak.lastHeartbeatUtc = new Date().toISOString(); } else globalThis.__soak.heartbeatFailures++; } return response; }
            catch (error) { if (heartbeat) globalThis.__soak.heartbeatFailures++; throw error; }
        };
        const win = BrowserWindow.getAllWindows()[0];
        win.webContents.on('render-process-gone', () => globalThis.__soak.rendererGone++);
        win.webContents.on('did-finish-load', () => globalThis.__soak.rendererLoads++);
    });
    const started = Date.now();
    while (!stopping && Date.now() - started < seconds * 1000) {
        const main = await application.evaluate(({ app }) => ({ processes: app.getAppMetrics().map(p => ({ type: p.type, cpuPercent: Number.isFinite(p.cpu.percentCPUUsage) && p.cpu.percentCPUUsage >= 0 ? p.cpu.percentCPUUsage : null, workingSetKB: p.memory.workingSetSize })), ...globalThis.__soak }));
        if (previousLoads !== main.rendererLoads || previousGone !== main.rendererGone) previous.clear();
        previousLoads = main.rendererLoads; previousGone = main.rendererGone;
        let playback;
        try {
            if (page.isClosed()) page = await application.firstWindow();
            playback = await page.evaluate(async () => {
                const status = await window.player.status(), value = status.value;
                window.__soakVideoIds ??= new WeakMap(); window.__soakNextId ??= 0;
                const videos = [...document.querySelectorAll('#canvas video')].map((v, index) => { if (!window.__soakVideoIds.has(v)) window.__soakVideoIds.set(v, ++window.__soakNextId); return { id: window.__soakVideoIds.get(v), index, time: v.currentTime, frames: v.getVideoPlaybackQuality().totalVideoFrames, readyState: v.readyState, paused: v.paused, seeking: v.seeking, error: v.error?.code ?? null }; });
                return { configured: value.configured, online: value.online, state: value.playback.state, lastSync: value.lastSync, lastError: value.lastError, cacheBytes: value.cacheBytes, videos };
            });
            playback.videos = playback.videos.map(v => { const stalled = videoStalled(previous.get(v.id), v); previous.set(v.id, v); return { ...v, stalled }; });
            for (const id of previous.keys()) if (!playback.videos.some(v => v.id === id)) previous.delete(id);
        } catch { playback = { available: false, reason: 'renderer_unavailable' }; previous.clear(); }
        const sample = { at: new Date().toISOString(), elapsedSeconds: Math.floor((Date.now() - started) / 1000), ...main, playback, ledger: await journalSummary() };
        await appendFile(log, JSON.stringify(sample) + '\n', { mode: 0o600 });
        await new Promise(resolve => setTimeout(resolve, Math.min(interval * 1000, Math.max(0, seconds * 1000 - (Date.now() - started)))));
    }
    console.log(JSON.stringify({ completed: !stopping && Date.now() - started >= seconds * 1000, elapsedSeconds: Math.floor((Date.now() - started) / 1000), log }));
} finally { await application?.close(); }
