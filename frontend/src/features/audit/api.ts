import { api } from '@/api/client'
import type { Paginated } from '@/api/types'
import type { AuditEntry } from '@/features/dashboard/api'

export async function listAuditLogs(params: Record<string, string | number>): Promise<Paginated<AuditEntry>> {
  const { data } = await api.get<Paginated<AuditEntry>>('/audit-logs', { params })
  return data
}
