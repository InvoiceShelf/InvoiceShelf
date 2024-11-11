// postcss.config.js
module.exports = {
  plugins: {
    "postcss-import": {
      path: ['resources/styles'],
      addModulesDirectories: ['node_modules', 'resources/styles']
    },
    "tailwindcss/nesting": {},
    tailwindcss: {},
    autoprefixer: {},
  },
}
