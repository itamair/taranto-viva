//vite.config.ts

import { defineConfig } from 'vite';
import path from 'path';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    minify: false,
    cssMinify: false,
    rollupOptions: {
      output: {
        entryFileNames: 'index.js',
        chunkFileNames: 'assets/[name].js',
        assetFileNames: 'assets/[name].[ext]',
      },
    },
    lib: {
      entry: path.resolve(__dirname, 'src/main.jsx'),
      formats: ['es'],
      name: 'index',
    },
  },
  resolve: {
    alias: {
      '~': path.resolve(__dirname, 'src/'),
    },
  },
});
