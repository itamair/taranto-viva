//vite.config.ts

import { defineConfig, loadEnv } from 'vite';
import path from 'path';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {

  const env = loadEnv(mode, process.cwd(), '');
  return {
    plugins: [react()],
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
        entry: path. resolve(__dirname, 'src/main.jsx'),
        formats: ['es'],
        name: 'index',
      },
    },
    resolve: {
      alias: {
        '~': path.resolve(__dirname, 'src/'),
      },
    },
    define: {
      ...Object.keys(env).reduce((prev, key) => {
        prev[`process.env.${key}`] = JSON.stringify(env[key]);
        return prev;
      }, {} as Record<string, string>),
    },
  }

});
