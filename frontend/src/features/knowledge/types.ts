interface Ref {
  id: number
  name: string
}

export const ARTICLE_TYPES = ['faq', 'guide', 'solution'] as const
export type ArticleType = (typeof ARTICLE_TYPES)[number]
export const ARTICLE_AUDIENCES = ['all', 'it'] as const
export type ArticleAudience = (typeof ARTICLE_AUDIENCES)[number]
export const ARTICLE_STATUSES = ['draft', 'published', 'archived'] as const
export type ArticleStatus = (typeof ARTICLE_STATUSES)[number]

export interface ArticleRow {
  id: number
  number: string
  title: string
  type: ArticleType
  category: Ref | null
  summary: string | null
  audience: ArticleAudience
  status: ArticleStatus
  published_at: string | null
  updated_at: string
  author: Ref | null
  views: number
  helpful: number
  not_helpful: number
}

export interface Article extends ArticleRow {
  body: string
  attachments: { id: number; file: { uuid: string; name: string; mime: string; size: number } }[]
  source_ticket: { id: number; number: string; title: string } | null
  my_vote: boolean | null
  can: { edit: boolean; delete: boolean }
}

export interface Suggestion {
  id: number
  number: string
  title: string
  summary: string | null
  type: ArticleType
}
