import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import RecipeImage from '../../../app/components/recipes/RecipeImage.vue'

const mocks = vi.hoisted(() => ({ imageFile: vi.fn(), user: { value: null as { id: number } | null } }))
mockNuxtImport('useApi', () => () => ({ recipes: { imageFile: mocks.imageFile } }))
mockNuxtImport('useAuth', () => () => ({ currentUser: mocks.user }))
const path = `/uploads/recipes/${'a'.repeat(32)}.png`
const recipe = { imagePath: path, title: 'Test drink', containsAlcohol: false }

describe('RecipeImage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.user = ref<{ id: number } | null>(null)
    URL.createObjectURL = vi.fn(() => 'blob:test-image')
    URL.revokeObjectURL = vi.fn()
  })

  it('renders public delivery URLs without fetching a blob', () => {
    const wrapper = mount(RecipeImage, { props: { recipe } })
    expect(wrapper.get('img').attributes('src')).toBe(`/api/recipe-images/${'a'.repeat(32)}.png`)
    expect(mocks.imageFile).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('loads authenticated images via the API and releases the blob on logout', async () => {
    mocks.user.value = { id: 1 }
    mocks.imageFile.mockResolvedValue(new Blob(['image'], { type: 'image/png' }))
    const wrapper = mount(RecipeImage, { props: { recipe } })
    expect(wrapper.find('img').exists()).toBe(false)
    await flushPromises()
    expect(mocks.imageFile).toHaveBeenCalledWith(path, expect.any(AbortSignal))
    expect(wrapper.get('img').attributes('src')).toBe('blob:test-image')
    mocks.user.value = null
    await flushPromises()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:test-image')
    expect(wrapper.get('img').attributes('src')).not.toContain('blob:')
    wrapper.unmount()
  })

  it('ignores stale responses after the image changes and aborts on unmount', async () => {
    mocks.user.value = { id: 1 }
    let resolve: (blob: Blob) => void = () => undefined
    mocks.imageFile.mockReturnValue(new Promise<Blob>((done) => { resolve = done }))
    const wrapper = mount(RecipeImage, { props: { recipe } })
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
    const wrapper = mount(RecipeImage, { props: { recipe } })
    await flushPromises()
    expect(wrapper.text()).toContain('Photo unavailable')
    expect(wrapper.find('img').exists()).toBe(false)
    wrapper.unmount()
  })
})
