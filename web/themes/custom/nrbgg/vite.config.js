import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        styles: resolve(__dirname, 'src/scss/style-imports.scss'),
        mobileMenu: resolve(__dirname, 'src/js/mobile-menu.js')
      },
      output: {
        assetFileNames: 'css/[name][extname]',
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name].js'
      }
    }
  }
});