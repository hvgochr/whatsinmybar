import { setResponseHeader } from 'h3'
import { publicUrl } from '../../app/utils/public-content'

export default defineEventHandler((event) => {
  const config = useRuntimeConfig(event)
  setResponseHeader(event, 'Content-Type', 'text/plain; charset=UTF-8')

  return [
    'User-agent: *',
    'Disallow: /admin',
    'Disallow: /settings',
    'Disallow: /recipes/new',
    'Disallow: /recipes/*/edit',
    `Sitemap: ${publicUrl(config.public.siteUrl, '/sitemap.xml')}`,
    ''
  ].join('\n')
})
