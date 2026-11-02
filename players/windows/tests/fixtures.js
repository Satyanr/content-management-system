import { createHash } from 'node:crypto';
export const publicId = '00000000-0000-4000-8000-000000000001';
export const token = 'dvc_' + 'a'.repeat(32) + '.' + 'b'.repeat(64);
export const bytes = Buffer.from('test-image-bytes');
export const hash = createHash('sha256').update(bytes).digest('hex');
export function manifest({ now = Date.now(), revision = 'a'.repeat(64), scheduled = true } = {}) {
    const start = new Date(now - 60000).toISOString(), end = new Date(now + 3600000).toISOString();
    const schedule = { id: 1, playlistId: 1, name: 'Lobby', priority: 0, type: 'once', timezone: 'Asia/Jakarta', startsAtUtc: start, endsAtUtc: end, startDate: null, endDate: null, startTime: null, endTime: null, weekdays: null };
    const asset = { id: 1, type: 'image', mimeType: 'image/png', size: bytes.length, checksum: { algorithm: 'sha256', value: hash }, available: true, downloadUrl: '/api/device/media/1' };
    const layout = { id: 1, name: 'Lobby', width: 1920, height: 1080, background: '#112233', totalDuration: 15, pages: [{ id: 1, name: 'Page 1', sort_order: 1, duration: 15, background: '#112233', zones: [{ id: 1, name: 'Window', x: 0, y: 0, width: 1920, height: 1080, z_index: 0, visible: true, fit_mode: 'contain', keep_aspect_ratio: true, items: [{ id: 1, sort_order: 1, mediaId: 1, title: 'Image', type: 'image', duration: 10, durationMode: 'manual', knownDuration: null, available: true }] }] }] };
    return { schemaVersion: 1, revision, serverTimeUtc: new Date(now).toISOString(), state: scheduled ? 'scheduled' : 'no_schedule',
        device: { publicId, name: 'Test Screen', platform: 'windows', playerVersion: 'test', capabilities: { reported: false, mediaTypes: ['image', 'video'] } }, output: { width: 1920, height: 1080, orientation: 'landscape' },
        currentSchedule: scheduled ? schedule : null, currentPlaylistId: scheduled ? 1 : null, nextTransitionUtc: null, planStartUtc: start, validUntilUtc: end,
        schedulePlan: [{ startUtc: start, endUtc: end, state: scheduled ? 'scheduled' : 'no_schedule', schedule: scheduled ? schedule : null }], playlists: scheduled ? [layout] : [], media: scheduled ? [asset] : [], pollIntervalSeconds: 60 };
}
