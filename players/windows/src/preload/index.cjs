const { contextBridge, ipcRenderer } = require('electron');
const invoke = (name, value) => ipcRenderer.invoke('player:' + name, value);
const subscribe = (name, callback) => {
    if (typeof callback !== 'function') throw new Error('callback_invalid');
    const listener = (_event, value) => callback(value);
    ipcRenderer.on('player:' + name, listener);
    return () => ipcRenderer.removeListener('player:' + name, listener);
};
contextBridge.exposeInMainWorld('player', Object.freeze({
    status: () => invoke('status'), activate: input => invoke('activate', input), unlock: pin => invoke('unlock', pin),
    sync: () => invoke('sync'), settings: input => invoke('settings', input), restart: () => invoke('restart'),
    reconnect: () => invoke('reconnect'),
    onState: callback => subscribe('state', callback), onOperator: callback => subscribe('operator', callback),
}));
