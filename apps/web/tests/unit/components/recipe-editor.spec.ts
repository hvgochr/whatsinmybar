import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecipeEditor from '../../../app/components/recipes/RecipeEditor.vue'
import { ApiRequestError } from '../../../app/services/api-client'
import type { RecipeResource } from '../../../app/types/api'

const mocks = vi.hoisted(() => ({
  image: vi.fn(),
  publish: vi.fn(),
  update: vi.fn()
}))

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
    mocks.image.mockRejectedValue(new ApiRequestError('Upload rejected.', 422, 'validation_failed'))
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
    expect(wrapper.text()).toContain('Recipe content was saved, but the image upload failed.')
    expect(wrapper.text()).not.toContain('Recipe changes have been saved.')
  })
})

function mountEditor() {
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
      initialRecipe: existingRecipe(),
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
