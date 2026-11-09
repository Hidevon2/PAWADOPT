import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './tests',
  workers: 1,
  reporter: 'list',
  use: { baseURL: process.env.PAWADOPT_TEST_URL || 'http://127.0.0.1:9001', channel: 'msedge', headless: true, viewport: { width: 1536, height: 1100 }, screenshot: 'only-on-failure' },
  outputDir: 'test-results',
})
