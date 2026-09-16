import { describe, expect, it, vi } from 'vitest'
import { collectPublicSitemapPaths, sitemapApiTimeoutMs } from '../../../server/utils/public-sitemap'

describe('public sitemap collection', () => {
  it('follows every anonymous recipe page with bounded, non-retried requests', async () => {
    const request = vi.fn()
      .mockResolvedValueOnce([{ slug: 'zero-proof' }])
      .mockResolvedValueOnce({
        member: [{ slug: 'first-recipe', authorUsername: 'first_author' }],
        view: { next: '/api/recipes?page=2' }
      })
      .mockResolvedValueOnce({
        'hydra:member': [{ slug: 'last-recipe', authorUsername: 'last_author' }],
        'hydra:view': {}
      })

    const paths = await collectPublicSitemapPaths('http://api/api', request as unknown as typeof $fetch)

    expect(request).toHaveBeenCalledTimes(3)
    for (const [, options] of request.mock.calls) {
      expect(options).toEqual(expect.objectContaining({ retry: 0, timeout: sitemapApiTimeoutMs }))
    }
    expect(request).toHaveBeenNthCalledWith(2, 'http://api/api/recipes', expect.objectContaining({ query: { page: 1 } }))
    expect(request).toHaveBeenNthCalledWith(3, 'http://api/api/recipes', expect.objectContaining({ query: { page: 2 } }))
    expect(paths).toEqual(expect.arrayContaining([
      '/categories/zero-proof',
      '/recipes/first-recipe',
      '/recipes/last-recipe',
      '/users/first_author',
      '/users/last_author'
    ]))
  })

  it('propagates an upstream timeout so the route can return 503', async () => {
    const request = vi.fn().mockRejectedValue(new Error('request timed out'))

    await expect(collectPublicSitemapPaths('http://api/api', request as unknown as typeof $fetch)).rejects.toThrow('request timed out')
  })
})
