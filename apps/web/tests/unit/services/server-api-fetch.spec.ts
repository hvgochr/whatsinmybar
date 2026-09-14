import { describe, expect, it, vi } from 'vitest'
import { createServerApiFetch } from '../../../app/services/server-api-fetch'

describe('SSR cookie transport', () => {
  it('updates the request cookie after rotation and never sends unrelated cookies', async () => {
    const append = vi.fn()
    const raw = vi.fn(async (_path: string, options: Record<string, unknown>) => {
      const cookie = (options.headers as Headers).get('cookie')
      expect(cookie ?? '').not.toContain('unrelated')
      return { _data: {}, headers: new Headers({ 'Set-Cookie': 'refresh_token=successor; Path=/; HttpOnly; SameSite=Strict' }) }
    })
    const fetch = createServerApiFetch(raw, 'unrelated=secret; refresh_token=original', append)
    await fetch('/auth/refresh')
    await fetch('/auth/logout')
    await fetch('/me')
    expect((raw.mock.calls[0]![1].headers as Headers).get('cookie')).toBe('refresh_token=original')
    expect((raw.mock.calls[1]![1].headers as Headers).get('cookie')).toBe('refresh_token=successor')
    expect((raw.mock.calls[2]![1].headers as Headers).has('cookie')).toBe(false)
    expect(append).toHaveBeenCalledWith(expect.stringContaining('HttpOnly'))
  })

  it('isolates simultaneous SSR users and forwards cookies even on HTTP errors', async () => {
    const raw = vi.fn(async (_path: string, options: Record<string, unknown>) => ({ _data: (options.headers as Headers).get('cookie'), headers: new Headers() }))
    const a = createServerApiFetch(raw, 'refresh_token=A', vi.fn())
    const b = createServerApiFetch(raw, 'refresh_token=B', vi.fn())
    expect(await Promise.all([a('/auth/refresh'), b('/auth/refresh')])).toEqual(['refresh_token=A', 'refresh_token=B'])
    const append = vi.fn()
    const failed = createServerApiFetch(async () => { throw { response: { headers: new Headers({ 'Set-Cookie': 'refresh_token=; Max-Age=0; Path=/' }) } } }, 'refresh_token=bad', append)
    await expect(failed('/auth/refresh')).rejects.toBeDefined()
    expect(append).toHaveBeenCalledOnce()
  })
})
