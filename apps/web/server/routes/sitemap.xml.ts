import { createError, setResponseHeader } from 'h3'
import { renderSitemap } from '../../app/utils/seo'
import { collectPublicSitemapPaths } from '../utils/public-sitemap'

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig(event)
  const apiBaseUrl = config.apiBaseUrl.replace(/\/$/, '')

  try {
    const paths = await collectPublicSitemapPaths(apiBaseUrl)

    setResponseHeader(event, 'Content-Type', 'application/xml; charset=UTF-8')
    setResponseHeader(event, 'Cache-Control', 'public, max-age=3600')
    return renderSitemap(config.public.siteUrl, paths)
  } catch (error) {
    throw createError({
      cause: error,
      statusCode: 503,
      statusMessage: 'Sitemap temporarily unavailable'
    })
  }
})
