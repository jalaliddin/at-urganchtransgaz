import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { api, configureApiHandlers } from '@/api/client'
import { ApiError, toApiError } from '@/api/errors'
import { server } from '@/test/server'

describe('api client', () => {
  it('retries once after 419 with a fresh CSRF cookie', async () => {
    let posts = 0
    let csrf = 0
    server.use(
      http.get('*/sanctum/csrf-cookie', () => {
        csrf++
        return new HttpResponse(null, { status: 204 })
      }),
      http.post('*/api/v1/things', () => {
        posts++
        return posts === 1
          ? HttpResponse.json({ message: 'expired', code: 'csrf_token_mismatch' }, { status: 419 })
          : HttpResponse.json({ data: { ok: true } })
      }),
    )

    const response = await api.post('/things')

    expect(response.data).toEqual({ data: { ok: true } })
    expect(csrf).toBe(1)
    expect(posts).toBe(2)
  })

  it('does not loop on repeated 419', async () => {
    let posts = 0
    server.use(
      http.post('*/api/v1/things', () => {
        posts++
        return HttpResponse.json({ message: 'expired', code: 'csrf_token_mismatch' }, { status: 419 })
      }),
    )

    const error = await api.post('/things').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(419)
    expect(posts).toBe(2)
  })

  it('normalises 422 into fieldErrors', async () => {
    server.use(
      http.post('*/api/v1/users', () =>
        HttpResponse.json(
          { message: "Kiritilgan ma'lumotlar noto'g'ri.", code: 'validation_error', errors: { username: ['Bu login band.'] } },
          { status: 422 },
        ),
      ),
    )

    const error = toApiError(await api.post('/users').catch((e: unknown) => e))

    expect(error.code).toBe('validation_error')
    expect(error.fieldErrors.username?.[0]).toBe('Bu login band.')
  })

  it('reports 401 and password_change_required to the registered handlers', async () => {
    const onUnauthenticated = vi.fn()
    const onPasswordChangeRequired = vi.fn()
    configureApiHandlers({ onUnauthenticated, onPasswordChangeRequired })
    server.use(
      http.get('*/api/v1/private', () => HttpResponse.json({ message: 'x', code: 'unauthenticated' }, { status: 401 })),
      http.get('*/api/v1/gated', () =>
        HttpResponse.json({ message: 'x', code: 'password_change_required' }, { status: 403 }),
      ),
    )

    await api.get('/private').catch(() => undefined)
    await api.get('/gated').catch(() => undefined)

    expect(onUnauthenticated).toHaveBeenCalledTimes(1)
    expect(onPasswordChangeRequired).toHaveBeenCalledTimes(1)
    configureApiHandlers({})
  })

  it('turns network failures into an ApiError with status 0', () => {
    const error = toApiError(new Error('Network Error'))

    expect(error.status).toBe(0)
    expect(error.code).toBe('network_error')
  })
})
