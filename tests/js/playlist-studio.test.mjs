import test from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { addEventListener() {} };
const { fitScale, clampZoom, playlistStudio } = await import('../../resources/js/playlist-studio.js');

test('manual zoom respects 25-200% bounds', () => {
    assert.equal(clampZoom(0.1), 0.25);
    assert.equal(clampZoom(3), 2);
    assert.equal(clampZoom(0.8), 0.8);
});

test('fit preserves aspect and fits landscape portrait banner and ultrawide at small desktop size', () => {
    for (const [w, h] of [[1920,1080], [1080,1920], [1920,384], [4864,512], [1234,567]]) {
        const scale = fitScale(w, h, 846, 560);
        assert.ok(w * scale <= 782.001);
        assert.ok(h * scale <= 496.001);
        assert.ok(Math.abs((w * scale) / (h * scale) - w / h) < 0.0001);
    }
    assert.equal(fitScale(100, 100, 4000, 2000), 2);
    assert.equal(fitScale(0, 100, 800, 600), 0.25);
});

test('editor zoom never changes output dimensions and fit responds to panel changes', () => {
    let onResize;
    let disconnected = false;
    globalThis.ResizeObserver = class {
        constructor(callback) { onResize = callback; }
        observe() {}
        disconnect() { disconnected = true; }
    };
    globalThis.window = { innerWidth: 1366, innerHeight: 768 };
    const ui = playlistStudio();
    ui.$el = { dataset: { width: '4864', height: '512' } };
    ui.$refs = { viewport: { clientWidth: 846, clientHeight: 560 } };
    ui.$nextTick = fn => fn();
    ui.init();
    assert.ok(ui.scale < 0.25);
    const oldScale = ui.scale;
    ui.$refs.viewport.clientWidth = 600;
    onResize();
    assert.ok(ui.scale < oldScale);
    ui.zoom(0.05);
    assert.equal(ui.scale, 0.25);
    assert.equal(ui.fitMode, false);
    ui.zoom(0.05);
    assert.equal(ui.scale, 0.30);
    assert.equal(ui.width, 4864);
    assert.equal(ui.height, 512);
    const zoom = ui.scale;
    onResize();
    assert.equal(ui.scale, zoom);
    ui.applyCanvas(1080, 1920);
    assert.equal(ui.width, 1080);
    assert.equal(ui.height, 1920);
    ui.fit();
    assert.ok(ui.scale * ui.height <= 496.001);
    ui.destroy();
    assert.equal(disconnected, true);
});

test('layout preview fits without changing editor zoom and restores focus', () => {
    let closeFocused = false;
    let triggerFocused = false;
    const ui = playlistStudio();
    ui.$nextTick = fn => fn();
    ui.$refs = { closePreview: { focus() { closeFocused = true; } }, previewTrigger: { focus() { triggerFocused = true; } } };
    ui.scale = 0.75;
    ui.openPreview();
    assert.equal(ui.preview, true);
    assert.ok(ui.width * ui.previewScale <= window.innerWidth - 64);
    assert.ok(ui.height * ui.previewScale <= window.innerHeight - 144);
    assert.equal(ui.scale, 0.75);
    assert.equal(closeFocused, true);
    ui.closePreview();
    assert.equal(ui.preview, false);
    assert.equal(triggerFocused, true);
});
