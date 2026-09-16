import { describe, expect, it } from 'vitest'
import { isNoIndexRoute, renderSitemap, sitemapHasNext, sitemapItems } from '../../../app/utils/seo'

describe('SEO helpers', () => {
  it.each([
    '/admin',
    '/admin/reports',
    '/settings',
    '/recipes/new',
    '/recipes/dry-martini/edit',
    '/login',
    '/register',
    '/logout'
  ])('marks %s as noindex', (path) => {
    expect(isNoIndexRoute(path)).toBe(true)
  })

  it.each(['/', '/recipes', '/recipes/citrus-spritz', '/categories', '/users/jane_doe'])('keeps %s indexable', (path) => {
    expect(isNoIndexRoute(path)).toBe(false)
  })

  it('reads plain and Hydra sitemap collections', () => {
    expect(sitemapItems([{ slug: 'one' }])).toEqual([{ slug: 'one' }])
    expect(sitemapItems({ member: [{ slug: 'two' }], view: { next: '/recipes?page=2' } })).toEqual([{ slug: 'two' }])
    expect(sitemapHasNext({ 'hydra:member': [], 'hydra:view': { 'hydra:next': '/recipes?page=2' } })).toBe(true)
  })

  it('deduplicates, sorts, absolutizes, and escapes sitemap locations', () => {
    const xml = renderSitemap('https://bar.example', ['/recipes/gin-tonic?x=1&y=2', '/', '/'])
    expect(xml).toContain('<loc>https://bar.example/</loc>')
    expect(xml).toContain('<loc>https://bar.example/recipes/gin-tonic?x=1&amp;y=2</loc>')
    expect(xml.match(/https:\/\/bar\.example\//g)).toHaveLength(2)
  })
})
