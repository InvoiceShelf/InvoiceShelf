import { defineConfig } from 'vite'
import { resolve } from 'node:path'
import { appVersion, repoRoot, sharedAlias, sharedExtensions, sharedPlugins } from './vite.shared.mjs'

/**
 * The thin-client build: the same SPA, packaged as a static bundle that a
 * Capacitor shell (or a plain browser, for development) serves from its own
 * origin and points at a server of the user's choosing.
 *
 * No `laravel-vite-plugin`: there is no manifest for Blade to read and no
 * server path the assets live under, which is also why `base` is relative.
 */
const CLIENT_PORT = 4173

export default defineConfig({
  root: 'resources/client',
  // Assets are loaded from the app package, never from a server path.
  base: './',
  resolve: {
    alias: sharedAlias,
    extensions: sharedExtensions,
  },
  define: {
    __INVOICESHELF_CLIENT__: 'true',
    __INVOICESHELF_CLIENT_VERSION__: JSON.stringify(appVersion()),
  },
  plugins: sharedPlugins(),
  build: {
    outDir: resolve(repoRoot, 'mobile/www'),
    emptyOutDir: true,
  },
  // A port of its own: the dev loop is this bundle talking cross-origin to a
  // server on another one, which is exactly how the shipped client behaves.
  server: {
    port: CLIENT_PORT,
    strictPort: true,
  },
  preview: {
    port: CLIENT_PORT,
    strictPort: true,
  },
})
