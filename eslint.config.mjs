import pluginVue from 'eslint-plugin-vue'
import pluginVueA11y from 'eslint-plugin-vuejs-accessibility'
import eslintConfigPrettier from 'eslint-config-prettier'
import tsParser from '@typescript-eslint/parser'

// Our form controls, so a <label> wrapping one counts as labelling it
const CONTROL_COMPONENTS = [
  'BaseInput',
  'BaseTextarea',
  'BaseMoney',
  'BaseMultiselect',
  'BaseDatePicker',
  'BaseSelectInput',
  'BaseSwitch',
  'BaseCheckbox',
]

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
    languageOptions: {
      globals: {
        // Injected by Vite's `define` in both build configs.
        __INVOICESHELF_CLIENT__: 'readonly',
        __INVOICESHELF_CLIENT_VERSION__: 'readonly',
      },
    },
    rules: {
      'vue/no-mutating-props': 'off',
      // Single-word components (Page, Breadcrumb) are intentional in this app.
      'vue/multi-word-component-names': 'off',
      // The app intentionally pairs `required` props with sensible `default`s.
      'vue/no-required-prop-with-default': 'off',
    },
  },
  // Accessibility: what a keyboard or screen reader user cannot reach or hear.
  // Only native elements are checked; Base* controls take their names from
  // BaseInputGroup, which a linter cannot see, and the axe check covers them.
  {
    files: ['resources/scripts/**/*.vue'],
    plugins: { 'vuejs-accessibility': pluginVueA11y },
    rules: {
      'vuejs-accessibility/alt-text': 'error',
      'vuejs-accessibility/anchor-has-content': 'error',
      'vuejs-accessibility/aria-props': 'error',
      'vuejs-accessibility/aria-role': 'error',
      'vuejs-accessibility/click-events-have-key-events': 'error',
      'vuejs-accessibility/form-control-has-label': 'error',
      'vuejs-accessibility/heading-has-content': 'error',
      'vuejs-accessibility/iframe-has-title': 'error',
      'vuejs-accessibility/interactive-supports-focus': 'error',
      'vuejs-accessibility/label-has-for': [
        'error',
        { required: { some: ['nesting', 'id'] }, controlComponents: CONTROL_COMPONENTS },
      ],
      'vuejs-accessibility/mouse-events-have-key-events': 'error',
      'vuejs-accessibility/no-autofocus': 'warn',
      'vuejs-accessibility/no-static-element-interactions': 'error',
      'vuejs-accessibility/role-has-required-aria-props': 'error',
      'vuejs-accessibility/tabindex-no-positive': 'error',
    },
  },
]
