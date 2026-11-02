export function adminNavigation() {
    return { sidebarCollapsed:false, mobileOpen:false, desktop: false, breakpoint:null, breakpointListener:null, preferenceKey:null,
        get compact(){return this.desktop && this.sidebarCollapsed;},
        init(){this.preferenceKey=`cms.navigation.v1.user.${this.$el.dataset.userId}`;this.breakpoint=window.matchMedia('(min-width: 1024px)');this.desktop=this.breakpoint.matches;this.breakpointListener=event=>{this.desktop=event.matches;this.mobileOpen=false;};this.breakpoint.addEventListener('change',this.breakpointListener);this.$watch('mobileOpen',open=>{this.$nextTick(()=>{if(open)this.$refs.sidebar.querySelector('button,a')?.focus();else this.$refs.sidebarToggle?.focus();});});try{this.sidebarCollapsed=localStorage.getItem(this.preferenceKey)==='collapsed';}catch{}},
        toggleSidebar(){if(this.desktop){this.sidebarCollapsed=!this.sidebarCollapsed;try{localStorage.setItem(this.preferenceKey,this.sidebarCollapsed?'collapsed':'expanded');}catch{}}else this.mobileOpen=!this.mobileOpen;},
        destroy(){this.breakpoint?.removeEventListener('change',this.breakpointListener);},
        closeDrawer(){this.mobileOpen=false;},
        trapDrawer(event){const elements=[...this.$refs.sidebar.querySelectorAll('a,button')].filter(el=>el.getClientRects().length);if(!elements.length)return;const index=elements.indexOf(document.activeElement);event.preventDefault();elements[(index+(event.shiftKey?-1:1)+elements.length)%elements.length].focus();},
    };
}
if(typeof document!=='undefined')document.addEventListener('alpine:init',()=>window.Alpine.data('adminNavigation',adminNavigation));
