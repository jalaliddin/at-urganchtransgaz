export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export interface Resource<T> {
  data: T
}

export interface MessageResponse {
  message: string
}

/** Compact reference to a related record. */
export interface Ref {
  id: number
  name: string
}
