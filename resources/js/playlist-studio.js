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
        width: 1920, height: 1080, scale: 0.35, fitMode: true,
        leftWidth: 220, rightWidth: 300, showPages: true, showProperties: true,
        tab: 'library', preview: false, previewScale: 0.35, settings: false, dirty: false,
        init() {
            this.width = Number(this.$el.dataset.width);
            this.height = Number(this.$el.dataset.height);
            observer = new ResizeObserver(() => { if (this.fitMode) this.fit(); this.fitPreview(); });
            observer.observe(this.$refs.viewport);
            this.$nextTick(() => this.fit());
        },
        destroy() { observer?.disconnect(); },
        zoom(delta) {
            this.fitMode = false;
            this.scale = clampZoom(Math.round((this.scale + delta) * 100) / 100);
        },
        fit() {
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
            this.previewScale = fitScale(this.width, this.height, window.innerWidth, window.innerHeight - 80, 64);
        },
        openPreview() {
            this.preview = true;
            this.fitPreview();
            this.$nextTick(() => this.$refs.closePreview.focus());
        },
        closePreview() {
            this.preview = false;
            this.$refs.previewTrigger.focus();
        },
        cycleTab(direction) {
            const tabs = ['library', 'windows', 'properties'];
            this.tab = tabs[(tabs.indexOf(this.tab) + direction + tabs.length) % tabs.length];
            this.$nextTick(() => this.$el.querySelector('#tab-' + this.tab).focus());
        },
        async selectPage(id) {
            if (this.dirty && !window.confirm('Switch page? Unsaved page properties will be discarded.')) return;
            await this.$wire.selectPage(id);
        },
        async addPage() {
            if (this.dirty && !window.confirm('Add page? Unsaved page properties will be discarded.')) return;
            await this.$wire.addPage();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('playlistStudio', playlistStudio);
});
