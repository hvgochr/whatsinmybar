import { ApiRequestError } from '../services/api-client'

export interface FormErrors {
  fields: Record<string, string>
  message: string | null
}

const genericMessage = 'Something went wrong. Please try again.'

export function toFormErrors(error: unknown): FormErrors {
  if (!(error instanceof ApiRequestError)) {
    return {
      fields: {},
      message: genericMessage
    }
  }

  const fields: Record<string, string> = {}

  for (const violation of error.violations) {
    const field = normalizePropertyPath(violation.property)
    if (field && !fields[field]) {
      fields[field] = violation.message
    }
  }

  return {
    fields,
    message: Object.keys(fields).length > 0 ? null : friendlyErrorMessage(error)
  }
}

export function normalizePropertyPath(propertyPath: string): string {
  return propertyPath
    .replace(/^\[/, '')
    .replace(/\]$/, '')
    .replace(/\]\[/g, '.')
}

function friendlyErrorMessage(error: ApiRequestError): string {
  if (error.status === 401) {
    return 'Please check your credentials and try again.'
  }

  if (error.status === 403) {
    return 'You do not have permission to perform this action.'
  }

  if (error.status === 404) {
    return 'We could not find what you were looking for.'
  }

  if (error.status === 0) {
    return 'The API is currently unreachable.'
  }

  return genericMessage
}
