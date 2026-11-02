import { studioTimeline } from './studio-timeline.js';
import { studioInteractions } from './studio-interactions.js';
// Zoom is editor state only; canvas and zone data remain in output pixels.
export function clampZoom(value) {
    return Math.min(2, Math.max(0.25, Number(value) || 0.25));
}

export function fitScale(width, height, availableWidth, availableHeight, padding = 64) {
    if (![width, height, availableWidth, availableHeight].every(v => Number.isFinite(v) && v > 0)) return 0.25;
    // Fit may go below 25% for large output resolutions; manual zoom stays at 25-200%.
    return Math.max(0.001, Math.min(2, Math.max(1, availableWidth - padding) / width, Math.max(1, availableHeight - padding) / height));
}

export function playlistStudio() {
    let observer;
    return {
        ...studioInteractions(),
        ...studioTimeline(),
        width: 1920, height: 1080, scale: 0.35, fitMode: true,
        leftWidth: 220, rightWidth: 300, showPages: true, showProperties: true,
        tab: 'library', preview: false, previewScale: 0.35, settings: false, dirty: false,
        init() {
            this.width = Number(this.$el.dataset.width);
            this.height = Number(this.$el.dataset.height);
            this.initializeInteractions();
            this.initializePlayback();
            observer = new ResizeObserver(() => { if (this.fitMode && !this.gesture) this.fit(); this.fitPreview(); });
            observer.observe(this.$refs.viewport);
            this.$nextTick(() => this.fit());
        },
        destroy() { observer?.disconnect(); this.destroyInteractions(); this.destroyPlayback(); },
        zoom(delta) {
            if (this.gesture) return;
            this.fitMode = false;
            this.scale = clampZoom(Math.round((this.scale + delta) * 100) / 100);
        },
        fit() {
            if (this.gesture) return;
            this.fitMode = true;
            const viewport = this.$refs.viewport;
            this.scale = fitScale(this.width, this.height, viewport.clientWidth, viewport.clientHeight);
        },
        applyCanvas(width, height) {
            this.width = width;
            this.height = height;
            if (this.fitMode) this.$nextTick(() => this.fit());
            this.fitPreview();
        },
        fitPreview() {
            if(this.preview && this.$refs.previewArea){this.fitPlaybackPreview();return;}
            this.previewScale = fitScale(this.width, this.height, window.innerWidth, window.innerHeight - 80, 64);
        },
        cycleTab(direction) {
            const tabs = ['library', 'windows', 'properties'];
            this.tab = tabs[(tabs.indexOf(this.tab) + direction + tabs.length) % tabs.length];
            this.$nextTick(() => this.$el.querySelector('#tab-' + this.tab).focus());
        },
        async selectPage(id) {
            if (this.busy || this.gesture) return;
            if ((this.dirty || this.zoneDirty) && !window.confirm('Switch page? Unsaved page/Window properties will be discarded.')) return;
            const ok=await this.perform(async () => { await this.$wire.selectPage(id); return true; }, { refreshForm: false });
            if(ok)this.editPagePlayback(id);return ok;
        },
        async addPage() {
            if (this.busy || this.gesture) return;
            if ((this.dirty || this.zoneDirty) && !window.confirm('Add page? Unsaved page/Window properties will be discarded.')) return;
            return this.perform(async () => { await this.$wire.addPage(); return true; }, { refreshForm: false });
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('playlistStudio', playlistStudio);
});
