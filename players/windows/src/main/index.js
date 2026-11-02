import { app, BrowserWindow, ipcMain, protocol, session, screen, powerSaveBlocker, powerMonitor, safeStorage } from 'electron';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { PlayerRuntime } from './runtime.js';
import { uiResponse, mediaResponse } from './protocol.js';

const flags = new Set(process.argv);
const testPath = process.argv.find(arg => arg.startsWith('--test-data-dir='));
if (!app.isPackaged && testPath && path.isAbsolute(testPath.split('=').slice(1).join('='))) app.setPath('userData', testPath.split('=').slice(1).join('='));
const hidden = !app.isPackaged && flags.has('--smoke-test');
const allowLocalHttp = !app.isPackaged && flags.has('--allow-localhost-http');
protocol.registerSchemesAsPrivileged([
    { scheme: 'signage', privileges: { standard: true, secure: true, supportFetchAPI: true } },
    { scheme: 'signage-media', privileges: { standard: true, secure: true, supportFetchAPI: true, stream: true } },
]);
app.setAppUserModelId('id.cms.signage.player');
if (!app.requestSingleInstanceLock()) app.quit();
else {
    let window, runtime, blocker = null, statusTimer, integrityTimer;
    const display = () => screen.getAllDisplays().find(d => d.id === runtime?.settings.displayId) ?? screen.getPrimaryDisplay();
    const releasePower = () => { if (blocker !== null) { powerSaveBlocker.stop(blocker); blocker = null; } };
    const publish = () => {
        if (!runtime || !window || window.isDestroyed()) return;
        const snapshot = runtime.snapshot();
        if (snapshot.playback.state === 'scheduled' && snapshot.playback.layout) { if (blocker === null) blocker = powerSaveBlocker.start('prevent-display-sleep'); } else releasePower();
        snapshot.displays = screen.getAllDisplays().map(d => ({ id: d.id, label: d.label || `Display ${d.id}`, width: d.size.width, height: d.size.height }));
        window.webContents.send('player:state', snapshot);
    };
    const applyWindow = () => {
        if (!window || window.isDestroyed()) return;
        window.setKiosk(false); window.setFullScreen(false); window.setBounds(display().bounds);
        window.setAlwaysOnTop(runtime.settings.alwaysOnTop); window.setFullScreen(true); window.setKiosk(runtime.settings.kiosk);
        if (app.isPackaged) app.setLoginItemSettings({ openAtLogin: runtime.settings.autoStart, path: process.execPath });
        publish();
    };
    const createWindow = () => {
        window = new BrowserWindow({ show: !hidden, width: 1280, height: 720, backgroundColor: '#050911', autoHideMenuBar: true,
            webPreferences: { preload: fileURLToPath(new URL('../preload/index.cjs', import.meta.url)), contextIsolation: true, sandbox: true, nodeIntegration: false, webSecurity: true, devTools: !app.isPackaged, autoplayPolicy: 'no-user-gesture-required' } });
        window.removeMenu();
        window.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));
        window.webContents.on('will-navigate', event => event.preventDefault());
        window.webContents.on('will-attach-webview', event => event.preventDefault());
        window.webContents.on('before-input-event', (event, input) => {
            if (input.control && input.alt && input.key.toLowerCase() === 'o' && input.type === 'keyDown') { event.preventDefault(); window.webContents.send('player:operator'); }
        });
        let failures = [];
        window.webContents.on('render-process-gone', () => {
            releasePower(); runtime.logger.log('renderer_process_gone');
            failures = failures.filter(t => Date.now() - t < 300000); failures.push(Date.now());
            if (failures.length <= 3) setTimeout(() => { if (!window.isDestroyed()) window.reload(); }, failures.length * 2000);
        });
        window.webContents.on('did-finish-load', publish);
        void window.loadURL('signage://app/index.html'); applyWindow();
    };
    app.on('second-instance', () => { if (window) { window.show(); window.focus(); } });
    app.whenReady().then(async () => {
        runtime = new PlayerRuntime({ root: app.getPath('userData'), safeStorage, version: app.getVersion(), allowLocalHttp,
            display: () => ({ width: display().size.width * display().scaleFactor, height: display().size.height * display().scaleFactor }), notify: publish });
        await runtime.init();
        protocol.handle('signage', uiResponse);
        protocol.handle('signage-media', request => mediaResponse(request, runtime));
        session.defaultSession.setPermissionRequestHandler((_wc, _permission, callback) => callback(false));
        session.defaultSession.setPermissionCheckHandler(() => false);
        session.defaultSession.webRequest.onBeforeRequest((details, callback) => callback({ cancel: !details.url.startsWith('signage://app/') && !details.url.startsWith('signage-media://cache/') && details.url !== 'about:blank' }));
        const caller = event => {
            if (!window || event.sender !== window.webContents || event.senderFrame?.url !== 'signage://app/index.html' || event.senderFrame.parent) throw new Error('ipc_caller_invalid');
        };
        const handler = (name, operation) => ipcMain.handle('player:' + name, async (event, input) => {
            try { caller(event); if (JSON.stringify(input ?? null).length > 4096) throw new Error('ipc_input_invalid'); return { ok: true, value: await operation(input) }; }
            catch (error) { runtime.error(error); publish(); return { ok: false, error: /^[a-z_0-9]{1,80}$/.test(error.code ?? error.message) ? error.code ?? error.message : 'operation_failed' }; }
        });
        handler('status', () => { publish(); return runtime.snapshot(); });
        handler('activate', async input => {
            if (!input || Object.keys(input).sort().join(',') !== 'code,origin' || typeof input.origin !== 'string' || input.origin.length > 2048 || typeof input.code !== 'string' || !/^[a-fA-F0-9-]{20,24}$/.test(input.code)) throw new Error('activation_input_invalid');
            await runtime.activate(input); return true;
        });
        handler('unlock', input => { if (typeof input !== 'string' || !/^\d{6,12}$/.test(input)) throw new Error('operator_pin_format'); runtime.lock.unlock(input); publish(); return true; });
        handler('sync', () => { if (!runtime.lock.unlocked) throw new Error('operator_locked'); if (!runtime.blocked) runtime.schedule('sync', () => runtime.synchronize(), 0); return true; });
        let lastReconnect = 0;
        handler('reconnect', () => { if (!runtime.blocked && Date.now() - lastReconnect > 30000) { lastReconnect = Date.now(); runtime.schedule('sync', () => runtime.synchronize(), 250); } return true; });
        handler('settings', async input => {
            if (!input || typeof input !== 'object' || Array.isArray(input)) throw new Error('settings_invalid');
            for (const [key, value] of Object.entries(input)) {
                if (['kiosk', 'alwaysOnTop', 'autoStart', 'muted'].includes(key)) { if (typeof value !== 'boolean') throw new Error('settings_invalid'); }
                else if (key === 'volume') { if (!Number.isFinite(value) || value < 0 || value > 1) throw new Error('settings_invalid'); }
                else if (key === 'displayId') { if (value !== null && !screen.getAllDisplays().some(d => d.id === value)) throw new Error('settings_invalid'); }
                else if (key === 'pin') { if (typeof value !== 'string' || !/^\d{6,12}$/.test(value)) throw new Error('operator_pin_format'); }
                else throw new Error('settings_invalid');
            }
            await runtime.saveSettings(input); applyWindow(); return true;
        });
        handler('restart', () => { if (!runtime.lock.unlocked) throw new Error('operator_locked'); app.relaunch(); app.quit(); return true; });
        createWindow();
        statusTimer = setInterval(publish, 1000);
        integrityTimer = setInterval(() => { void runtime.integrity().catch(error => runtime.error(error)); }, 15 * 60000);
        for (const event of ['display-added', 'display-removed', 'display-metrics-changed']) screen.on(event, applyWindow);
        powerMonitor.on('resume', () => { if (!runtime.blocked) runtime.schedule('sync', () => runtime.synchronize(), 500); publish(); });
        powerMonitor.on('suspend', releasePower);
    }).catch(() => { app.exit(1); });
    app.on('before-quit', () => { clearInterval(statusTimer); clearInterval(integrityTimer); releasePower(); runtime?.stop(); });
    app.on('window-all-closed', () => app.quit());
}
