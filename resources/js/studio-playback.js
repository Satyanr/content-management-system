// One playlist clock; all Page/Window/item positions are derived, never independently timed.
export const positive = value => Number.isFinite(Number(value)) && Number(value) > 0 ? Number(value) : null;
export function itemDuration(item, metadata = {}) {
    if (positive(item.duration)) return Number(item.duration);
    if (item.type === 'image') return 10;
    if (item.type !== 'video') return null;
    const info = metadata[item.src];
    if (info?.status === 'error') return null;
    return positive(info?.duration) || positive(item.knownDuration);
}
export function orderedManifest(input) {
    const order = (a,b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id;
    const pages = [...(input.pages || [])].sort(order).filter(p => positive(p.duration)).map(p => ({...p, zones: [...p.zones].sort((a,b)=>a.z_index-b.z_index || a.id-b.id).map(z=>({...z,items:[...z.items].sort(order)}))}));
    let start = 0;
    return {...input, pages: pages.map(page => { const next={...page,start};start+=Number(page.duration);return next; }), totalDuration: pages.reduce((n,p)=>n+Number(p.duration),0)};
}
export function pageAt(manifest, time) {
    const pages=manifest.pages;
    if (!pages.length) return null;
    const bounded=Math.max(0,Math.min(time,Math.max(0,manifest.totalDuration-0.000001)));
    const page=pages.find(p=>bounded<p.start+Number(p.duration)) || pages.at(-1);
    return {page,localTime:bounded-page.start};
}
export function windowAt(zone, time, metadata = {}) {
    if (!zone.visible || !zone.items.length) return {zone,state:'empty',item:null};
    const pending=zone.items.filter(i=>i.type==='video' && i.available && itemDuration(i,metadata)===null && metadata[i.src]?.status!=='error');
    const sequence=zone.items.map(item=>({item,duration:itemDuration(item,metadata)})).filter(x=>x.duration!==null);
    const total=sequence.reduce((n,x)=>n+x.duration,0);
    if (!total) return {zone,state:pending.length?'loading':'error',item:null,message:zone.items.map(i=>metadata[i.src]?.message).find(Boolean) || 'Media unavailable / Unknown duration'};
    const cycle=Math.floor(Math.max(0,time)/total);
    let position=((time%total)+total)%total;
    for (const entry of sequence) {
        if (position<entry.duration) return {zone,state:entry.item.available && metadata[entry.item.src]?.status!=='error'?'ready':'error',...entry,message:metadata[entry.item.src]?.message,localTime:position,cycle};
        position-=entry.duration;
    }
    return {zone,state:'empty',item:null};
}
export function trackSegments(zone, page, metadata, from, to, limit=600) {
    const pending=zone.items.some(i=>i.type==='video' && i.available && itemDuration(i,metadata)===null && metadata[i.src]?.status!=='error');
    const sequence=zone.items.map(item=>({item,duration:itemDuration(item,metadata)})).filter(x=>x.duration!==null);
    const cycle=sequence.reduce((n,x)=>n+x.duration,0), segments=[];
    if (!cycle) return {pending,segments};
    let at=Math.max(0,Math.floor(Math.max(0,from-page.start)/cycle))*cycle;
    const end=Math.min(Number(page.duration),to-page.start);
    while (at<end && segments.length<limit) {
        for (const entry of sequence) {
            const duration=Math.min(entry.duration,Number(page.duration)-at);
            if (duration>0 && at+duration>from-page.start && at<end) segments.push({...entry,start:page.start+at,duration,key:entry.item.id+'-'+at});
            at+=entry.duration;
            if (at>=end || segments.length>=limit) break;
        }
    }
    return {pending,segments};
}
export class PlaybackEngine {
    constructor(manifest,{now=()=>performance.now(),raf=fn=>requestAnimationFrame(fn),cancel=id=>cancelAnimationFrame(id),onChange=()=>{}}={}) {
        this.manifest=orderedManifest(manifest);this.now=now;this.raf=raf;this.cancel=cancel;this.onChange=onChange;
        this.time=0;this.state='Ready';this.loop=true;this.metadata={};this.frame=null;this.seekVersion=0;
    }
    snapshot() {
        const located=pageAt(this.manifest,this.time);
        return {time:this.time,total:this.manifest.totalDuration,state:this.state,loop:this.loop,seekVersion:this.seekVersion,
            page:located?.page ?? null,pageTime:located?.localTime ?? 0,
            windows:located?located.page.zones.filter(z=>z.visible).map(z=>windowAt(z,located.localTime,this.metadata)):[]};
    }
    emit() { const result=this.snapshot();this.onChange(result);return result; }
    clock() {
        if (this.state!=='Playing') return;
        let time=this.anchorTime+(this.now()-this.anchorNow)/1000;
        const total=this.manifest.totalDuration;
        if (!total) {this.time=0;this.state='Ended';return;}
        if (time>=total) {
            if (this.loop) {time%=total;this.anchorTime=time;this.anchorNow=this.now();this.seekVersion++;}
            else {time=total;this.state='Ended';}
        }
        this.time=Math.max(0,time);
    }
    step() {this.clock();return this.emit();}
    schedule() {
        if (this.frame!==null || this.state!=='Playing') return;
        this.frame=this.raf(()=>{this.frame=null;this.step();this.schedule();});
    }
    clearFrame() {if(this.frame!==null)this.cancel(this.frame);this.frame=null;}
    play() {
        if (this.state==='Playing') {this.emit();return;}
        if (this.time>=this.manifest.totalDuration){this.time=0;this.seekVersion++;}
        this.anchorTime=this.time;this.anchorNow=this.now();this.state='Playing';this.emit();this.schedule();
    }
    pause() {this.clock();this.clearFrame();if(this.state!=='Ended')this.state='Paused';this.emit();}
    stop() {this.clearFrame();this.time=0;this.state='Ready';this.seekVersion++;this.emit();}
    seek(time) {
        const running=this.state==='Playing';this.clearFrame();this.state='Seeking';this.emit();
        this.time=Math.max(0,Math.min(Number(time)||0,this.manifest.totalDuration));this.anchorTime=this.time;this.anchorNow=this.now();this.seekVersion++;
        this.state=running && this.time<this.manifest.totalDuration?'Playing':this.time===this.manifest.totalDuration?'Ended':'Paused';
        this.emit();this.schedule();
    }
    nextPage(direction=1) {
        const current=pageAt(this.manifest,this.time)?.page;
        const index=this.manifest.pages.findIndex(p=>p.id===current?.id);
        const next=this.manifest.pages[index+direction];
        this.seek(next?next.start:direction<0?0:this.loop?0:this.manifest.totalDuration);
    }
    setLoop(loop) {this.loop=!!loop;this.emit();}
    setMetadata(src,info) {this.metadata={...this.metadata,[src]:info};this.emit();}
    destroy() {this.clearFrame();this.onChange=()=>{};}
}
