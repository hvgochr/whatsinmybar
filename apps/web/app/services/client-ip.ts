// Caddy overwrites X-Forwarded-For with exactly one socket IP.
// A direct caller of Nuxt must not be able to supply that value.
export function forwardedClientIp(peer: string | undefined, forwarded: string | undefined, trustedProxy: string): string | undefined {
  const address = peer?.replace(/^::ffff:/, '')
  if (!address) return undefined
  if (trustedProxy && address === trustedProxy && forwarded && !forwarded.includes(',')) {
    return forwarded.trim()
  }
  return address
}
