import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  css: ['~/assets/css/main.css'],
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', 'shadcn-nuxt'],
  runtimeConfig: {
    apiBaseUrl: process.env.NUXT_API_BASE_URL ?? 'http://api/api',
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL ?? process.env.NUXT_PUBLIC_API_BASE ?? '/api'
    }
  },
  shadcn: {
    componentDir: './app/components/ui',
    prefix: 'Ui'
  },
  vite: {
    plugins: [
      tailwindcss()
    ]
  }
})
