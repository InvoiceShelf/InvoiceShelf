import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from "path";

export default defineConfig({
  root: resolve(__dirname, './tests/browser/resources'),
  resolve: {
    alias: {
        "vue-i18n": "vue-i18n/dist/vue-i18n.cjs.js",
      '@': resolve(__dirname, './resources/'),
      '@t': resolve(__dirname, './tests/browser/resources/'),
    },
    extensions: ['.js', '.ts', '.jsx', '.tsx', '.json', '.vue', '.mjs']
  },
  plugins: [vue()],
  test: {
    setupFiles: resolve(__dirname, './tests/browser/setup.js'),
    css: true,
    threads: false,
    browser: {
      enabled: true,
      provider: 'playwright',
      name: 'chromium',
    }
  },
  css: {
      postcss: 'postcss.config.js',
  }
})

