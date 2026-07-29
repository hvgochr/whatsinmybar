import { defineVitestConfig } from '@nuxt/test-utils/config'
import { fileURLToPath } from 'node:url'

export default defineVitestConfig({
  resolve: {
    alias: {
      '#app-manifest': fileURLToPath(new URL('./tests/unit/stubs/app-manifest.ts', import.meta.url))
    }
  },
  test: {
    environment: 'nuxt',
    include: ['tests/unit/**/*.spec.ts']
  }
})
