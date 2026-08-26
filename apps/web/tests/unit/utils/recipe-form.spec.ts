import { describe, expect, it } from 'vitest'
import type { RecipeResource } from '../../../app/types/api'
import {
  buildRecipePayload,
  createEmptyRecipeForm,
  recipeToForm,
  toggleCategory
} from '../../../app/utils/recipe-form'

describe('recipe form helpers', () => {
  it('builds the main recipe payload with API IRIs', () => {
    const form = createEmptyRecipeForm()
    form.title = ' Negroni '
    form.description = ' Stirred classic '
    form.difficulty = 'medium'
    form.preparationTimeMinutes = 4
    form.servings = 1
    form.categories = ['classics', 'aperitif']

    expect(buildRecipePayload(form)).toEqual({
      categories: ['/api/categories/classics', '/api/categories/aperitif'],
      description: 'Stirred classic',
      difficulty: 'medium',
      ingredients: [],
      preparationTimeMinutes: 4,
      servings: 1,
      steps: [],
      title: 'Negroni'
    })
  })

  it('maps a recipe resource into editable form state', () => {
    const recipe: RecipeResource = {
      categories: [{ name: 'Classics', slug: 'classics', description: null }],
      containsAlcohol: true,
      containsAlcoholOverride: false,
      description: 'A classic.',
      difficulty: 'hard',
      favoriteCount: 2,
      imagePath: null,
      moderationStatus: 'visible',
      preparationTimeMinutes: 6,
      publishedAt: null,
      recipeIngredients: [
        {
          ingredient: { containsAlcohol: true, name: 'Gin', slug: 'gin' },
          note: null,
          position: 2,
          quantity: '45.00',
          unit: 'ml'
        }
      ],
      servings: 1,
      slug: 'negroni',
      status: 'draft',
      steps: [{ instruction: 'Stir.', position: 1 }],
      title: 'Negroni'
    }

    expect(recipeToForm(recipe)).toMatchObject({
      categories: ['classics'],
      difficulty: 'hard',
      ingredients: [{ ingredientSlug: 'gin', quantity: '45.00' }],
      steps: [{ instruction: 'Stir.' }]
    })
  })

  it('toggles categories without duplicates', () => {
    const form = createEmptyRecipeForm()
    const category = { description: null, name: 'Classics', slug: 'classics' }

    toggleCategory(form, category, true)
    toggleCategory(form, category, true)
    expect(form.categories).toEqual(['classics'])

    toggleCategory(form, category, false)
    expect(form.categories).toEqual([])
  })
})
