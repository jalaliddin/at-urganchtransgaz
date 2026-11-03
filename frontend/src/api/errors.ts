import { isAxiosError } from 'axios'
import i18n from '@/i18n'

/** Normalised API failure (see docs/API.md §2). status 0 = no response (network). */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly fieldErrors: Record<string, string[]>
  readonly requestId?: string

  constructor(options: {
    status: number
    code: string
    message: string
    fieldErrors?: Record<string, string[]>
    requestId?: string
  }) {
    super(options.message)
    this.name = 'ApiError'
    this.status = options.status
    this.code = options.code
    this.fieldErrors = options.fieldErrors ?? {}
    this.requestId = options.requestId
  }
}

interface ErrorBody {
  message?: unknown
  code?: unknown
  errors?: unknown
  request_id?: unknown
}

export function toApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  if (isAxiosError(error) && error.response) {
    const body = (error.response.data ?? {}) as ErrorBody
    return new ApiError({
      status: error.response.status,
      code: typeof body.code === 'string' ? body.code : 'http_error',
      message: typeof body.message === 'string' ? body.message : i18n.t('common:errors.generic'),
      fieldErrors: isFieldErrors(body.errors) ? body.errors : {},
      requestId: typeof body.request_id === 'string' ? body.request_id : undefined,
    })
  }

  return new ApiError({ status: 0, code: 'network_error', message: i18n.t('common:errors.network') })
}

function isFieldErrors(value: unknown): value is Record<string, string[]> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}
