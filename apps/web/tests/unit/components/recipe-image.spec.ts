import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import RecipeImage from '../../../app/components/recipes/RecipeImage.vue'

const mocks = vi.hoisted(() => ({ imageFile: vi.fn(), user: { value: null as { id: number } | null } }))
mockNuxtImport('useApi', () => () => ({ recipes: { imageFile: mocks.imageFile } }))
mockNuxtImport('useAuth', () => () => ({ currentUser: mocks.user }))
const path = `/uploads/recipes/${'a'.repeat(32)}.png`
const recipe = { imagePath: path, title: 'Test drink', containsAlcohol: false }

describe('RecipeImage', () => {
  const observers: { intersect: (visible: boolean) => void, disconnect: ReturnType<typeof vi.fn>, observe: ReturnType<typeof vi.fn> }[] = []

  afterEach(() => vi.unstubAllGlobals())

  beforeEach(() => {
    observers.length = 0
    vi.stubGlobal('IntersectionObserver', class {
      disconnect = vi.fn()
      observe = vi.fn()
      constructor(callback: IntersectionObserverCallback, options: IntersectionObserverInit) {
        expect(options.rootMargin).toBe('200px')
        observers.push({
          intersect: visible => callback([{ isIntersecting: visible } as IntersectionObserverEntry], this as unknown as IntersectionObserver),
          disconnect: this.disconnect,
          observe: this.observe
        })
      }
    })
    vi.clearAllMocks()
    mocks.user = ref<{ id: number } | null>(null)
    URL.createObjectURL = vi.fn(() => 'blob:test-image')
    URL.revokeObjectURL = vi.fn()
  })

  it('renders public delivery URLs without fetching a blob', () => {
    const wrapper = mount(RecipeImage, { props: { recipe } })
    expect(wrapper.get('img').attributes('src')).toBe(`/api/recipe-images/${'a'.repeat(32)}.png`)
    expect(wrapper.get('img').attributes('width')).toBe('800')
    expect(wrapper.get('img').attributes('height')).toBe('600')
    expect(mocks.imageFile).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('defers authenticated downloads until near the viewport and loads only once', async () => {
    mocks.user.value = { id: 1 }
    mocks.imageFile.mockResolvedValue(new Blob(['image']))
    const wrapper = mount(RecipeImage, { props: { recipe } })
    await flushPromises()
    expect(mocks.imageFile).not.toHaveBeenCalled()
    expect(observers[0]?.observe).toHaveBeenCalledWith(wrapper.element)
    observers[0]?.intersect(false)
    await flushPromises()
    expect(mocks.imageFile).not.toHaveBeenCalled()
    observers[0]?.intersect(true)
    observers[0]?.intersect(true)
    await flushPromises()
    expect(mocks.imageFile).toHaveBeenCalledTimes(1)
    expect(observers[0]?.disconnect).toHaveBeenCalled()
    expect(wrapper.get('img').attributes('src')).toBe('blob:test-image')
    wrapper.unmount()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:test-image')
  })

  it('disconnects pending observers on image changes, logout and unmount', async () => {
    mocks.user.value = { id: 1 }
    const wrapper = mount(RecipeImage, { props: { recipe } })
    await wrapper.setProps({ recipe: { ...recipe, imagePath: `/uploads/recipes/${'b'.repeat(32)}.png` } })
    expect(observers[0]?.disconnect).toHaveBeenCalled()
    observers[0]?.intersect(true)
    mocks.user.value = null
    await flushPromises()
    expect(observers[1]?.disconnect).toHaveBeenCalled()
    observers[1]?.intersect(true)
    mocks.user.value = { id: 2 }
    await flushPromises()
    wrapper.unmount()
    expect(observers[2]?.disconnect).toHaveBeenCalled()
    observers[2]?.intersect(true)
    expect(mocks.imageFile).not.toHaveBeenCalled()
  })

  it('falls back to an immediate request when IntersectionObserver is unavailable', async () => {
    vi.stubGlobal('IntersectionObserver', undefined)
    mocks.user.value = { id: 1 }
    mocks.imageFile.mockResolvedValue(new Blob(['image']))
    const wrapper = mount(RecipeImage, { props: { recipe } })
    await flushPromises()
    expect(mocks.imageFile).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('loads authenticated images via the API and releases the blob on logout', async () => {
    mocks.user.value = { id: 1 }
    mocks.imageFile.mockResolvedValue(new Blob(['image'], { type: 'image/png' }))
    const wrapper = mount(RecipeImage, { props: { recipe, eager: true } })
    expect(wrapper.find('img').exists()).toBe(false)
    await flushPromises()
    expect(mocks.imageFile).toHaveBeenCalledWith(path, expect.any(AbortSignal))
    expect(wrapper.get('img').attributes('src')).toBe('blob:test-image')
    mocks.user.value = null
    await flushPromises()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:test-image')
    expect(wrapper.get('img').attributes('src')).not.toContain('blob:')
    expect(observers).toHaveLength(0)
    wrapper.unmount()
  })

  it('ignores stale responses after the image changes and aborts on unmount', async () => {
    mocks.user.value = { id: 1 }
    let resolve: (blob: Blob) => void = () => undefined
    mocks.imageFile.mockReturnValue(new Promise<Blob>((done) => { resolve = done }))
    const wrapper = mount(RecipeImage, { props: { recipe, eager: true } })
    const signal = mocks.imageFile.mock.calls[0]?.[1] as AbortSignal
    await wrapper.setProps({ recipe: { ...recipe, imagePath: null } })
    resolve(new Blob(['stale']))
    await flushPromises()
    expect(signal.aborted).toBe(true)
    expect(URL.createObjectURL).not.toHaveBeenCalled()
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows a neutral placeholder when access is denied', async () => {
    mocks.user.value = { id: 1 }
    mocks.imageFile.mockRejectedValue(new Error('Not found'))
    const wrapper = mount(RecipeImage, { props: { recipe, eager: true } })
    await flushPromises()
    expect(wrapper.text()).toContain('Photo unavailable')
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })
})
