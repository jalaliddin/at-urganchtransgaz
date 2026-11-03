import type { TFunction } from 'i18next'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ApiError } from '@/api/errors'

/**
 * Maps a 422 response onto form fields. Returns true when at least one field
 * error was applied (otherwise the caller shows a general error).
 */
export function applyApiErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>): boolean {
  if (!(error instanceof ApiError) || error.status !== 422) return false

  let applied = false
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    const message = messages[0]
    if (message) {
      setError(field as Path<T>, { type: 'server', message })
      applied = true
    }
  }
  return applied
}

/**
 * Zod errors carry translation keys (validation namespace); server errors carry
 * ready-to-show text.
 */
export function fieldError(t: TFunction, error: { type?: unknown; message?: unknown } | undefined): string | undefined {
  const message = typeof error?.message === 'string' ? error.message : undefined
  if (!message) return undefined
  return error?.type === 'server' ? message : t(`validation:${message}`)
}
