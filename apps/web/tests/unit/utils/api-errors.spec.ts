import { describe, expect, it } from 'vitest'
import { ApiRequestError } from '../../../app/services/api-client'
import { normalizePropertyPath, toFormErrors } from '../../../app/utils/api-errors'

describe('api error form helpers', () => {
  it('explains rate limits and the Retry-After delay without blaming credentials', () => {
    expect(toFormErrors(new ApiRequestError('Too many requests.', 429, 'too_many_requests', null, [], 1501)).message)
      .toBe('Too many attempts. Please try again in 2 seconds.')
    expect(toFormErrors(new ApiRequestError('Too many requests.', 429, 'too_many_requests')).message)
      .toBe('Too many attempts. Please wait a moment before trying again.')
  })

  it('normalizes Symfony collection property paths', () => {
    expect(normalizePropertyPath('[email]')).toBe('email')
    expect(normalizePropertyPath('[profile][bio]')).toBe('profile.bio')
  })

  it('maps validation violations to field errors', () => {
    const error = new ApiRequestError('Validation failed.', 422, 'validation_failed', null, [
      { property: '[email]', message: 'Invalid email.' }
    ])

    expect(toFormErrors(error)).toEqual({
      fields: { email: 'Invalid email.' },
      message: null
    })
  })

  it('returns a friendly credential error', () => {
    const error = new ApiRequestError('JWT failure.', 401, 'unauthorized')

    expect(toFormErrors(error)).toEqual({
      fields: {},
      message: 'Please check your credentials and try again.'
    })
  })
})
