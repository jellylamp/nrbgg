import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    outDir: 'build',
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/scss/style-imports.scss'),
      output: {
        assetFileNames: 'css/[name][extname]'
      }
    }
  }
});