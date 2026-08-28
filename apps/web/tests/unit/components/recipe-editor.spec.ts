import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecipeEditor from '../../../app/components/recipes/RecipeEditor.vue'
import { ApiRequestError } from '../../../app/services/api-client'
import type { RecipeResource } from '../../../app/types/api'

const mocks = vi.hoisted(() => ({
  image: vi.fn(),
  notifyError: vi.fn(),
  notifySuccess: vi.fn(),
  publish: vi.fn(),
  update: vi.fn()
}))
mockNuxtImport('useNotifications', () => () => ({ error: mocks.notifyError, success: mocks.notifySuccess }))

mockNuxtImport('useApi', () => () => ({
  recipes: {
    image: mocks.image,
    publish: mocks.publish,
    update: mocks.update
  }
}))

describe('RecipeEditor aggregate saves', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    Object.defineProperty(URL, 'createObjectURL', { configurable: true, value: vi.fn(() => 'blob:preview') })
    Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, value: vi.fn() })
  })

  it('does not publish when the aggregate save fails', async () => {
    mocks.update.mockRejectedValue(new ApiRequestError('Invalid recipe.', 422, 'validation_failed'))
    const wrapper = mountEditor()

    const publishButton = wrapper.findAll('button').find(button => button.text() === 'Save and publish')
    expect(publishButton).toBeDefined()
    await publishButton!.trigger('click')
    await flushPromises()

    expect(mocks.update).toHaveBeenCalledOnce()
    expect(mocks.image).not.toHaveBeenCalled()
    expect(mocks.publish).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Something went wrong. Please try again.')
  })

  it('reports image failure separately after recipe content is saved', async () => {
    const recipe = existingRecipe()
    mocks.update.mockResolvedValue(recipe)
    mocks.image.mockRejectedValueOnce(new ApiRequestError('Upload rejected.', 422, 'validation_failed'))
      .mockResolvedValueOnce({ imagePath: '/uploads/recipes/highball.png', recipeSlug: 'highball' })
    const wrapper = mountEditor()
    const imageInput = wrapper.get('#recipe-image').element as HTMLInputElement
    Object.defineProperty(imageInput, 'files', {
      configurable: true,
      value: [new File(['image'], 'cocktail.png', { type: 'image/png' })]
    })

    await wrapper.get('#recipe-image').trigger('change')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(mocks.update).toHaveBeenCalledOnce()
    expect(mocks.image).toHaveBeenCalledOnce()
    expect(mocks.publish).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Recipe saved, but the image could not be uploaded.')
    expect(wrapper.text()).toContain('The image could not be uploaded. Try again.')
    expect(wrapper.get('button').text()).not.toContain('Recipe changes have been saved.')

    const retryButton = wrapper.findAll('button').find(button => button.text() === 'Retry image upload')
    expect(retryButton).toBeDefined()
    await retryButton!.trigger('click')
    await flushPromises()

    expect(mocks.update).toHaveBeenCalledOnce()
    expect(mocks.image).toHaveBeenCalledTimes(2)
    expect(mocks.notifySuccess).toHaveBeenCalledWith('recipe-image:highball', 'Recipe image uploaded.')
  })

  it('rejects an oversized image before saving or uploading', async () => {
    const wrapper = mountEditor()
    const imageInput = wrapper.get('#recipe-image').element as HTMLInputElement
    Object.defineProperty(imageInput, 'files', {
      configurable: true,
      value: [new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'large.png', { type: 'image/png' })]
    })

    await wrapper.get('#recipe-image').trigger('change')

    expect(wrapper.text()).toContain('Image must be 5 MB or smaller.')
    expect(mocks.image).not.toHaveBeenCalled()
    expect(mocks.update).not.toHaveBeenCalled()
  })

  it('preserves an existing image when saving without selecting a replacement', async () => {
    const recipe = { ...existingRecipe(), imagePath: '/uploads/recipes/existing.jpg' }
    mocks.update.mockResolvedValue(recipe)
    const wrapper = mountEditor(recipe)

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(mocks.update).toHaveBeenCalledOnce()
    expect(mocks.image).not.toHaveBeenCalled()
    expect(wrapper.find('img[alt="Highball"]').attributes('src')).toContain('/uploads/recipes/existing.jpg')
  })
})

function mountEditor(recipe = existingRecipe()) {
  return mount(RecipeEditor, {
    global: {
      stubs: {
        RouterLink: {
          template: '<a><slot /></a>'
        }
      }
    },
    props: {
      categories: [],
      ingredients: [{ containsAlcohol: false, name: 'Soda', slug: 'soda' }],
      initialRecipe: recipe,
      mode: 'edit'
    }
  })
}

function existingRecipe(): RecipeResource {
  return {
    categories: [],
    containsAlcohol: false,
    description: 'A refreshing highball.',
    difficulty: 'easy',
    favoriteCount: 0,
    imagePath: null,
    moderationStatus: 'visible',
    preparationTimeMinutes: 3,
    publishedAt: null,
    recipeIngredients: [{
      ingredient: '/api/ingredients/soda',
      note: null,
      position: 1,
      quantity: '50.00',
      unit: 'ml'
    }],
    servings: 1,
    slug: 'highball',
    status: 'draft',
    steps: [{ instruction: 'Build over ice.', position: 1 }],
    title: 'Highball'
  }
}
