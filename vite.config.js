import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { appVersion, sharedAlias, sharedExtensions, sharedPlugins } from './vite.shared.mjs';

export default defineConfig({
  server: {
    host: 'invoiceshelf.test',
    hmr: {
      host: 'invoiceshelf.test',
    }
  },
  resolve: {
    alias: sharedAlias,
    extensions: sharedExtensions
  },
  // False here is what compiles every thin-client branch out of the web bundle.
  define: {
    __INVOICESHELF_CLIENT__: 'false',
    __INVOICESHELF_CLIENT_VERSION__: JSON.stringify(appVersion())
  },
  plugins: [
    ...sharedPlugins(),
    laravel({
      input: ['resources/scripts/main.ts'],
    })
  ]
});
