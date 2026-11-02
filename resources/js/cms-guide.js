export function guidePreference(userId, version, local, session) {
    const key = `cms.guide.v${version}.user.${userId}`;
    const get = (store, name) => { try { return store?.getItem(name); } catch { return null; } };
    const set = (store, name, value) => { try { store?.setItem(name,value); } catch {} };
    return { key, hidden:()=>get(local,key)==='hidden', shouldOpen: () => get(local,key)!=='hidden' && get(session,key)!=='seen',
        seen: () => set(session,key,'seen'), remember(hidden){if(hidden)set(local,key,'hidden');else try{local?.removeItem(key);}catch{}} };
}
export function browserStorage(name) { try { return globalThis[name]; } catch { return null; } }
export function cmsGuide() {
    let preference, previousFocus, inert = new Map();
    return { open:false, never:false,
        init() { preference=guidePreference(this.$el.dataset.userId,1,browserStorage('localStorage'),browserStorage('sessionStorage')); if(preference.shouldOpen())this.show(); },
        show() {
            if(this.open)return;this.never=preference.hidden();previousFocus=document.activeElement;this.open=true;
            for(const child of document.body.children) if(child!==this.$el && !child.contains(this.$el)){inert.set(child,child.inert);child.inert=true;}
            this.$nextTick(()=>this.$refs.start.focus());
        },
        close(markSeen = true) { if(this.open && markSeen)preference.seen();this.open=false;for(const [node,value] of inert)node.inert=value;inert.clear();previousFocus?.focus?.(); },
        finish() { preference.remember(this.never);preference.seen();this.close(); },
        trap(event) {
            const items=[...this.$refs.dialog.querySelectorAll('button,a,input')].filter(el=>!el.disabled && el.getClientRects().length);
            if(!items.length)return;const current=items.indexOf(document.activeElement),next=(current+(event.shiftKey?-1:1)+items.length)%items.length;
            event.preventDefault();items[next].focus();
        },
        destroy(){this.close(false);},
    };
}
if(typeof document!=='undefined')document.addEventListener('alpine:init',()=>window.Alpine.data('cmsGuide',cmsGuide));
