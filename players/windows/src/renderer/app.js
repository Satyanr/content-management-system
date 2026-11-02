import { PlaybackEngine } from '/playback/vendor/studio-playback.js';
import { PlaybackRenderer } from '/playback/vendor/studio-playback-renderer.js';
import { PlayerMetadataQueue } from '/playback/metadata.js';
const $ = id => document.getElementById(id);
let state, engine, renderer, pool, key, layout, settingsDirty = false, repairing = false, audioVersion = 0;
const cleanup = () => { pool?.destroy(); engine?.destroy(); renderer?.destroy(); pool = engine = renderer = null; };
function scale() { if (!layout) return; const ratio = Math.min(innerWidth / layout.width, innerHeight / layout.height); Object.assign($('canvas').style, { width: layout.width * ratio + 'px', height: layout.height * ratio + 'px' }); }
function play(next) {
    if (next.key === key) return;
    key = next.key; cleanup(); layout = next.layout;
    $('idle').hidden = !!layout; $('canvas').hidden = !layout;
    $('idle-title').textContent = ({ expired: 'Offline schedule expired', no_schedule: 'No scheduled content', no_package: 'Waiting for content', clock_unavailable: 'Waiting for server time', reactivation_required: 'Device activation required' })[next.state] ?? 'Ready to connect';
    if (!layout) return;
    scale();
    renderer = new PlaybackRenderer($('canvas'), { onError: src => engine?.setMetadata(src, { status: 'error' }), onBlocked: () => { $('connection').textContent = 'AUDIO BLOCKED — open settings and toggle mute'; } });
    engine = new PlaybackEngine(layout, { onChange: snapshot => renderer?.render(snapshot, { width: layout.width, height: layout.height, muted: state.settings.muted, volume: state.settings.volume, audioVersion }) });
    pool = new PlayerMetadataQueue({ onResult: (src, info) => engine?.setMetadata(src, info) });
    pool.load(engine.manifest);
    if (engine.manifest.totalDuration) engine.seek(Math.max(0, (next.utc - Date.parse(next.startUtc)) / 1000) % engine.manifest.totalDuration);
    engine.play();
}
function update(next) {
    const audioChanged = state && (state.settings.muted !== next.settings.muted || state.settings.volume !== next.settings.volume);
    state = next; if (audioChanged) audioVersion++;
    $('setup').hidden = next.configured && next.playback.state !== 'reactivation_required' && !repairing;
    $('activate').disabled = next.configured && !next.operatorUnlocked;
    $('connection').textContent = `${next.online ? 'ONLINE' : 'OFFLINE'} · ${next.playback.state.replaceAll('_', ' ').toUpperCase()}`;
    play(next.playback);
    const rows = { Device: next.deviceId ?? 'Not paired', CMS: next.cmsUrl || 'Not configured', Connection: next.online ? 'Online' : 'Offline', Schedule: next.playback.schedule?.name ?? 'None', Playlist: next.playback.layout?.name ?? 'None', Revision: next.revision ?? 'None', 'Last successful sync': next.lastSync ?? 'Never', 'Cache size': (next.cacheBytes / 1024 ** 2).toFixed(1) + ' MiB', Version: next.version, 'Last error': next.lastError ?? 'None' };
    $('status').replaceChildren(...Object.entries(rows).flatMap(([name, value]) => { const dt = document.createElement('dt'), dd = document.createElement('dd'); dt.textContent = name; dd.textContent = value; return [dt, dd]; }));
    $('unlock-form').hidden = next.operatorUnlocked; $('settings-form').hidden = !next.operatorUnlocked;
    for (const id of ['sync', 'restart', 'repair']) $(id).disabled = !next.operatorUnlocked;
    if (!settingsDirty) {
        $('kiosk').checked = next.settings.kiosk; $('top').checked = next.settings.alwaysOnTop; $('autostart').checked = next.settings.autoStart; $('muted').checked = next.settings.muted; $('volume').value = next.settings.volume;
        if (next.displays) { $('display').replaceChildren(...next.displays.map(display => { const option = document.createElement('option'); option.value = display.id; option.textContent = `${display.label} · ${display.width} × ${display.height}`; return option; })); $('display').value = next.settings.displayId ?? next.displays[0]?.id; }
    }
}
async function run(promise, errorId = 'operation-error') { const result = await promise; $(errorId).textContent = result.ok ? '' : result.error.replaceAll('_', ' '); return result; }
$('activation').addEventListener('submit', async event => { event.preventDefault(); $('activate').disabled = true; const result = await run(window.player.activate({ origin: $('origin').value.trim(), code: $('code').value.trim() }), 'activation-error'); $('code').value = ''; $('activate').disabled = false; if (result.ok) { repairing = false; $('setup').hidden = true; } });
const operator = () => { $('operator').hidden = !$('operator').hidden; };
$('setup-operator').onclick = operator; $('close').onclick = operator;
$('unlock-form').onsubmit = async event => { event.preventDefault(); await run(window.player.unlock($('unlock-pin').value)); $('unlock-pin').value = ''; };
$('settings-form').oninput = () => { settingsDirty = true; };
$('settings-form').onsubmit = async event => { event.preventDefault(); const input = { kiosk: $('kiosk').checked, alwaysOnTop: $('top').checked, autoStart: $('autostart').checked, muted: $('muted').checked, volume: Number($('volume').value), displayId: Number($('display').value) }; if ($('new-pin').value) input.pin = $('new-pin').value; const result = await run(window.player.settings(input)); $('new-pin').value = ''; if (result.ok) settingsDirty = false; };
$('sync').onclick = () => run(window.player.sync()); $('restart').onclick = () => run(window.player.restart());
$('repair').onclick = () => { if (confirm('Re-pair with a newly issued code? The current identity will be replaced only after successful secure storage.')) { repairing = true; $('origin').value = state.cmsUrl; $('setup').hidden = false; $('operator').hidden = true; } };
const unsubscribe = window.player.onState(update), unsubscribeOperator = window.player.onOperator(operator);
window.addEventListener('resize', scale); window.addEventListener('beforeunload', () => { unsubscribe(); unsubscribeOperator(); cleanup(); });
window.addEventListener('online', () => { void window.player.reconnect(); });
void window.player.status().then(result => { if (result.ok) update(result.value); });
