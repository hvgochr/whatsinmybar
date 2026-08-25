export default defineNuxtPlugin({
  name: 'session',
  dependsOn: ['api'],
  async setup() {
    const bootstrapped = useState<boolean>('auth.bootstrapped', () => false)

    if (bootstrapped.value) {
      return
    }

    if (import.meta.server && !hasRefreshCookie(useRequestHeaders(['cookie']).cookie)) {
      bootstrapped.value = true
      return
    }

    try {
      await useAuth().restoreSession()
    } catch {
      // Session failures must not make public pages unavailable.
    } finally {
      bootstrapped.value = true
    }
  }
})

function hasRefreshCookie(cookieHeader: string | undefined): boolean {
  return /(?:^|;\s*)refresh_token=[^;]+/.test(cookieHeader ?? '')
}
