import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

/**
 * Everything the web build and the thin-client build must agree on.
 *
 * The two targets compile the same `resources/scripts` tree from different
 * shells, so an alias or a plugin option that drifts between them is a bug
 * that only shows up in one of the two bundles.
 */

/** Repository root, so both configs resolve paths from one place. */
export const repoRoot = dirname(fileURLToPath(import.meta.url))

/** Import aliases the source tree relies on. */
export const sharedAlias = {
  '@': resolve(repoRoot, './resources/'),
  $fonts: resolve(repoRoot, './resources/static/fonts'),
  $images: resolve(repoRoot, './resources/static/img'),
}

/** Extensions resolved without being spelled out in an import. */
export const sharedExtensions = ['.js', '.ts', '.jsx', '.tsx', '.json', '.vue', '.mjs']

/**
 * Tailwind and Vue, configured identically for both targets. Returns fresh
 * plugin instances because a Vite plugin object belongs to one build.
 */
export function sharedPlugins() {
  return [
    tailwindcss(),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ]
}

/**
 * The build version, read from the one file that carries it. Both targets
 * stamp it in as `__INVOICESHELF_CLIENT_VERSION__`, which is how a client
 * knows whether the server it is talking to is its own generation.
 */
export function appVersion() {
  return readFileSync(resolve(repoRoot, 'version.md'), 'utf8').trim()
}
