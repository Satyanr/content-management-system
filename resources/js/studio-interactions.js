import { clamp, canvasPoint, initialBox, moveBox, resizeBox, objectFit, requestToken } from './studio-geometry.js';
const copy = value => JSON.parse(JSON.stringify(value));
const geometry = zone => ({ x: zone.x, y: zone.y, width: zone.width, height: zone.height });

export function studioInteractions() {
    let cleanup = null;
    let lastData = null;
    let unhook = null;
    let rejectRequest = null;
    return {
        pageId: null, zones: {}, selectedZoneId: null, zoneForm: null, zoneDirty: false,
        canEdit: false, canMedia: false, busy: false, error: '', dragMedia: null, dropGhost: null,
        gesture: null, snapEdges: true, gridEnabled: false, gridSize: 10, extentX: 0, extentY: 0,
        initializeInteractions() {
            unhook?.();
            unhook = window.Livewire?.hook('commit', ({ component, fail }) => {
                if (component.id !== this.$wire?.$id) return;
                fail(() => rejectRequest?.(new Error('Livewire request failed')));
            });
            const data = this.$refs.editorData?.textContent;
            if (data) this.syncEditor(JSON.parse(data));
        },
        cleanupInteractions() { cleanup?.(); cleanup = null; this.gesture = null; this.dropGhost = null; },
        destroyInteractions() {
            this.cleanupInteractions();
            unhook?.(); unhook = null;
            rejectRequest?.(new Error('Editor closed'));
        },
        syncEditor(data) {
            lastData = copy(data);
            if (this.gesture && this.pageId === data.pageId) return;
            if (this.pageId !== data.pageId) {
                this.cleanupInteractions();
                this.selectedZoneId = null; this.zoneDirty = false; this.zoneForm = null;
            }
            this.pageId = data.pageId; this.canEdit = data.canEdit; this.canMedia = data.canMedia;
            this.extentX = data.extentX; this.extentY = data.extentY;
            this.zones = Object.fromEntries(data.zones.map(z => [z.id, z]));
            if (this.selectedZoneId && !this.zones[this.selectedZoneId]) { this.selectedZoneId = null; this.zoneForm = null; this.zoneDirty = false; }
            if (this.selectedZoneId && !this.zoneDirty) this.zoneForm = copy(this.zones[this.selectedZoneId]);
        },
        requestSelection(id) {
            this.selectedZoneId = id; this.zoneDirty = false;
            if (this.zones[id]) this.zoneForm = copy(this.zones[id]);
            this.tab = 'properties'; this.showProperties = true;
        },
        selectWindow(id) {
            if (this.busy || this.gesture) return;
            if (this.zoneDirty && id !== this.selectedZoneId && !window.confirm('Discard unsaved Window properties?')) return;
            if (this.zoneDirty && id === this.selectedZoneId) return;
            this.selectedZoneId = id; this.zoneForm = this.zones[id] ? copy(this.zones[id]) : null; this.zoneDirty = false;
            if (id) { this.tab = 'properties'; this.showProperties = true; }
        },
        async perform(action, { refreshForm = true } = {}) {
            if (this.busy) return false;
            this.busy = true; this.error = '';
            try {
                // Livewire does not reject action promises on HTTP failure; use its scoped commit failure hook.
                const failed = new Promise((_, reject) => { rejectRequest = reject; });
                const result = await Promise.race([Promise.resolve().then(action), failed]);
                if (result === true && refreshForm) this.zoneDirty = false;
                if (lastData) this.syncEditor(lastData);
                return result === true;
            } catch (e) {
                this.error = 'The change could not be saved. Check your connection, permissions and current workspace.';
                if (lastData) this.syncEditor(lastData);
                return false;
            } finally {
                rejectRequest = null; this.busy = false;
                if(this.playbackStale && this.playbackManifest && !this.loadingPlayback)this.$nextTick(()=>this.loadPlayback());
            }
        },
        windowStyle(id) {
            const z = this.zones[id];
            return z ? { left: z.x + 'px', top: z.y + 'px', width: z.width + 'px', height: z.height + 'px', zIndex: z.z_index, display: z.is_visible ? '' : 'none', outline: this.selectedZoneId === id ? `${2 / this.scale}px solid #22d3ee` : '1px solid #64748b' } : {};
        },
        fitMedia(id) { return { objectFit: objectFit(this.zones[id]?.fit_mode), width: '100%', height: '100%' }; },
        startMedia(event, media) {
            if (!this.canEdit || !this.canMedia || this.busy) { event.preventDefault(); return; }
            this.dragMedia = media;
            event.dataTransfer.setData('application/x-studio-media', JSON.stringify(media));
            event.dataTransfer.effectAllowed = 'copy';
        },
        dragOver(event) {
            if (!this.canEdit || !this.canMedia || this.busy || !this.dragMedia) return;
            event.preventDefault(); event.dataTransfer.dropEffect = 'copy';
            const point = canvasPoint(event.clientX, event.clientY, this.$refs.canvas.getBoundingClientRect(), this.scale);
            this.dropGhost = initialBox(point, this.dragMedia.width, this.dragMedia.height, this.width, this.height);
        },
        endMedia() { this.dragMedia = null; this.dropGhost = null; },
        async dropMedia(event) {
            event.preventDefault();
            if (this.busy || !this.dragMedia || !this.dropGhost || !this.pageId) { this.endMedia(); return; }
            const mediaId = this.dragMedia.id, box = copy(this.dropGhost), page = this.pageId;
            this.endMedia();
            await this.perform(() => this.$wire.dropMedia(page, mediaId, box, requestToken()));
        },
        async addMediaToSelected(id) {
            if (!this.selectedZoneId) return;
            await this.perform(() => this.$wire.addWindowMedia(this.pageId, this.selectedZoneId, id, requestToken()), { refreshForm: false });
        },
        startWindow(event, id, handle = null) {
            if (event.button !== 0 || this.busy || this.preview) return;
            if (this.zoneDirty) { this.error = 'Save or discard Window property edits before dragging.'; return; }
            this.selectWindow(id);
            const zone = this.zones[id];
            if (!zone || !this.canEdit || zone.is_locked || this.selectedZoneId !== id) return;
            if (zone.width > this.width || zone.height > this.height || zone.x + zone.width > this.width || zone.y + zone.height > this.height) {
                this.error = 'This Window is outside the canvas. Enlarge the canvas or correct its numeric geometry first.'; return;
            }
            event.preventDefault(); event.stopPropagation();
            this.zoneDirty = false;
            this.gesture = { type: 'window', id, handle, pageId: this.pageId, box: geometry(zone), startX: event.clientX, startY: event.clientY, scale: this.scale };
            this.listenPointer(event);
        },
        startCanvas(event, handle) {
            if (!this.canEdit || this.busy || event.button !== 0) return;
            event.preventDefault(); event.stopPropagation();
            this.gesture = { type: 'canvas', handle, box: { width: this.width, height: this.height }, startX: event.clientX, startY: event.clientY, scale: this.scale };
            this.listenPointer(event);
        },
        listenPointer(event) {
            cleanup?.();
            const pointerId = event.pointerId;
            const move = e => { if (e.pointerId === pointerId) this.pointerMove(e); };
            const up = e => { if (e.pointerId === pointerId) this.finishPointer(false); };
            const cancel = e => { if (e.pointerId === pointerId) this.finishPointer(true); };
            const blur = () => this.finishPointer(true);
            window.addEventListener('blur', blur);
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', cancel);
            cleanup = () => {
                window.removeEventListener('blur', blur);
                window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', cancel);
            };
        },
        pointerMove(event) {
            const g = this.gesture; if (!g) return;
            const dx = (event.clientX - g.startX) / g.scale, dy = (event.clientY - g.startY) / g.scale;
            if (g.type === 'canvas') {
                if (g.handle.includes('e')) this.width = Math.round(clamp(g.box.width + dx, 64, 16384));
                if (g.handle.includes('s')) this.height = Math.round(clamp(g.box.height + dy, 64, 16384));
                return;
            }
            const z = this.zones[g.id]; if (!z) return;
            const grid = this.gridEnabled ? Math.max(1, Number(this.gridSize) || 10) : 0;
            const threshold = this.snapEdges ? 6 / g.scale : 0;
            const box = g.handle ? resizeBox(g.box, g.handle, dx, dy, this, z.keep_aspect_ratio, grid, threshold) : moveBox(g.box, dx, dy, this, grid, threshold);
            Object.assign(z, box); this.zoneForm = copy(z);
        },
        async finishPointer(cancelled) {
            const g = this.gesture; if (!g) return;
            cleanup?.(); cleanup = null; this.gesture = null;
            if (cancelled) {
                if (g.type === 'canvas') this.applyCanvas(g.box.width, g.box.height);
                if (lastData) this.syncEditor(lastData);
                return;
            }
            if (g.type === 'canvas') {
                const w = this.width, h = this.height;
                const confirmed = this.confirmCanvas(w, h);
                if (!confirmed.ok) { this.applyCanvas(g.box.width, g.box.height); return; }
                const saved = await this.perform(() => this.$wire.resizeCanvas(w, h, confirmed.overflow), { refreshForm: false });
                if (!saved) this.applyCanvas(g.box.width, g.box.height);
            } else {
                const box = geometry(this.zones[g.id]);
                if (JSON.stringify(box) !== JSON.stringify(g.box)) await this.perform(() => this.$wire.updateWindow(g.pageId, g.id, box));
            }
        },
        confirmCanvas(width, height) {
            const overflow = width < this.extentX || height < this.extentY;
            return { overflow, ok: !overflow || window.confirm('Some Windows on this Playlist will be outside the smaller canvas. Keep their original positions and sizes?') };
        },
        async saveAll() {
            if (this.busy || this.gesture) return;
            if (this.zoneDirty && !(await this.saveWindow())) return;
            await this.saveProperties();
        },
        async saveProperties() {
            if (this.gesture || this.busy) return;
            const result = this.confirmCanvas(Number(this.$wire.canvasWidth), Number(this.$wire.canvasHeight));
            if (result.ok) await this.perform(() => this.$wire.save(result.overflow), { refreshForm: false });
        },
        changeDimension(axis) {
            if (!this.zoneForm) return;
            const original = this.zones[this.selectedZoneId];
            if (this.zoneForm.keep_aspect_ratio && original) {
                const ratio = original.width / original.height;
                if (axis === 'width') this.zoneForm.height = Math.max(1, Math.round(Number(this.zoneForm.width) / ratio));
                else this.zoneForm.width = Math.max(1, Math.round(Number(this.zoneForm.height) * ratio));
            }
            this.zoneDirty = true;
        },
        async saveWindow() {
            if (!this.zoneForm) return;
            const fields = ['name','x','y','width','height','keep_aspect_ratio','fit_mode','z_index','is_visible','is_locked'];
            const data = Object.fromEntries(fields.map(key => [key, this.zoneForm[key]]));
            return this.perform(() => this.$wire.updateWindow(this.pageId, this.selectedZoneId, data));
        },
        async deleteWindow(id) {
            if (!window.confirm('Delete this Window and its items? Source media files will be kept.')) return;
            await this.perform(() => this.$wire.deleteWindow(this.pageId, id));
        },
        async layerWindow(id, direction) { await this.perform(() => this.$wire.changeWindowLayer(this.pageId, id, direction), { refreshForm: false }); },
        async removeItem(id, itemId) { await this.perform(() => this.$wire.removeWindowMedia(this.pageId, id, itemId), { refreshForm: false }); },
        async moveItem(id, itemId, offset) {
            const ids = this.zones[id].items.map(item => item.id), from = ids.indexOf(itemId), to = from + offset;
            if (to < 0 || to >= ids.length) return;
            [ids[from], ids[to]] = [ids[to], ids[from]];
            await this.perform(() => this.$wire.reorderWindowMedia(this.pageId, id, ids), { refreshForm: false });
        },
    };
}
