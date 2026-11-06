import { defineConfig } from '#q-app/wrappers'

export default defineConfig(() => ({
  boot: [],
  css: ['app.scss'],
  extras: ['material-symbols-rounded'],
  build: {
    target: { browser: ['es2022', 'chrome115', 'firefox115', 'safari14'] },
    vueRouterMode: 'hash',
  },
  devServer: {
    host: '127.0.0.1',
    port: 9001,
    open: false,
    proxy: { '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true } },
  },
  framework: {
    iconSet: 'material-symbols-rounded',
    config: { brand: { primary: '#f39152', secondary: '#7fb5a6', dark: '#182120', positive: '#91c9ad' } },
    plugins: ['Notify', 'Dialog'],
  },
  animations: [],
}))
