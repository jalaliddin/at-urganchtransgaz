import { api } from '@/api/client'
import type { Paginated, Resource } from '@/api/types'

export type ExportFormat = 'xlsx' | 'pdf'
export type ExportStatus = 'pending' | 'processing' | 'done' | 'failed'

export interface ReportExport {
  uuid: string
  type: string
  format: ExportFormat
  filters: Record<string, unknown>
  status: ExportStatus
  row_count: number | null
  error: string | null
  file: { uuid: string; name: string; size: number } | null
  created_at: string
  finished_at: string | null
}

export async function requestExport(
  type: string,
  format: ExportFormat,
  filters: Record<string, string>,
): Promise<ReportExport> {
  const { data } = await api.post<Resource<ReportExport>>('/exports', { type, format, filters })
  return data.data
}

export async function listExports(params: Record<string, string | number>): Promise<Paginated<ReportExport>> {
  const { data } = await api.get<Paginated<ReportExport>>('/exports', { params })
  return data
}

/** Same-origin download through the guarded file endpoint (session cookie auth). */
export function fileUrl(uuid: string, inline = false): string {
  return `/api/v1/files/${uuid}${inline ? '?inline=1' : ''}`
}
