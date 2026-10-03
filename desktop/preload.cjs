const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('macaronPos', {
  restartServer: (lanEnabled) => ipcRenderer.invoke('restart-server', lanEnabled),
  getInfo: () => ipcRenderer.invoke('shell-info'),
  printHtml: (html) => ipcRenderer.invoke('print-html', html),
});
