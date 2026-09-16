interface SitemapCollection<T> {
  'hydra:member'?: T[]
  'hydra:view'?: { 'hydra:next'?: string }
  member?: T[]
  view?: { next?: string }
}

const noIndexRoutes = new Set(['/login', '/logout', '/recipes/new', '/register', '/settings'])

export function isNoIndexRoute(path: string): boolean {
  const normalized = path.length > 1 ? path.replace(/\/+$/, '') : path
  return normalized === '/admin'
    || normalized.startsWith('/admin/')
    || noIndexRoutes.has(normalized)
    || /^\/recipes\/[^/]+\/edit$/.test(normalized)
}

export function sitemapItems<T>(collection: SitemapCollection<T> | T[]): T[] {
  if (Array.isArray(collection)) return collection
  return collection.member ?? collection['hydra:member'] ?? []
}

export function sitemapHasNext<T>(collection: SitemapCollection<T> | T[]): boolean {
  if (Array.isArray(collection)) return false
  return Boolean(collection.view?.next ?? collection['hydra:view']?.['hydra:next'])
}

export function renderSitemap(siteUrl: string, paths: Iterable<string>): string {
  const urls = Array.from(new Set(paths), path => new URL(path, normalizedSiteUrl(siteUrl)).toString()).sort()
  const entries = urls.map(url => `  <url><loc>${escapeXml(url)}</loc></url>`).join('\n')

  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${entries}\n</urlset>\n`
}

function normalizedSiteUrl(siteUrl: string): string {
  return siteUrl.endsWith('/') ? siteUrl : `${siteUrl}/`
}

function escapeXml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&apos;')
}
