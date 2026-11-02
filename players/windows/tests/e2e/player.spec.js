import { test, expect, _electron as electron } from '@playwright/test';
import { mkdtemp, rm, readFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { createServer } from 'node:http';
import { createHash } from 'node:crypto';
import { manifest, publicId, token } from '../fixtures.js';
test('Windows hidden setup, sandbox and selected-display bounds smoke', async () => {
    const directory = await mkdtemp(path.join(os.tmpdir(), 'cms-player-e2e-'));
    const env = { ...process.env }; delete env.ELECTRON_RUN_AS_NODE;
    let application;
    try {
        application = await electron.launch({ args: ['.', '--smoke-test', '--test-data-dir=' + directory], cwd: process.cwd(), env });
        const page = await application.firstWindow();
        await expect(page.locator('#setup')).toBeVisible();
        await expect(page.locator('h1', { hasText: 'Connect your display' })).toBeVisible();
        expect(await page.evaluate(() => typeof window.require)).toBe('undefined');
        expect(await page.evaluate(() => Object.keys(window.player))).not.toContain('token');
        const config = await application.evaluate(({ BrowserWindow }) => {
            const win = BrowserWindow.getAllWindows()[0], options = win.webContents.getLastWebPreferences();
            return { fullScreen: win.isFullScreen(), sandbox: options.sandbox, isolation: options.contextIsolation, node: options.nodeIntegration };
        });
        expect(config).toMatchObject({ sandbox: true, isolation: true, node: false });
        // A hidden Windows window cannot enter OS fullscreen until shown; verify selected-display bounds here.
        expect(await application.evaluate(({ BrowserWindow, screen }) => {
            const bounds = BrowserWindow.getAllWindows()[0].getBounds(), target = screen.getPrimaryDisplay().bounds;
            return bounds.width === target.width && bounds.height === target.height;
        })).toBe(true);
        await page.locator('#setup-operator').click(); await expect(page.locator('#operator')).toBeVisible();
        await expect(page.locator('#display option')).not.toHaveCount(0);
        expect(await page.evaluate(() => window.player.settings({ arbitraryFile: 'C:/secret' }))).toMatchObject({ ok: false, error: 'settings_invalid' });
        const denied = await page.evaluate(async () => { try { return await fetch('https://example.com').then(() => false); } catch { return true; } }); expect(denied).toBe(true);
    } finally { await application?.close(); await rm(directory, { recursive: true, force: true }); }
});

test('real Windows safeStorage pairing, image playback, restart persistence and revocation', async ({}, testInfo) => {
    const directory = await mkdtemp(path.join(os.tmpdir(), 'cms-player-pairing-'));
    const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=', 'base64');
    const imageHash = createHash('sha256').update(image).digest('hex');
    const data = manifest(); data.media[0].size = image.length; data.media[0].checksum.value = imageHash;
    let revoked = false, heartbeat = 0;
    const server = createServer((request, response) => {
        if (request.url === '/api/device/activate') { response.writeHead(201, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ device: { public_id: publicId }, credential: token })); return; }
        if (request.headers.authorization !== 'Bearer ' + token || revoked) { response.writeHead(401, { 'Content-Type': 'application/json' }); response.end('{"error":{"code":"invalid_token"}}'); return; }
        if (request.url === '/api/device/heartbeat') { heartbeat++; response.setHeader('Content-Type', 'application/json'); response.end('{"heartbeat_interval_seconds":60}'); return; }
        if (request.url === '/api/device/manifest') {
            response.setHeader('X-Server-Time-Utc', new Date().toISOString()); response.setHeader('ETag', 'W/"' + data.revision + '"');
            if (request.headers['if-none-match']) { response.writeHead(304); response.end(); return; }
            response.setHeader('Content-Type', 'application/json'); response.end(JSON.stringify(data)); return;
        }
        if (request.url === '/api/device/media/1') { response.writeHead(200, { ETag: '"' + imageHash + '"', 'X-Content-Revision': data.revision, 'Content-Length': image.length }); response.end(image); return; }
        response.writeHead(404); response.end();
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const env = { ...process.env }; delete env.ELECTRON_RUN_AS_NODE;
    const launch = () => electron.launch({ args: ['.', '--smoke-test', '--allow-localhost-http', '--test-data-dir=' + directory], cwd: process.cwd(), env });
    let application;
    try {
        application = await launch(); let page = await application.firstWindow();
        await page.locator('#origin').fill('http://127.0.0.1:' + server.address().port); await page.locator('#code').fill('A'.repeat(20)); await page.locator('#activate').click();
        await expect(page.locator('#setup')).toBeHidden();
        await expect.poll(() => page.evaluate(() => document.querySelector('#canvas img')?.naturalWidth ?? 0)).toBe(1);
        expect((await readFile(path.join(directory, 'credentials.bin'))).includes(Buffer.from(token))).toBe(false);
        expect(await page.evaluate(async () => JSON.stringify((await window.player.status()).value).includes('dvc_'))).toBe(false);
        await expect.poll(() => heartbeat).toBeGreaterThan(0);
        await application.close(); application = await launch(); page = await application.firstWindow();
        await expect(page.locator('#setup')).toBeHidden();
        await expect.poll(() => page.evaluate(() => document.querySelector('#canvas img')?.naturalWidth ?? 0)).toBe(1);
        revoked = true; await page.evaluate(() => window.player.sync());
        await expect.poll(() => page.evaluate(async () => (await window.player.status()).value.playback.state)).toBe('reactivation_required');
        await expect(page.locator('#setup')).toBeVisible();
    } catch (error) {
        if (application) { const page = await application.firstWindow(); await testInfo.attach('safe-player-status', { body: JSON.stringify(await page.evaluate(() => window.player.status())), contentType: 'application/json' }); }
        throw error;
    } finally { await application?.close(); server.close(); await rm(directory, { recursive: true, force: true }); }
});
