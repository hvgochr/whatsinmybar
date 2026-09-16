import { describe, expect, it } from 'vitest'
import { absoluteImageUrl, formatIngredientAmount, imageUrl, publicDescription, publicUrl } from '../../../app/utils/public-content'

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

  it.each([
    ['/api', '/api/recipe-images/negroni.jpg'],
    ['/api/', '/api/recipe-images/negroni.jpg'],
    ['http://localhost:8080/api', 'http://localhost:8080/api/recipe-images/negroni.jpg'],
    ['http://localhost:8080/api/', 'http://localhost:8080/api/recipe-images/negroni.jpg']
  ])('normalizes upload image URLs with API base %s', (apiBaseUrl, expected) => {
    expect(imageUrl('/uploads/recipes/negroni.jpg', apiBaseUrl)).toBe(expected)
  })

  it('returns absolute image URLs unchanged', () => {
    expect(imageUrl('https://cdn.example.com/recipes/negroni.jpg', '/api')).toBe('https://cdn.example.com/recipes/negroni.jpg')
  })

  it('creates absolute OpenGraph image URLs from protected image paths', () => {
    expect(absoluteImageUrl('/uploads/recipes/negroni.jpg', '/api', 'https://bar.example')).toBe('https://bar.example/api/recipe-images/negroni.jpg')
    expect(absoluteImageUrl('/uploads/avatars/jane.jpg', '/api', 'https://bar.example')).toBe('https://bar.example/uploads/avatars/jane.jpg')
  })

  it('returns non-upload paths unchanged', () => {
    expect(imageUrl('/images/negroni.jpg', '/api')).toBe('/images/negroni.jpg')
  })

  it('builds canonical public URLs', () => {
    expect(publicUrl('https://whatsinmybar.test', '/recipes/negroni')).toBe('https://whatsinmybar.test/recipes/negroni')
  })

  it('uses fallback descriptions and truncates long text', () => {
    expect(publicDescription('', 'Fallback description')).toBe('Fallback description')
    expect(publicDescription('A'.repeat(180), 'Fallback description')).toHaveLength(155)
  })
})
