import type { User } from '../types/api'

export type SessionStatus = 'unknown' | 'authenticated' | 'anonymous' | 'degraded'

// All state belongs to this Nuxt app / SSR request, never to the server module.
export function useSessionState() {
  const nuxtApp = useNuxtApp()
  const accessToken = useState<string | null>('auth.accessToken', () => null)
  const user = useState<User | null>('auth.currentUser', () => null)
  const status = useState<SessionStatus>('auth.status', () => 'unknown')
  const revision = useState<number>('auth.revision', () => 0)

  const invalidate = () => {
    revision.value++
    nuxtApp.runWithContext(() => clearNuxtData())
  }
  const transition = (next: SessionStatus) => {
    if (status.value !== next) {
      status.value = next
      invalidate()
    }
  }
  const clear = () => {
    accessToken.value = null
    user.value = null
    // Also cancel pending work when already anonymous.
    status.value = 'anonymous'
    invalidate()
  }
  const setAccessToken = (token: string | null) => {
    accessToken.value = token
    transition('unknown')
  }
  const setUser = (next: User) => {
    const changed = user.value?.id !== next.id || user.value?.birthDate !== next.birthDate
      || JSON.stringify(user.value?.roles) !== JSON.stringify(next.roles)
    user.value = next
    if (status.value !== 'authenticated') transition('authenticated')
    else if (changed) invalidate()
  }

  return { accessToken, user, status, revision, clear, setAccessToken, setUser, degrade: () => transition('degraded') }
}
