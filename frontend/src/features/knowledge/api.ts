import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { Article, ArticleRow, Suggestion } from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)

export const listArticles = (params: Params) =>
  api.get<Paginated<ArticleRow>>('/kb-articles', { params }).then((r) => r.data)
export const getArticle = (id: number) => data<Article>(api.get(`/kb-articles/${id}`))
export const saveArticle = (values: Values, id?: number) =>
  data<Article>(id ? api.put(`/kb-articles/${id}`, values) : api.post('/kb-articles', values))
export const articleAction = (id: number, action: 'publish' | 'archive') =>
  data<Article>(api.post(`/kb-articles/${id}/${action}`))
export const deleteArticle = (id: number) =>
  api.delete<MessageResponse>(`/kb-articles/${id}`).then((r) => r.data.message)
export const voteArticle = (id: number, helpful: boolean) =>
  data<Article>(api.post(`/kb-articles/${id}/vote`, { helpful }))
export function uploadArticleFiles(id: number, files: File[]) {
  const form = new FormData()
  files.forEach((f) => form.append('files[]', f))
  return data<Article>(api.post(`/kb-articles/${id}/attachments`, form))
}
export const removeArticleFile = (id: number, attachmentId: number) =>
  data<Article>(api.delete(`/kb-articles/${id}/attachments/${attachmentId}`))
export const suggestArticles = (title: string, category: number | null) =>
  data<Suggestion[]>(
    api.get('/kb-articles/suggest', { params: { title, ...(category ? { category } : {}) } }),
  )
