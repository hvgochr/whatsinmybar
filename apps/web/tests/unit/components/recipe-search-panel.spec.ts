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
        ingredients: [{ name: 'Gin', slug: 'gin', containsAlcohol: true }],
        pending: false,
        state: { page: 1, sort: 'newest' }
      }
    })

    expect(wrapper.text()).toContain('Search within recipes')
    expect(wrapper.text()).toContain('Advanced filters')
    expect(wrapper.find('details').text()).toContain('Ingredient')

    expect(wrapper.get('a[aria-label="Remove Ingredient filter"]').attributes('href')).toBe('/recipes')
  })
})
