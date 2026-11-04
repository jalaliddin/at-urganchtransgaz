import { api } from '@/api/client'
import type { MessageResponse, Paginated, Ref, Resource } from '@/api/types'

export const DOCUMENT_TYPES = [
  'contract',
  'act',
  'requisition',
  'invoice',
  'technical_passport',
  'warranty',
  'order',
  'letter',
  'other',
] as const
export type DocumentType = (typeof DOCUMENT_TYPES)[number]

export interface DocumentVersion {
  id: number
  version: number
  note: string | null
  file: { uuid: string; name: string; mime: string; size: number }
  uploaded_by: Ref | null
  created_at: string | null
}

export interface DocumentLinkRow {
  id: number
  type: string
  target: { id: number; number?: string; title?: string }
}

export interface DocumentRecord {
  id: number
  organization: Ref | null
  type: DocumentType
  number: string | null
  title: string
  document_date: string | null
  description: string | null
  current_version: DocumentVersion | null
  versions?: DocumentVersion[]
  links?: DocumentLinkRow[]
  can_manage: boolean
  created_at: string | null
  updated_at: string | null
}

export interface DocumentInput {
  organization_id: number
  type: DocumentType
  number: string | null
  title: string
  document_date: string | null
  description: string | null
}

export async function listDocuments(
  params: Record<string, string | number>,
): Promise<Paginated<DocumentRecord>> {
  const { data } = await api.get<Paginated<DocumentRecord>>('/documents', { params })
  return data
}

export async function getDocument(id: number): Promise<DocumentRecord> {
  const { data } = await api.get<Resource<DocumentRecord>>(`/documents/${id}`)
  return data.data
}

export async function createDocument(input: DocumentInput, file: File | null): Promise<DocumentRecord> {
  const form = new FormData()
  Object.entries(input).forEach(([key, value]) => {
    if (value !== null && value !== '') form.append(key, String(value))
  })
  if (file) form.append('file', file)
  const { data } = await api.post<Resource<DocumentRecord>>('/documents', form)
  return data.data
}

export async function updateDocument(id: number, input: Partial<DocumentInput>): Promise<DocumentRecord> {
  const { data } = await api.put<Resource<DocumentRecord>>(`/documents/${id}`, input)
  return data.data
}

export async function deleteDocument(id: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/documents/${id}`)
  return data.message
}

export async function addVersion(id: number, file: File, note: string): Promise<DocumentVersion> {
  const form = new FormData()
  form.append('file', file)
  if (note.trim() !== '') form.append('note', note.trim())
  const { data } = await api.post<Resource<DocumentVersion>>(`/documents/${id}/versions`, form)
  return data.data
}

export async function linkDocument(id: number, type: string, targetId: number): Promise<DocumentLinkRow> {
  const { data } = await api.post<Resource<DocumentLinkRow>>(`/documents/${id}/links`, { type, id: targetId })
  return data.data
}

export async function unlinkDocument(id: number, linkId: number): Promise<string> {
  const { data } = await api.delete<MessageResponse>(`/documents/${id}/links/${linkId}`)
  return data.message
}
