import { api } from '@/api/client'
import type { Paginated, Resource } from '@/api/types'
import type { AppNotification } from './types'

export async function fetchNotifications(params: Record<string, string | number> = {}): Promise<Paginated<AppNotification>> {
  const { data } = await api.get<Paginated<AppNotification>>('/notifications', { params })
  return data
}

export async function fetchUnreadCount(): Promise<number> {
  const { data } = await api.get<Resource<{ count: number }>>('/notifications/unread-count')
  return data.data.count
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.post(`/notifications/${id}/read`)
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all')
}
