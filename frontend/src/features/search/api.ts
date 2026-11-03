import { api } from '@/api/client'
import type { Resource } from '@/api/types'

export interface SearchResults {
  organizations?: { id: number; name: string; code: string | null }[]
  employees?: {
    id: number
    full_name: string
    position: string | null
    organization: { id: number; name: string } | null
  }[]
  users?: { id: number; username: string; name: string }[]
}

export async function globalSearch(q: string): Promise<SearchResults> {
  const { data } = await api.get<Resource<SearchResults>>('/search', { params: { q } })
  return data.data
}
