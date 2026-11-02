import test from 'node:test';
import assert from 'node:assert/strict';
import { adminNavigation } from '../../resources/js/admin-navigation.js';
test('sidebar preference stays per account when Alpine event element changes and mobile remains usable',()=>{
    const previousWindow=globalThis.window,previousStorage=globalThis.localStorage,values=new Map();let breakpoint;
    globalThis.localStorage={getItem:key=>values.get(key),setItem:(key,value)=>values.set(key,value)};
    globalThis.window={matchMedia:()=>({matches:true,addEventListener:(name,listener)=>breakpoint=listener,removeEventListener(){}})};
    const create=id=>{const ui={...adminNavigation(),$el:{dataset:{userId:id}},$watch(){}};Object.defineProperty(ui,'compact',{get(){return this.desktop && this.sidebarCollapsed;}});ui.init();return ui;};
    try{
        const a=create(1);a.$el={dataset:{}};a.toggleSidebar();assert.equal(values.get('cms.navigation.v1.user.1'),'collapsed');assert.equal(values.has('cms.navigation.v1.user.undefined'),false);
        assert.equal(create(2).sidebarCollapsed,false);const reloaded=create(1);assert.equal(reloaded.compact,true);
        breakpoint({matches:false});assert.equal(reloaded.compact,false);reloaded.toggleSidebar();assert.equal(reloaded.mobileOpen,true);assert.equal(values.get('cms.navigation.v1.user.1'),'collapsed');reloaded.closeDrawer();assert.equal(reloaded.mobileOpen,false);reloaded.destroy();
    }finally{globalThis.window=previousWindow;globalThis.localStorage=previousStorage;}
});
