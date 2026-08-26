import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FavoriteButton from '../../../app/components/social/FavoriteButton.vue'

const mocks = vi.hoisted(() => ({
  add: vi.fn(),
  authenticated: { value: true },
  navigateTo: vi.fn(),
  remove: vi.fn()
}))

mockNuxtImport('useApi', () => () => ({ favorites: { add: mocks.add, remove: mocks.remove } }))
mockNuxtImport('useAuth', () => () => ({ isAuthenticated: mocks.authenticated }))
mockNuxtImport('navigateTo', () => mocks.navigateTo)

describe('FavoriteButton', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.authenticated.value = true
  })

  it('renders the saved state and count, then emits the updated state', async () => {
    mocks.remove.mockResolvedValue({ recipeSlug: 'negroni', favoriteCount: 11, favorited: false, changed: true })
    const wrapper = mount(FavoriteButton, { props: { count: 12, favorited: true, recipeSlug: 'negroni' } })
    const button = wrapper.get('button')

    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.text()).toContain('12')
    expect(wrapper.get('svg').attributes('fill')).toBe('currentColor')

    await button.trigger('click')
    await flushPromises()

    expect(mocks.remove).toHaveBeenCalledWith('negroni')
    expect(wrapper.emitted('updated')?.[0]).toEqual([{ count: 11, favorited: false }])
    expect(button.attributes('aria-pressed')).toBe('false')
  })

  it('sends anonymous visitors to login without calling a favorite endpoint', async () => {
    mocks.authenticated.value = false
    const wrapper = mount(FavoriteButton, { props: { count: 4, favorited: false, recipeSlug: 'spritz' } })
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(mocks.navigateTo).toHaveBeenCalledWith('/login?redirect=%2Frecipes%2Fspritz')
    expect(mocks.add).not.toHaveBeenCalled()
  })
})
