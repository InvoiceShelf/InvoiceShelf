import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  server: {
    host: 'invoiceshelf.test',
    hmr: {
      host: 'invoiceshelf.test',
    }
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './resources/'),
      $fonts: resolve(__dirname, './resources/static/fonts'),
      $images: resolve(__dirname, './resources/static/img')
    },
    extensions: ['.js', '.ts', '.jsx', '.tsx', '.json', '.vue', '.mjs']
  },
  plugins: [
    tailwindcss(),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
    laravel({
      input: ['resources/scripts/main.js'],
    })
  ]
});
