import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as notificationsApi from './api'

export const notificationKeys = {
  all: ['notifications'] as const,
  unread: ['notifications', 'unread-count'] as const,
  list: (params: Record<string, string | number>) => ['notifications', 'list', params] as const,
}

export function useUnreadCount() {
  return useQuery({
    queryKey: notificationKeys.unread,
    queryFn: notificationsApi.fetchUnreadCount,
    refetchInterval: 60_000,
  })
}

export function useNotifications(params: Record<string, string | number>) {
  return useQuery({
    queryKey: notificationKeys.list(params),
    queryFn: () => notificationsApi.fetchNotifications(params),
    placeholderData: keepPreviousData,
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: notificationsApi.markNotificationRead,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: notificationsApi.markAllNotificationsRead,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: notificationKeys.all }),
  })
}
