export default defineNuxtPlugin({
  name: 'session-sync',
  dependsOn: ['session'],
  setup(nuxtApp) {
    const api = useApi()
    const session = useSessionState()
    const auth = useAuth()
    const recover = () => nuxtApp.runWithContext(async () => {
      try {
        await auth.restoreSession()
        await refreshNuxtData()
      } catch {
        // The session banner offers another attempt; cookies remain untouched.
      }
    })
    const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('whatsinmybar.session') : null
    if (channel) channel.onmessage = (message) => {
      if (message.data !== 'logout' && message.data !== 'changed') return
      api.clearTokens()
      if (message.data === 'changed') void recover()
      else void nuxtApp.runWithContext(() => refreshNuxtData())
    }
    const onFocus = () => {
      if (session.status.value === 'degraded') void recover()
    }
    window.addEventListener('focus', onFocus)
    nuxtApp.vueApp.onUnmount(() => {
      channel?.close()
      window.removeEventListener('focus', onFocus)
    })
  }
})
