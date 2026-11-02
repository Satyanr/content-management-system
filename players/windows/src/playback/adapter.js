export function adaptPlaylist(layout, media) {
    const assets = new Map(media.map(asset => [asset.id, asset]));
    return { id: layout.id, name: layout.name, width: layout.width, height: layout.height, background: layout.background,
        pages: layout.pages.map(page => ({ id: page.id, name: page.name, sort_order: page.sort_order, duration: page.duration, background: page.background,
            zones: page.zones.map(zone => ({ id: zone.id, name: zone.name, x: zone.x, y: zone.y, width: zone.width, height: zone.height, z_index: zone.z_index,
                visible: zone.visible, fit_mode: zone.fit_mode, items: zone.items.map(item => {
                    const asset = assets.get(item.mediaId);
                    return { id: item.id, sort_order: item.sort_order, title: item.title, type: item.type, duration: item.duration, knownDuration: item.knownDuration,
                        ...(item.counterEpoch ? {mediaId:item.mediaId,maxPlays:item.maxPlays,counterEpoch:item.counterEpoch,completedCount:item.completedCount} : {}),
                        ...(item.mediaTransform ? { mediaTransform: item.mediaTransform } : {}),
                        available: !!asset && item.available, src: asset ? 'signage-media://cache/' + asset.checksum.value : null };
                }) })) })) };
}
export function segmentAt(manifest, utc) {
    if (utc === null) return { state: 'clock_unavailable' };
    if (!manifest) return { state: 'no_package' };
    if (manifest.schemaVersion === 2) {
        const segment = utc < Date.parse(manifest.validUntilUtc) ? manifest.schedulePlan.find(s=>Date.parse(s.startUtc)<=utc && utc<Date.parse(s.endUtc)) : null;
        const id=segment?.playlistId ?? (segment ? null : manifest.fallbackPlaylistId);
        return {...(segment || {...manifest.fallbackSelection,state:id?'playing':'idle',commandId:manifest.fallbackSelection?.commandId ?? manifest.commandId,startUtc:manifest.validUntilUtc,schedule:manifest.fallbackSelection?.schedule ?? null}),playlist:manifest.playlists.find(p=>p.id===id)??null};
    }
    if (utc >= Date.parse(manifest.validUntilUtc)) return { state: 'expired' };
    const segment = manifest.schedulePlan.find(s => Date.parse(s.startUtc) <= utc && utc < Date.parse(s.endUtc));
    if (!segment) return { state: 'no_schedule' };
    return { ...segment, playlist: manifest.playlists.find(p => p.id === segment.schedule?.playlistId) ?? null };
}
export function playbackKey(segment, media) {
    if (!segment.playlist) return segment.state;
    // Ignore package horizon/revision-only changes; only the current interval/layout sources matter.
    return JSON.stringify({ schedule: segment.schedule?.id ?? null, command:segment.commandId ?? null, layout: adaptPlaylist(segment.playlist, media) },(key,value)=>key==='completedCount'?undefined:value);
}
