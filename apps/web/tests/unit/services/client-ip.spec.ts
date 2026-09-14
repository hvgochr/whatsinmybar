import { describe, expect, it } from 'vitest'
import { forwardedClientIp } from '../../../app/services/client-ip'

describe('SSR client IP boundary', () => {
  it('accepts only the address supplied by the configured Caddy peer', () => {
    expect(forwardedClientIp('::ffff:172.30.71.2', '192.0.2.1', '172.30.71.2')).toBe('192.0.2.1')
    expect(forwardedClientIp('172.30.71.2', '192.0.2.2', '172.30.71.2')).toBe('192.0.2.2')
    expect(forwardedClientIp('172.30.71.2', '2001:db8::1', '172.30.71.2')).toBe('2001:db8::1')
  })

  it('ignores forged headers from direct visitors and ambiguous chains', () => {
    expect(forwardedClientIp('192.0.2.1', '203.0.113.1', '172.30.71.2')).toBe('192.0.2.1')
    expect(forwardedClientIp('192.0.2.1', '203.0.113.1', '')).toBe('192.0.2.1')
    expect(forwardedClientIp(undefined, '203.0.113.1', '172.30.71.2')).toBeUndefined()
    expect(forwardedClientIp('172.30.71.2', '203.0.113.1, 192.0.2.1', '172.30.71.2')).toBe('172.30.71.2')
  })
})
