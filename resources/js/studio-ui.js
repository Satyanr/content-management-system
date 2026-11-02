import { browserStorage } from './cms-guide.js';
import { mediaTransformStyle, initialBox, requestToken } from './studio-geometry.js';
export function studioPreferences(userId, storage) {
    const key=`cms.studio.ui.v1.user.${userId}`;
    return { load(){try{const value=JSON.parse(storage?.getItem(key)||'{}');return value && typeof value==='object' && !Array.isArray(value)?value:{};}catch{return {};}}, save(value){try{storage?.setItem(key,JSON.stringify(value));}catch{}} };
}
export function studioUi() {
    return { advanced:false, selectedMediaId:null, mediaForm:null, mediaDirty:false,
        initializeStudioUi(){
            const prefs=studioPreferences(this.$el.dataset.userId,browserStorage('localStorage')),saved=prefs.load();
            this.timelineOpen=saved.timelineOpen===true;this.advanced=saved.advanced===true;
            for(const name of ['showPages','showProperties'])if(typeof saved[name]==='boolean')this[name]=saved[name];
            this.leftWidth=Math.min(300,Math.max(200,Number(saved.leftWidth)||250));this.rightWidth=Math.min(360,Math.max(260,Number(saved.rightWidth)||300));
            const persist=()=>{if(!this.preview)prefs.save(Object.fromEntries(['timelineOpen','advanced','showPages','showProperties','leftWidth','rightWidth'].map(key=>[key,this[key]])));};
            for(const key of ['timelineOpen','advanced','showPages','showProperties','leftWidth','rightWidth'])this.$watch?.(key,persist);
        },
        syncMediaSelection(){
            const items=this.zones[this.selectedZoneId]?.items ?? [];
            if(!items.some(i=>i.id===this.selectedMediaId)){this.selectedMediaId=items[0]?.id ?? null;this.mediaDirty=false;}
            if(!this.mediaDirty)this.mediaForm=this.selectedMediaId ? {...items.find(i=>i.id===this.selectedMediaId)?.mediaTransform} : null;
        },
        selectMedia(id){
            if(!this.zones[this.selectedZoneId]?.items.some(item=>item.id===Number(id)))return;
            if(this.mediaDirty && id!==this.selectedMediaId && !window.confirm('Buang perubahan isi media yang belum disimpan?'))return;
            this.selectedMediaId=Number(id);this.mediaDirty=false;this.syncMediaSelection();
        },
        selectWindowMedia(zoneId,itemId){if(this.selectWindow(zoneId)!==false)this.selectMedia(itemId);},
        editorItem(id){const items=this.zones[id]?.items ?? [];return items.find(item=>id===this.selectedZoneId && item.id===this.selectedMediaId) ?? items[0];},
        editorMediaStyle(id){const item=this.editorItem(id),transform=id===this.selectedZoneId && this.mediaForm ? this.mediaForm : item?.mediaTransform;return {...mediaTransformStyle(transform,this.zones[id]?.fit_mode),width:'100%',height:'100%'};},
        resetMedia(){if(!this.mediaForm)return;this.mediaForm={scale:1,offset_x:0,offset_y:0,fit_mode:'contain',keep_aspect_ratio:true};this.mediaDirty=true;},
        mediaFitChanged(){this.mediaForm.keep_aspect_ratio=this.mediaForm.fit_mode!=='stretch';this.mediaDirty=true;},
        async saveMedia(){
            if(!this.mediaForm || !this.selectedMediaId)return true;
            const ok=await this.perform(()=>this.$wire.updateMediaTransform(this.pageId,this.selectedZoneId,this.selectedMediaId,{...this.mediaForm}),{refreshForm:false});
            if(ok){this.mediaDirty=false;this.syncMediaSelection();}return ok;
        },
        async createWindow(){if(this.busy || this.gesture)return;await this.perform(()=>this.$wire.createWindow(this.pageId),{refreshForm:false});},
        async createMediaWindow(media){if(this.busy || this.gesture)return;const box=initialBox({x:0,y:0},media.width,media.height,this.width,this.height);await this.perform(()=>this.$wire.dropMedia(this.pageId,media.id,box,requestToken()),{refreshForm:false});},
        windowPreset(preset){
            if(!this.zoneForm || this.zones[this.selectedZoneId]?.is_locked)return;
            const half=Math.floor(this.width/2);
            const box=preset==='full'?{x:0,y:0,width:this.width,height:this.height}:preset==='left'?{x:0,y:0,width:half,height:this.height}:preset==='right'?{x:half,y:0,width:this.width-half,height:this.height}:{x:Math.round(this.width/4),y:Math.round(this.height/4),width:half,height:Math.floor(this.height/2)};
            Object.assign(this.zoneForm,box,{keep_aspect_ratio:false});this.zoneDirty=true;
        },
        mediaShortcut(event){
            if(event.target?.closest?.('input,select,textarea,[contenteditable="true"]') || !event.altKey || !this.canEdit)return;
            if(event.key.toLowerCase()==='r' || event.key.toLowerCase()==='f'){event.preventDefault();this.resetMedia();}
        },
    };
}
