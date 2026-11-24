import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [vue()],
  build: {
    outDir: resolve(__dirname, 'views/dist'),
    emptyOutDir: true,
    cssCodeSplit: false,
    rollupOptions: {
      input: resolve(__dirname, 'ui/main.ts'),
      output: {
        format: 'es',
        entryFileNames: 'studio.js',
        chunkFileNames: 'studio-[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'studio.css';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
});
