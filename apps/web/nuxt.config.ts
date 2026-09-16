import tailwindcss from '@tailwindcss/vite'

const themeInitializer = `(() => {
  try {
    const stored = localStorage.getItem('theme')
    const preference = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
    const dark = preference === 'dark' || (preference === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)
    document.documentElement.dataset.theme = preference
    document.documentElement.classList.toggle('dark', dark)
  } catch {}
})()`

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
      script: [{ innerHTML: themeInitializer, tagPosition: 'head' }],
      meta: [
        { name: 'color-scheme', content: 'light dark' },
        { name: 'theme-color', media: '(prefers-color-scheme: light)', content: '#ffffff' },
        { name: 'theme-color', media: '(prefers-color-scheme: dark)', content: '#09090b' }
      ]
    }
  },
  compatibilityDate: '2025-07-15',
  css: ['~/assets/css/main.css'],
  devtools: { enabled: true },
  modules: ['@nuxt/eslint', 'shadcn-nuxt'],
  runtimeConfig: {
    trustedProxyIp: '',
    apiBaseUrl: process.env.NUXT_API_BASE_URL ?? 'http://api/api',
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL ?? process.env.NUXT_PUBLIC_API_BASE ?? '/api',
      siteUrl: process.env.NUXT_PUBLIC_SITE_URL ?? 'http://localhost:3000'
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
