import { positive } from './studio-playback.js';
import { objectFit } from './studio-geometry.js';

export class MetadataPool {
    constructor({createVideo=()=>document.createElement('video'),timeout=8000,setTimer=setTimeout,clearTimer=clearTimeout,onResult=()=>{}}={}) {
        Object.assign(this,{createVideo,timeout,setTimer,clearTimer,onResult});this.entries=new Map();
    }
    load(manifest) {
        for(const page of manifest.pages) for(const zone of page.zones) for(const item of zone.items) {
            if(item.type!=='video'||!item.src||!item.available||this.entries.has(item.src))continue;
            const video=this.createVideo(),entry={video,done:false};this.entries.set(item.src,entry);
            const finish=info=>{
                if(entry.done || this.entries.get(item.src)!==entry)return;
                entry.done=true;entry.cleanup();this.onResult(item.src,info);
            };
            const loaded=()=>finish(positive(video.duration)?{status:'ready',duration:video.duration}:{status:'error',message:'Unknown intrinsic video duration'});
            const error=()=>finish({status:'error',message:'Media unavailable / Unsupported codec'});
            const timer=this.setTimer(()=>finish({status:'error',message:'Metadata timed out / Unknown duration'}),this.timeout);
            entry.cleanup=()=>{this.clearTimer(timer);video.removeEventListener('loadedmetadata',loaded);video.removeEventListener('error',error);video.pause();video.removeAttribute('src');video.load();};
            video.addEventListener('loadedmetadata',loaded);video.addEventListener('error',error);
            this.onResult(item.src,positive(item.knownDuration)?{status:'ready',duration:Number(item.knownDuration)}:{status:'loading'});
            video.preload='metadata';video.muted=true;video.src=item.src;video.load();
        }
    }
    destroy() {for(const entry of this.entries.values()){entry.done=true;entry.cleanup();}this.entries.clear();this.onResult=()=>{};}
}

// Owns only preview DOM. No editor node, source or currentTime is reused.
export class PlaybackRenderer {
    constructor(container,{onError=()=>{},onBlocked=()=>{},onSeeking=()=>{},createElement=tag=>document.createElement(tag),timeout=8000}={}) {
        Object.assign(this,{container,onError,onBlocked,onSeeking,createElement,timeout});this.entries=new Map();this.seekingEntries=new Set();this.pageId=null;this.dead=false;
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
            if(entry.node)Object.assign(entry.node.style,{width:'100%',height:'100%',objectFit:objectFit(zone.fit_mode)});
            if(active.state==='error')this.fail(entry,'Media unavailable / Unsupported codec');
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
            box.textContent=active.state==='loading'?'Loading metadata':active.state==='empty'?'Empty Window':'Media unavailable / Unknown duration';return entry;
        }
        const node=this.createElement(item.type==='video'?'video':'img');entry.node=node;box.appendChild(node);
        const listen=(name,callback)=>{node.addEventListener(name,callback);entry.listeners.push([name,callback]);};
        const error=()=>{if(this.current(entry) && !entry.failed){this.fail(entry,'Media unavailable / Unsupported codec');this.onError(item.src);}};
        listen('error',error);
        entry.timer=setTimeout(()=>{if(this.current(entry)){this.fail(entry,'Media loading timed out');this.onError(item.src);}},this.timeout);
        if(item.type==='video') {
            entry.video=node;node.preload='auto';node.playsInline=true;
            listen('loadedmetadata',()=>{if(this.current(entry))this.align(entry,true);});
            listen('loadeddata',()=>{if(this.current(entry))clearTimeout(entry.timer);});
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
