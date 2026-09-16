import { getRequestURL, setResponseHeader } from 'h3'
import { isNoIndexRoute } from '../../app/utils/seo'

export default defineEventHandler((event) => {
  if (isNoIndexRoute(getRequestURL(event).pathname)) {
    setResponseHeader(event, 'X-Robots-Tag', 'noindex, nofollow')
  }
})
