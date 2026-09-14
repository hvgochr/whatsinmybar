import type { User } from '../types/api'

export type SessionStatus = 'unknown' | 'authenticated' | 'anonymous' | 'degraded'

// All state belongs to this Nuxt app / SSR request, never to the server module.
export function useSessionState() {
  const nuxtApp = useNuxtApp()
  const accessToken = useState<string | null>('auth.accessToken', () => null)
  const user = useState<User | null>('auth.currentUser', () => null)
  const status = useState<SessionStatus>('auth.status', () => 'unknown')
  const revision = useState<number>('auth.revision', () => 0)
  const needsViewerReload = useState<boolean>('auth.needsViewerReload', () => true)

  const invalidate = () => {
    revision.value++
    nuxtApp.runWithContext(() => clearNuxtData())
  }
  const clear = () => {
    accessToken.value = null
    user.value = null
    // Also cancel pending work when already anonymous.
    status.value = 'anonymous'
    needsViewerReload.value = true
    invalidate()
  }
  const setAccessToken = (token: string | null) => {
    accessToken.value = token
    // Verification pauses personalized controls without discarding an editor's
    // unsaved work on every routine access-token renewal.
    status.value = 'unknown'
  }
  const setUser = (next: User) => {
    const changed = user.value?.id !== next.id || user.value?.birthDate !== next.birthDate
      || JSON.stringify(user.value?.roles) !== JSON.stringify(next.roles)
    user.value = next
    status.value = 'authenticated'
    if (changed || needsViewerReload.value) invalidate()
    needsViewerReload.value = false
  }

  const degrade = () => {
    if (status.value === 'degraded') return
    status.value = 'degraded'
    needsViewerReload.value = true
    invalidate()
  }

  return { accessToken, user, status, revision, clear, setAccessToken, setUser, degrade }
}
