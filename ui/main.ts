/**
 * Mega Builder — Back-Office Vue 3 + TypeScript Entry Point
 */

import { createApp } from 'vue';
import App from './App.vue';
import './studio.css';
import '../views/css/storefront.css';
import type { BootPayload } from './types';

declare global {
  interface Window {
    __MEGABUILDER_BOOT__?: BootPayload;
  }
}

const mountEl = document.getElementById('megabuilder-studio-app');
if (mountEl && window.__MEGABUILDER_BOOT__) {
  const app = createApp(App, {
    boot: window.__MEGABUILDER_BOOT__,
  });
  app.mount(mountEl);
}
