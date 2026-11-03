import { api } from '@/api/client'
import type { Resource } from '@/api/types'

export interface SettingRow {
  key: string
  type: 'string' | 'integer' | 'boolean'
  value: string | number | boolean | null
  default: string | number | boolean | null
  public: boolean
}

export async function fetchSettings(): Promise<SettingRow[]> {
  const { data } = await api.get<Resource<SettingRow[]>>('/settings')
  return data.data
}

export async function updateSettings(values: Record<string, SettingRow['value']>): Promise<SettingRow[]> {
  const { data } = await api.put<Resource<SettingRow[]>>('/settings', { values })
  return data.data
}
