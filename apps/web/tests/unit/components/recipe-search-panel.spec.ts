import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RecipeSearchPanel from '../../../app/components/recipes/RecipeSearchPanel.vue'

describe('RecipeSearchPanel', () => {
  it('shows every recipe filter and applies changes from the shared grid', async () => {
    const wrapper = mount(RecipeSearchPanel, {
      global: { stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } } },
      props: {
        activeFilters: [{ key: 'ingredient', label: 'Ingredient', to: '/recipes', value: 'Gin' }],
        categories: [{ name: 'Classics', slug: 'classics', description: null }],
        clearTo: '/recipes',
        ingredients: [{ name: 'Gin', slug: 'gin', containsAlcohol: true }],
        pending: false,
        resultCount: 12,
        state: { page: 1, sort: 'newest' }
      }
    })

    expect(wrapper.findAll('label').map(label => label.text())).toEqual([
      'Sort orderNewest firstMost savedOldest first',
      'CategoryAny categoryClassics',
      'IngredientAny ingredientGin',
      'Alcohol preferenceAny recipeWith alcoholZero-proof'
    ])
    expect(wrapper.text()).not.toContain('Advanced filters')
    expect(wrapper.find('details').exists()).toBe(false)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    expect(wrapper.get('a[aria-label="Remove Ingredient filter"]').attributes('href')).toBe('/recipes')

    await wrapper.findAll('select')[1]!.setValue('classics')
    expect(wrapper.emitted('apply')).toEqual([[
      expect.objectContaining({ category: 'classics', page: 1 })
    ]])
  })

  it('omits the redundant category filter for a category collection', () => {
    const wrapper = mount(RecipeSearchPanel, {
      global: { stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } },
      props: {
        activeFilters: [],
        categories: [],
        clearTo: '/categories/classics',
        fixedCategory: 'classics',
        ingredients: [{ name: 'Gin', slug: 'gin', containsAlcohol: true }],
        pending: false,
        resultCount: 4,
        state: { category: 'classics', page: 1, sort: 'newest' }
      }
    })

    expect(wrapper.text()).not.toContain('Category')
    expect(wrapper.text()).toContain('Sort order')
    expect(wrapper.text()).toContain('Ingredient')
    expect(wrapper.text()).toContain('Alcohol preference')
    expect(wrapper.findAll('select')).toHaveLength(3)
  })
})
