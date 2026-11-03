import { api, ensureCsrfCookie } from '@/api/client'
import { ApiError } from '@/api/errors'
import type { MessageResponse, Resource } from '@/api/types'
import type { Language } from '@/i18n'
import type { ChangePasswordInput, LoginInput, Me, PublicSettings, ResetPasswordInput } from './types'

/** The signed-in user, or null when there is no session. */
export async function fetchMe(): Promise<Me | null> {
  try {
    const { data } = await api.get<Resource<Me>>('/auth/me')
    return data.data
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) return null
    throw error
  }
}

export async function login(input: LoginInput): Promise<Me> {
  await ensureCsrfCookie()
  const { data } = await api.post<Resource<Me>>('/auth/login', input)
  return data.data
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

export async function updateMyLocale(locale: Language): Promise<Me> {
  const { data } = await api.patch<Resource<Me>>('/auth/me', { locale })
  return data.data
}

export async function changePassword(input: ChangePasswordInput): Promise<string> {
  const { data } = await api.put<MessageResponse>('/auth/password', input)
  return data.message
}

export async function requestPasswordReset(login: string): Promise<string> {
  await ensureCsrfCookie()
  const { data } = await api.post<MessageResponse>('/auth/forgot-password', { login })
  return data.message
}

export async function resetPassword(input: ResetPasswordInput): Promise<string> {
  await ensureCsrfCookie()
  const { data } = await api.post<MessageResponse>('/auth/reset-password', input)
  return data.message
}

export async function fetchPublicSettings(): Promise<PublicSettings> {
  const { data } = await api.get<Resource<PublicSettings>>('/settings/public')
  return data.data
}
