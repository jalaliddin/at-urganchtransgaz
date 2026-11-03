import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import i18n from '@/i18n'
import { toApiError } from './errors'

/**
 * The single HTTP client. Sanctum SPA auth: session cookie + XSRF-TOKEN cookie echoed
 * as the X-XSRF-TOKEN header. Every failure is rejected as an ApiError.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

interface Handlers {
  /** Session missing or expired (401). */
  onUnauthenticated?: () => void
  /** The backend requires a password change before anything else (403). */
  onPasswordChangeRequired?: () => void
}

let handlers: Handlers = {}

/** Registered once by the app shell (keeps the client free of router/query imports). */
export function configureApiHandlers(next: Handlers): void {
  handlers = next
}

type RetriableConfig = InternalAxiosRequestConfig & { _csrfRetried?: boolean }

api.interceptors.request.use((config) => {
  config.headers.set('Accept-Language', i18n.language)
  return config
})

api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config as RetriableConfig | undefined
  const status = error.response?.status

  // CSRF token expired: refresh the cookie and replay the request once.
  if (status === 419 && config && !config._csrfRetried) {
    config._csrfRetried = true
    await ensureCsrfCookie()
    return api.request(config)
  }

  const apiError = toApiError(error)
  if (status === 401) handlers.onUnauthenticated?.()
  if (status === 403 && apiError.code === 'password_change_required') handlers.onPasswordChangeRequired?.()

  return Promise.reject(apiError)
})
