/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_TITLE: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

/**
 * Build-time flags injected by Vite's `define`. Both configs set them, so a
 * branch on `__INVOICESHELF_CLIENT__` is a constant the bundler folds away
 * rather than a runtime test.
 */
declare const __INVOICESHELF_CLIENT__: boolean
declare const __INVOICESHELF_CLIENT_VERSION__: string
