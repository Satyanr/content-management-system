import { positive } from './studio-playback.js';
import { mediaTransformStyle } from './studio-geometry.js';

export class MetadataPool {
    constructor({createVideo=()=>document.createElement('video'),timeout=8000,setTimer=(fn,ms)=>setTimeout(fn,ms),clearTimer=id=>clearTimeout(id),onResult=()=>{},onDiagnostic=()=>{}}={}) {
        Object.assign(this,{createVideo,timeout,setTimer,clearTimer,onResult,onDiagnostic});this.entries=new Map();
    }
    load(manifest) {
        for(const page of manifest.pages) for(const zone of page.zones) for(const item of zone.items) {
            if(item.type!=='video'||!item.src||!item.available||this.entries.has(item.src))continue;
            const video=this.createVideo(),entry={video,done:false,cleanup:()=>{}};this.entries.set(item.src,entry);
            const diagnostic=event=>this.onDiagnostic({event,mediaId:item.id,readyState:video.readyState,networkState:video.networkState,errorCode:video.error?.code ?? null,duration:positive(video.duration),source:video.currentSrc?'assigned':'unassigned'});
            const finish=info=>{
                if(entry.done || this.entries.get(item.src)!==entry)return;
                entry.done=true;diagnostic(info.status);entry.cleanup();this.onResult(item.src,info);
            };
            const loaded=()=>finish(positive(video.duration)?{status:'ready',duration:video.duration}:{status:'error',message:'Unknown intrinsic video duration'});
            const error=()=>finish({status:'error',message:mediaErrorMessage(video.error?.code)});
            const stalled=()=>diagnostic('stalled'),waiting=()=>diagnostic('waiting');
            const timer=this.setTimer(()=>{diagnostic('timeout');finish({status:'error',message:'Metadata timed out after '+this.timeout/1000+'s. Check the stream response and MP4 container/codec.'});},this.timeout);
            entry.cleanup=()=>{this.clearTimer(timer);video.removeEventListener('loadedmetadata',loaded);video.removeEventListener('error',error);video.removeEventListener('stalled',stalled);video.removeEventListener('waiting',waiting);video.pause();video.removeAttribute('src');video.load();};
            video.addEventListener('loadedmetadata',loaded);video.addEventListener('error',error);
            video.addEventListener('stalled',stalled);video.addEventListener('waiting',waiting);
            this.onResult(item.src,positive(item.knownDuration)?{status:'ready',duration:Number(item.knownDuration)}:{status:'loading'});
            try { video.preload='metadata';video.muted=true;video.src=item.src;video.load();diagnostic('source-assigned'); }
            catch { finish({status:'error',message:'Media source could not be loaded.'}); }
        }
    }
    destroy() {for(const entry of this.entries.values()){entry.done=true;entry.cleanup();}this.entries.clear();this.onResult=()=>{};}
}

export function mediaErrorMessage(code) {
    return ({1:'Media loading was aborted.',2:'Media network error. Check access, HTTP status and Range responses.',3:'Video decoding failed. The file may be corrupt or use an unsupported codec.',4:'Media source/codec is unsupported, or the response is not a playable video. Try MP4 H.264/AAC.'})[code] || 'Media unavailable / Unsupported codec';
}

// Owns only preview DOM. No editor node, source or currentTime is reused.
export class PlaybackRenderer {
    constructor(container,{onError=()=>{},onBlocked=()=>{},onSeeking=()=>{},onDiagnostic=()=>{},createElement=tag=>document.createElement(tag),timeout=8000}={}) {
        Object.assign(this,{container,onError,onBlocked,onSeeking,onDiagnostic,createElement,timeout});this.entries=new Map();this.seekingEntries=new Set();this.pageId=null;this.dead=false;
    }
    render(snapshot,{width,height,muted=true,volume=1,audioVersion=0}={}) {
        if(this.dead)return;
        this.options={width,height,muted,volume,audioVersion};this.snapshot=snapshot;
        if(this.pageId!==snapshot.page?.id){this.clear();this.pageId=snapshot.page?.id;}
        this.container.style.backgroundColor=snapshot.page?.background || '#000000';
        const keep=new Set();
        for(const active of snapshot.windows) {
            const zone=active.zone;keep.add(zone.id);
            let entry=this.entries.get(zone.id);
            const key=active.item?.id ?? active.state;
            if(!entry || entry.key!==key){if(entry)this.release(entry);entry=this.create(active,key);this.entries.set(zone.id,entry);}
            Object.assign(entry.box.style,{position:'absolute',left:100*zone.x/width+'%',top:100*zone.y/height+'%',width:100*zone.width/width+'%',height:100*zone.height/height+'%',zIndex:zone.z_index,overflow:'hidden'});
            entry.active=active;
            if(entry.node)Object.assign(entry.node.style,{width:'100%',height:'100%',...mediaTransformStyle(active.item?.mediaTransform,zone.fit_mode)});
            if(active.state==='error')this.fail(entry,active.message || 'Media unavailable / Unsupported codec');
            if(entry.video)this.align(entry);
        }
        for(const [id,entry] of this.entries)if(!keep.has(id)){this.release(entry);this.entries.delete(id);}
    }
    current(entry) {return !this.dead && this.entries.get(entry.zoneId)===entry;}
    create(active,key) {
        const box=this.createElement('div'),entry={box,key,zoneId:active.zone.id,active,listeners:[],failed:false,attempt:-1};
        this.container.appendChild(box);
        const item=active.item;
        if(!item || !item.src || active.state!=='ready') {
            box.textContent=active.state==='loading'?'Loading metadata':active.state==='empty'?'Empty Window':active.message || 'Media unavailable / Unknown duration';return entry;
        }
        const node=this.createElement(item.type==='video'?'video':'img');entry.node=node;box.appendChild(node);
        const listen=(name,callback)=>{node.addEventListener(name,callback);entry.listeners.push([name,callback]);};
        const diagnostic=event=>this.onDiagnostic({event,mediaId:item.id,readyState:node.readyState,networkState:node.networkState,errorCode:node.error?.code ?? null,duration:positive(node.duration),source:node.currentSrc?'assigned':'unassigned'});
        const error=()=>{if(this.current(entry) && !entry.failed){diagnostic('decode-error');const message=mediaErrorMessage(node.error?.code);this.fail(entry,message);this.onError(item.src,{status:'error',message});}};
        listen('error',error);
        entry.timer=setTimeout(()=>{if(this.current(entry)){diagnostic('frame-timeout');const message='Media loading timed out: no decoded frame. Check the video codec and stream.';this.fail(entry,message);this.onError(item.src,{status:'error',message});}},this.timeout);
        if(item.type==='video') {
            entry.video=node;node.preload='auto';node.playsInline=true;
            listen('loadedmetadata',()=>{if(this.current(entry)){diagnostic('loadedmetadata');this.align(entry,true);}});
            listen('loadeddata',()=>{if(this.current(entry)){diagnostic('loadeddata');clearTimeout(entry.timer);}});
            listen('stalled',()=>{if(this.current(entry))diagnostic('stalled');});
            listen('waiting',()=>{if(this.current(entry))diagnostic('waiting');});
            listen('seeking',()=>{if(this.current(entry)){this.seekingEntries.add(entry);this.onSeeking(true);}});
            listen('seeked',()=>{if(this.current(entry)){this.seekingEntries.delete(entry);this.onSeeking(this.seekingEntries.size>0);this.align(entry);}});
            listen('ended',()=>{if(this.current(entry)){entry.attempt=-1;this.align(entry,true);}});
            node.muted=this.options.muted;node.src=item.src;node.load();
        } else {node.alt=item.title;listen('load',()=>clearTimeout(entry.timer));node.src=item.src;}
        return entry;
    }
    fail(entry,message) {
        if(entry.failed)return;entry.failed=true;clearTimeout(entry.timer);
        this.seekingEntries.delete(entry);this.onSeeking(this.seekingEntries.size>0);
        for(const [name,callback]of entry.listeners)entry.node?.removeEventListener(name,callback);
        entry.listeners=[];
        if(entry.video){entry.video.pause();entry.video.removeAttribute('src');entry.video.load();}
        const label=this.createElement('div');label.textContent=message;Object.assign(label.style,{color:'#cbd5e1',fontSize:'12px',padding:'8px',background:'#1e293b',height:'100%'});
        entry.box.replaceChildren(label);
    }
    align(entry,force=false) {
        const video=entry.video;
        if(entry.failed || !video)return;
        const {muted,volume,audioVersion}=this.options, snapshot=this.snapshot;
        video.muted=muted;video.volume=Math.max(0,Math.min(1,volume));video.playbackRate=1;
        const duration=positive(video.duration);
        if(!duration || video.readyState<1)return;
        const target=Math.min(entry.active.localTime%duration,Math.max(0,duration-0.001));
        // A pending seek always settles to the latest clock target, never a captured old source/time.
        if(!video.seeking && Math.abs(video.currentTime-target)>(snapshot.state==='Playing' && !force && entry.seekVersion===snapshot.seekVersion?0.3:0.025)) {
            try{video.currentTime=target;}catch{ return; }
        }
        entry.seekVersion=snapshot.seekVersion;
        if(snapshot.state==='Playing') {
            if(entry.attempt!==audioVersion || (!entry.blocked && video.paused && !video.seeking)) {
                entry.attempt=audioVersion;entry.blocked=false;
                const promise=video.play();
                promise?.catch(error=>{if(this.current(entry)){
                    if(error.name==='NotAllowedError'){entry.blocked=true;this.onBlocked();}
                    else if(error.name!=='AbortError'){this.fail(entry,'Video playback failed / Unsupported codec');this.onError(entry.active.item.src);}
                }});
            }
        } else {video.pause();entry.attempt=-1;}
    }
    release(entry) {
        this.seekingEntries.delete(entry);this.onSeeking(this.seekingEntries.size>0);
        clearTimeout(entry.timer);
        for(const [name,callback]of entry.listeners)entry.node?.removeEventListener(name,callback);
        if(entry.video){entry.video.pause();entry.video.removeAttribute('src');entry.video.load();}
        entry.box.remove();
    }
    clear(){for(const entry of this.entries.values())this.release(entry);this.entries.clear();this.container.replaceChildren();}
    destroy(){this.clear();this.dead=true;}
}
