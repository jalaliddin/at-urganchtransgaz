import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { setLanguage, type Language } from '@/i18n'
import * as authApi from './api'
import { resetClientSession } from './session'
import type { Me } from './types'

export const meQueryKey = ['auth', 'me'] as const

export function useMe() {
  return useQuery({ queryKey: meQueryKey, queryFn: authApi.fetchMe, staleTime: 5 * 60_000 })
}

/** The signed-in user; only call below RequireAuth. */
export function useCurrentUser(): Me {
  const { data } = useMe()
  if (!data) throw new Error('useCurrentUser() used outside RequireAuth')
  return data
}

export function usePublicSettings() {
  return useQuery({
    queryKey: ['settings', 'public'],
    queryFn: authApi.fetchPublicSettings,
    staleTime: 30 * 60_000,
  })
}

export function useLogin() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: authApi.login,
    onSuccess: async (me) => {
      // Nothing cached by a previous session on this device may leak into this one.
      queryClient.clear()
      queryClient.setQueryData(meQueryKey, me)
      await setLanguage(me.locale)
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: authApi.logout,
    onSettled: () => resetClientSession(queryClient),
  })
}

export function useChangePassword() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: authApi.changePassword,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: meQueryKey }),
  })
}

export function useUpdateMyLocale() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (locale: Language) => authApi.updateMyLocale(locale),
    onSuccess: async (me) => {
      queryClient.setQueryData(meQueryKey, me)
      await setLanguage(me.locale)
    },
  })
}

export function useRequestPasswordReset() {
  return useMutation({ mutationFn: authApi.requestPasswordReset })
}

export function useResetPassword() {
  return useMutation({ mutationFn: authApi.resetPassword })
}
