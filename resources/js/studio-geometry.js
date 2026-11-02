export const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
export function canvasPoint(clientX, clientY, rect, scale) {
    return { x: (clientX - rect.left) / scale, y: (clientY - rect.top) / scale };
}
export function initialBox(point, mediaWidth, mediaHeight, canvasWidth, canvasHeight) {
    const ratio = mediaWidth > 0 && mediaHeight > 0 ? mediaWidth / mediaHeight : 16 / 9;
    const width = Math.min(mediaWidth || 640, canvasWidth * 0.4, canvasHeight * 0.4 * ratio);
    const height = width / ratio;
    const box = { width: Math.max(1, Math.round(width)), height: Math.max(1, Math.round(height)) };
    return { ...box, x: Math.round(clamp(point.x, 0, canvasWidth - box.width)), y: Math.round(clamp(point.y, 0, canvasHeight - box.height)) };
}
export function moveBox(box, dx, dy, canvas, grid = 0, threshold = 0) {
    const snap = v => grid > 0 ? Math.round(v / grid) * grid : v;
    let x = snap(box.x + dx), y = snap(box.y + dy);
    if (Math.abs(x) <= threshold) x = 0;
    if (Math.abs(y) <= threshold) y = 0;
    if (Math.abs(canvas.width - x - box.width) <= threshold) x = canvas.width - box.width;
    if (Math.abs(canvas.height - y - box.height) <= threshold) y = canvas.height - box.height;
    return { ...box, x: Math.round(clamp(x, 0, Math.max(0, canvas.width - box.width))), y: Math.round(clamp(y, 0, Math.max(0, canvas.height - box.height))) };
}
export function resizeBox(box, handle, dx, dy, canvas, keepRatio, grid = 0, threshold = 0) {
    const west = handle.includes('w'), east = handle.includes('e'), north = handle.includes('n'), south = handle.includes('s');
    const snap = (v, max) => {
        let result = grid > 0 ? Math.round(v / grid) * grid : v;
        if (Math.abs(result) <= threshold) result = 0;
        if (Math.abs(result - max) <= threshold) result = max;
        return result;
    };
    let w = west ? box.x + box.width - snap(box.x + dx, canvas.width) : east ? snap(box.x + box.width + dx, canvas.width) - box.x : box.width;
    let h = north ? box.y + box.height - snap(box.y + dy, canvas.height) : south ? snap(box.y + box.height + dy, canvas.height) - box.y : box.height;
    if (keepRatio) {
        const ratio = box.width / box.height;
        if ((east || west) && (north || south)) {
            if (Math.abs(w - box.width) / box.width >= Math.abs(h - box.height) / box.height) h = w / ratio;
            else w = h * ratio;
        } else if (east || west) h = w / ratio;
        else w = h * ratio;
        const maxW = west ? box.x + box.width : canvas.width - box.x;
        const maxH = north ? box.y + box.height : canvas.height - box.y;
        w = clamp(w, Math.max(1, ratio), Math.min(maxW, maxH * ratio));
        h = w / ratio;
    } else {
        w = clamp(w, 1, west ? box.x + box.width : canvas.width - box.x);
        h = clamp(h, 1, north ? box.y + box.height : canvas.height - box.y);
    }
    w = Math.max(1, Math.round(w)); h = Math.max(1, Math.round(h));
    return { x: west ? box.x + box.width - w : box.x, y: north ? box.y + box.height - h : box.y, width: w, height: h };
}
export function objectFit(mode) { return mode === 'stretch' ? 'fill' : mode === 'cover' ? 'cover' : 'contain'; }

// UUID is a retry key, never an authorization credential; support local HTTP development hosts too.
export function requestToken() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (globalThis.crypto?.getRandomValues) globalThis.crypto.getRandomValues(bytes);
    else for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map(v => v.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
}
