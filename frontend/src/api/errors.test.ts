import { AxiosError, AxiosHeaders } from 'axios'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { toApiError } from './errors'

function httpError(status: number, data: unknown): AxiosError {
  const config = { headers: new AxiosHeaders() }
  return new AxiosError('fail', 'ERR', config, null, { status, statusText: '', headers: {}, config, data })
}

describe('toApiError', () => {
  it('explains a request rejected by the web server for its size', () => {
    const error = toApiError(httpError(413, '<html><body>413 Request Entity Too Large</body></html>'))

    expect(error.status).toBe(413)
    expect(error.message).toBe(i18n.t('common:errors.tooLarge'))
  })

  it('keeps the API message for JSON errors', () => {
    expect(toApiError(httpError(409, { message: 'Band', code: 'conflict' })).message).toBe('Band')
  })
})
