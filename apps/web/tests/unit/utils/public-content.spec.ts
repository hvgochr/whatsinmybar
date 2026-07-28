import { describe, expect, it } from 'vitest'
import { formatIngredientAmount, imageUrl, publicDescription, publicUrl } from '../../../app/utils/public-content'

describe('public content helpers', () => {
  it('formats measured ingredients', () => {
    expect(formatIngredientAmount({
      id: 1,
      ingredient: {
        containsAlcohol: true,
        name: 'Gin',
        slug: 'gin'
      },
      note: 'chilled',
      position: 1,
      quantity: '45.00',
      unit: 'ml'
    })).toBe('45 ml Gin chilled')
  })

  it('normalizes relative upload image URLs against the API base URL', () => {
    expect(imageUrl('/uploads/recipes/negroni.jpg', 'http://localhost:8080/api')).toBe('http://localhost:8080/uploads/recipes/negroni.jpg')
  })

  it('builds canonical public URLs', () => {
    expect(publicUrl('https://whatsinmybar.test', '/recipes/negroni')).toBe('https://whatsinmybar.test/recipes/negroni')
  })

  it('uses fallback descriptions and truncates long text', () => {
    expect(publicDescription('', 'Fallback description')).toBe('Fallback description')
    expect(publicDescription('A'.repeat(180), 'Fallback description')).toHaveLength(155)
  })
})
