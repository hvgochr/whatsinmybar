import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/integration',
  workers: 1,
  timeout: 60_000,
  use: {
    baseURL: process.env.SESSION_TEST_BASE_URL ?? 'http://caddy',
    ...devices['Desktop Chrome']
  }
})
