import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RecipeSearchPanel from '../../../app/components/recipes/RecipeSearchPanel.vue'

describe('RecipeSearchPanel', () => {
  it('keeps primary filters concise and exposes removable active filters', async () => {
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

    expect(wrapper.text()).toContain('Sort order')
    expect(wrapper.text()).not.toContain('Narrow the current collection')
    expect(wrapper.text()).toContain('Advanced filters')
    expect(wrapper.find('details').text()).toContain('Ingredient')
    expect(wrapper.find('details').text()).toContain('Category')

    expect(wrapper.get('a[aria-label="Remove Ingredient filter"]').attributes('href')).toBe('/recipes')
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

    expect(wrapper.find('details').text()).not.toContain('Category')
    expect(wrapper.find('details').text()).toContain('Ingredient')
    expect(wrapper.find('details').text()).toContain('Alcohol preference')
  })
})
