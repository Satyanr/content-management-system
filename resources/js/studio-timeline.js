import { PlaybackEngine, trackSegments, positive } from './studio-playback.js';
import { MetadataPool, PlaybackRenderer } from './studio-playback-renderer.js';
export function formatTime(value) {
    const seconds=Math.max(0,Math.floor(Number(value)||0));
    return (seconds>=3600?Math.floor(seconds/3600)+':':'')+String(Math.floor(seconds/60)%60).padStart(2,'0')+':'+String(seconds%60).padStart(2,'0');
}
export function studioTimeline() {
    let engine=null, pool=null, renderer=null, timelineCleanup=null, generation=0, resizeObserver=null;
    return {
        playback:{state:'Ready',time:0,total:0,page:null,pageTime:0,windows:[]}, playbackManifest:null, playbackStale:true,
        loadingPlayback:false, playbackMode:false, timelineOpen:true, tracksOpen:true, timelineZoom:30, timelineHeight:210,
        collapsedTracks:{}, timelineScroll:0, timelineViewport:1000, muted:true, volume:1, loopPlaylist:true, audioBlocked:false, audioVersion:0,
        playbackError:'', mediaSeeking:false, metadata:{}, durationDrafts:{}, fullscreenSupported:false,
        initializePlayback() {
            this.fullscreenSupported=!!globalThis.document?.fullscreenEnabled;
            if(this.$wire?.getPlaybackManifest)this.loadPlayback();
        },
        async loadPlayback() {
            if(this.loadingPlayback || this.busy || this.gesture)return false;
            this.loadingPlayback=true;this.playbackError='';const version=++generation;
            let manifest;
            const ok=await this.perform(async()=>{manifest=await this.$wire.getPlaybackManifest();return true;},{refreshForm:false});
            this.loadingPlayback=false;
            if(!ok || version!==generation || !manifest)return false;
            this.releasePlayback();engine?.destroy();
            this.metadata={};engine=new PlaybackEngine(manifest,{onChange:snapshot=>this.updatePlayback(snapshot)});
            engine.setLoop(this.loopPlaylist);this.playbackManifest=engine.manifest;this.playbackStale=false;engine.emit();this.ensureMetadata();
            const active=engine.manifest.pages.find(p=>p.id===this.pageId);if(active?.start)engine.seek(active.start);
            if(this.preview || this.playbackMode)this.$nextTick(()=>this.attachRenderer());
            return true;
        },
        invalidatePlayback() {
            this.playbackStale=true;this.stopPlayback();
            this.$nextTick(()=>{if(!this.busy && !this.loadingPlayback)this.loadPlayback();});
        },
        ensureMetadata() {
            if(pool || !engine)return;
            const owner=engine;
            pool=new MetadataPool({onResult:(src,info)=>{if(engine!==owner)return;owner.setMetadata(src,info);this.metadata={...owner.metadata};},onDiagnostic:info=>this.mediaDiagnostic(info)});
            pool.load(engine.manifest);
            if(this.mediaDiagnosticsEnabled()) {
                const sources=new Map(engine.manifest.pages.flatMap(p=>p.zones).flatMap(z=>z.items).filter(i=>i.type==='video' && i.src).map(i=>[i.src,i.id]));
                for(const [src,mediaId] of sources) fetch(src,{method:'HEAD',credentials:'same-origin',redirect:'manual',signal:AbortSignal.timeout(8000)})
                    .then(response=>this.mediaDiagnostic({event:'http',mediaId,status:response.status,mime:response.headers.get('Content-Type'),length:response.headers.get('Content-Length'),ranges:response.headers.get('Accept-Ranges')}))
                    .catch(()=>this.mediaDiagnostic({event:'http-unavailable',mediaId}));
            }
        },
        mediaDiagnosticsEnabled() {
            try { return this.$el?.dataset.playbackDebug==='1' && globalThis.localStorage?.getItem('cms.playback.debug')==='1'; } catch { return false; }
        },
        mediaDiagnostic(info) {
            if(this.mediaDiagnosticsEnabled())console.debug('[Studio media]',info);
        },
        updatePlayback(snapshot) {
            this.playback=snapshot;
            renderer?.render(snapshot,{width:engine?.manifest.width,height:engine?.manifest.height,muted:this.muted,volume:this.volume,audioVersion:this.audioVersion});
        },
        attachRenderer() {
            renderer?.destroy();renderer=null;resizeObserver?.disconnect();resizeObserver=null;
            if(!engine || (!this.preview && !this.playbackMode))return;
            const element=this.preview?this.$refs.previewPlayback:this.$refs.inlinePlayback;
            if(!element)return;
            renderer=new PlaybackRenderer(element,{onError:(src,info)=>{engine?.setMetadata(src,info ?? {status:'error',message:'Media unavailable / Unsupported codec'});this.metadata={...engine?.metadata};},onBlocked:()=>{this.audioBlocked=true;},onSeeking:value=>{this.mediaSeeking=value;},onDiagnostic:info=>this.mediaDiagnostic(info)});
            this.updatePlayback(engine.snapshot());
            if(this.preview && this.$refs.previewArea){
                resizeObserver=new ResizeObserver(()=>this.fitPlaybackPreview());resizeObserver.observe(this.$refs.previewArea);this.fitPlaybackPreview();
            }
        },
        fitPlaybackPreview() {
            const area=this.$refs.previewArea;
            if(!area || !this.playbackManifest)return;
            this.previewScale=Math.max(0.001,Math.min(Math.max(1,area.clientWidth-16)/this.playbackManifest.width,Math.max(1,area.clientHeight-16)/this.playbackManifest.height));
        },
        releasePlayback() {renderer?.destroy();renderer=null;pool?.destroy();pool=null;resizeObserver?.disconnect();resizeObserver=null;timelineCleanup?.();timelineCleanup=null;},
        destroyPlayback() {generation++;this.releasePlayback();engine?.destroy();engine=null;},
        async openPreview() {
            if(this.busy || this.gesture)return;
            if(!engine || this.playbackStale){if(!(await this.loadPlayback()))return;}
            this.ensureMetadata();engine.pause();this.editorTimelineOpen=this.timelineOpen;this.playbackMode=false;this.preview=true;this.timelineOpen=true;
            this.$nextTick(()=>{this.attachRenderer();this.$refs.closePreview.focus();});
        },
        closePreview() {
            engine?.pause();this.timelineOpen=this.editorTimelineOpen ?? false;this.preview=false;this.playbackMode=false;this.releasePlayback();
            if(globalThis.document?.fullscreenElement===this.$refs.previewDialog)document.exitFullscreen?.().catch(()=>{});
            this.$refs.previewTrigger.focus();
        },
        async playPlayback() {
            if(this.busy || this.gesture)return;
            if(!engine || this.playbackStale){if(!(await this.loadPlayback()))return;}
            this.ensureMetadata();this.audioBlocked=false;this.audioVersion++;
            if(!this.preview && !this.playbackMode){this.playbackMode=true;this.$nextTick(()=>{this.attachRenderer();engine.play();});}
            else engine.play();
        },
        pausePlayback(){engine?.pause();},
        stopPlayback(){engine?.stop();this.playbackMode=false;if(!this.preview){renderer?.destroy();renderer=null;}},
        restartPlayback(){engine?.stop();this.playPlayback();},
        seekPlayback(value){
            if(!engine || this.playbackStale)return;
            this.ensureMetadata();
            if(!this.preview && !this.playbackMode){this.playbackMode=true;this.$nextTick(()=>this.attachRenderer());}
            engine.seek(value);
        },
        pagePlayback(direction){if(!engine)return;this.seekPlayback(this.playback.time);engine.nextPage(direction);},
        updateAudio(){this.audioVersion++;this.audioBlocked=false;if(engine)this.updatePlayback(engine.snapshot());},
        setLoop(){engine?.setLoop(this.loopPlaylist);},
        formatTime,
        playbackStatus(){
            if(this.loadingPlayback)return 'Loading manifest';
            if(this.playback.windows.some(w=>w.state==='loading'))return 'Loading metadata';
            if(this.mediaSeeking)return 'Seeking';
            return this.playback.state;
        },
        async enterFullscreen(){try{await this.$refs.previewDialog.requestFullscreen();}catch{this.playbackError='Browser fullscreen is unavailable. The preview still fills the editor.';}},
        trapPreviewFocus(event) {
            const nodes=[...this.$refs.previewDialog.querySelectorAll('button:not([disabled]),input:not([disabled]),[tabindex="0"]')].filter(el=>el.getClientRects().length);
            const first=nodes[0],last=nodes.at(-1),active=document.activeElement;
            if(event.shiftKey && (active===first || !nodes.includes(active))){event.preventDefault();last?.focus();}
            else if(!event.shiftKey && (active===last || !nodes.includes(active))){event.preventDefault();first?.focus();}
        },
        editPagePlayback(id) {
            engine?.pause();this.playbackMode=false;renderer?.destroy();renderer=null;
            const page=engine?.manifest.pages.find(p=>p.id===id);if(page)engine.seek(page.start);
        },
        playbackKey(event) {
            const target=event.target;
            if(target?.closest?.('input,textarea,select,[contenteditable="true"],button,a'))return;
            if(this.busy || this.gesture)return;
            if(event.code==='Space'){event.preventDefault();this.playback.state==='Playing'?this.pausePlayback():this.playPlayback();}
            else if(event.code==='Home'){event.preventDefault();this.seekPlayback(0);}
            else if(event.code==='End'){event.preventDefault();this.seekPlayback(this.playback.total);}
            else if(event.code==='ArrowLeft'||event.code==='ArrowRight'){event.preventDefault();this.seekPlayback(this.playback.time+(event.code==='ArrowLeft'?-1:1));}
        },
        timelineScale(){return Math.min(Number(this.timelineZoom)||30,16000000/Math.max(1,this.playback.total));},
        rulerTicks(){
            const scale=this.timelineScale(),step=Math.max(1,Math.pow(10,Math.floor(Math.log10(70/scale))))*(70/scale/Math.pow(10,Math.floor(Math.log10(70/scale)))>5?10:70/scale/Math.pow(10,Math.floor(Math.log10(70/scale)))>2?5:2);
            const ticks=[];const start=Math.floor(this.timelineScroll/scale/step)*step;
            for(let t=start;t<=Math.min(this.playback.total,(this.timelineScroll+this.timelineViewport)/scale)+step;t+=step)ticks.push(t);
            return ticks;
        },
        timelineTracks(){
            const page=this.playback.page;if(!page)return [];
            const from=this.timelineScroll/this.timelineScale(),to=(this.timelineScroll+this.timelineViewport)/this.timelineScale();
            return page.zones.map(zone=>({zone,...trackSegments(zone,page,this.metadata,from,to)}));
        },
        isActiveSegment(segment,zoneId){const active=this.playback.windows.find(w=>w.zone.id===zoneId);return active?.item?.id===segment.item.id && this.playback.time>=segment.start && this.playback.time<segment.start+segment.duration;},
        fileDurationLabel(id){
            const item=this.playbackManifest?.pages.flatMap(p=>p.zones).flatMap(z=>z.items).find(i=>i.id===id);
            return item?this.durationLabel(item):'Loading duration';
        },
        durationLabel(item){
            if(!item.type && !positive(item.duration))return 'Unknown duration';
            if(item.type==='video' && item.duration===null){const info=this.metadata[item.src];return info?.status==='error'?'Auto / '+(info.message || 'Unknown'):info?.duration||item.knownDuration?'Auto / '+Number(info?.duration||item.knownDuration).toFixed(2)+'s':'Auto / Loading metadata';}
            return (item.duration??10)+'s';
        },
        timelinePointer(event) {
            if(event.button!==0 || !engine)return;
            event.preventDefault();timelineCleanup?.();
            const surface=event.currentTarget,rect=surface.getBoundingClientRect(),pointerId=event.pointerId;
            const seek=e=>this.seekPlayback((e.clientX-surface.getBoundingClientRect().left)/this.timelineScale());
            const move=e=>{if(e.pointerId===pointerId)seek(e);};
            const end=()=>{timelineCleanup?.();timelineCleanup=null;};
            window.addEventListener('pointermove',move);window.addEventListener('pointerup',end);window.addEventListener('pointercancel',end);window.addEventListener('blur',end);
            timelineCleanup=()=>{window.removeEventListener('pointermove',move);window.removeEventListener('pointerup',end);window.removeEventListener('pointercancel',end);window.removeEventListener('blur',end);};
            seek(event);
        },
        async saveDuration(zoneId,itemId,auto=false){
            const seconds=auto?null:Number(this.durationDrafts[itemId]);
            const ok=await this.perform(()=>auto?this.$wire.resetItemDurationToAuto(this.pageId,zoneId,itemId):this.$wire.updateItemDuration(this.pageId,zoneId,itemId,seconds),{refreshForm:false});
            if(ok){delete this.durationDrafts[itemId];await this.loadPlayback();}
        },
    };
}
