import pluginVue from 'eslint-plugin-vue'
import eslintConfigPrettier from 'eslint-config-prettier'

export default [
  ...pluginVue.configs['flat/recommended'],
  eslintConfigPrettier,
  {
    files: ['resources/scripts/**/*.{js,vue}'],
    rules: {
      'vue/no-mutating-props': 'off',
    },
  },
]
