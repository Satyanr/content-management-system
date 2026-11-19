import { PlaybackLedger } from '../src/services/playback/ledger.js';

export function summarizeJournal(text) {
    const complete = text.slice(0, text.lastIndexOf('\n') + 1);
    const ledger = new PlaybackLedger('unused');
    for (const line of complete.split('\n').filter(Boolean)) ledger.apply(JSON.parse(line));
    return { available: true, pending: Object.keys(ledger.state.pending).length,
        activeItems: Object.values(ledger.state.open).map(s => ({ itemId: s.playlist_zone_item_id, zoneId: s.zone_id })),
        completed: Object.entries(ledger.state.counters).map(([key, count]) => ({ itemId: key.split('/')[1], count })) };
}

export function videoStalled(previous, current) {
    return !!previous && !current.paused && !current.seeking && !previous.paused
        && current.frames === previous.frames && Math.abs(current.time - previous.time) < 0.001;
}
