export type ThemePreference = 'light' | 'dark' | 'system'

export function useTheme() {
  const preference = useState<ThemePreference>('theme.preference', () => 'system')
  const systemDark = useState('theme.system-dark', () => false)
  let media: MediaQueryList | undefined
  const onMediaChange = (event: MediaQueryListEvent) => {
    systemDark.value = event.matches
    if (preference.value === 'system') applyTheme()
  }

  function resolve(value = preference.value): 'light' | 'dark' {
    if (value !== 'system') return value
    return systemDark.value ? 'dark' : 'light'
  }

  function applyTheme(value = preference.value): void {
    if (!import.meta.client) return
    document.documentElement.classList.toggle('dark', resolve(value) === 'dark')
    document.documentElement.dataset.theme = value
  }

  function setTheme(value: ThemePreference): void {
    preference.value = value
    if (import.meta.client) {
      localStorage.setItem('theme', value)
      applyTheme(value)
    }
  }

  onMounted(() => {
    const stored = localStorage.getItem('theme')
    preference.value = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
    media = window.matchMedia('(prefers-color-scheme: dark)')
    systemDark.value = media.matches
    applyTheme()
    media.addEventListener('change', onMediaChange)
  })

  onBeforeUnmount(() => media?.removeEventListener('change', onMediaChange))

  return {
    preference: readonly(preference),
    resolvedTheme: computed(() => resolve()),
    setTheme
  }
}
