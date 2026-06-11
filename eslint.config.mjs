import pluginVue from 'eslint-plugin-vue'
import eslintConfigPrettier from 'eslint-config-prettier'
import tsParser from '@typescript-eslint/parser'

export default [
  ...pluginVue.configs['flat/recommended'],
  // Parse TypeScript inside <script lang="ts"> blocks of .vue files.
  // vue-eslint-parser stays the top-level parser; it delegates <script> to tsParser.
  {
    files: ['resources/scripts/**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tsParser,
        sourceType: 'module',
        ecmaVersion: 'latest',
      },
    },
  },
  // Parse standalone .ts files with the TypeScript parser.
  {
    files: ['resources/scripts/**/*.ts'],
    languageOptions: {
      parser: tsParser,
      sourceType: 'module',
      ecmaVersion: 'latest',
    },
  },
  eslintConfigPrettier,
  {
    files: ['resources/scripts/**/*.{js,ts,vue}'],
    rules: {
      'vue/no-mutating-props': 'off',
      // Single-word components (Page, Breadcrumb) are intentional in this app.
      'vue/multi-word-component-names': 'off',
      // The app intentionally pairs `required` props with sensible `default`s.
      'vue/no-required-prop-with-default': 'off',
    },
  },
]
