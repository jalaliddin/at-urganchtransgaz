import type { DataTableSortStatus } from 'mantine-datatable'
import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'
import { useUiStore } from '@/stores/ui'

export interface TableParams {
  page: number
  perPage: number
  /** API sort string, e.g. "-created_at" */
  sort: string
  search: string
  filters: Record<string, string>
  sortStatus: DataTableSortStatus<Record<string, unknown>>
  setPage: (page: number) => void
  setPerPage: (perPage: number) => void
  setSortStatus: (status: DataTableSortStatus<never>) => void
  setSearch: (search: string) => void
  setFilter: (key: string, value: string | null) => void
  resetFilters: () => void
  /** Query parameters for the list endpoint (docs/API.md §3). */
  apiParams: Record<string, string | number>
}

interface Options {
  defaultSort: string
  defaultPerPage?: number
  /** Restrict which filter[...] keys are read from the URL. */
  filterKeys?: string[]
  /** Use the header's organization context when the URL has no organization filter. */
  organizationContext?: boolean
}

const FILTER = /^filter\[(.+)\]$/

/**
 * Table state (page, size, sort, search, filters) lives in the URL so a filtered
 * list can be bookmarked or shared. Any change except paging returns to page 1.
 */
export function useTableParams({ defaultSort, defaultPerPage = 25, filterKeys, organizationContext = false }: Options): TableParams {
  const [params, setParams] = useSearchParams()
  const orgContextId = useUiStore((s) => s.orgContextId)

  const page = Math.max(1, Number.parseInt(params.get('page') ?? '', 10) || 1)
  const perPage = Number.parseInt(params.get('per_page') ?? '', 10) || defaultPerPage
  const sort = params.get('sort') || defaultSort
  const search = params.get('search') ?? ''

  const filters = useMemo(() => {
    const result: Record<string, string> = {}
    params.forEach((value, key) => {
      const name = FILTER.exec(key)?.[1]
      if (name && value !== '' && (!filterKeys || filterKeys.includes(name))) result[name] = value
    })
    return result
  }, [params, filterKeys])

  const update = useCallback(
    (mutate: (next: URLSearchParams) => void, resetPage = true) => {
      setParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          mutate(next)
          if (resetPage) next.delete('page')
          return next
        },
        { replace: true },
      )
    },
    [setParams],
  )

  const apiParams = useMemo(() => {
    const result: Record<string, string | number> = { page, per_page: perPage, sort }
    if (search.trim()) result.search = search.trim()
    for (const [key, value] of Object.entries(filters)) result[`filter[${key}]`] = value
    if (organizationContext && orgContextId !== null && !('organization' in filters)) {
      result['filter[organization]'] = orgContextId
    }
    return result
  }, [page, perPage, sort, search, filters, organizationContext, orgContextId])

  const direction = sort.startsWith('-') ? 'desc' : 'asc'

  return {
    page,
    perPage,
    sort,
    search,
    filters,
    sortStatus: { columnAccessor: sort.replace(/^-/, ''), direction },
    setPage: (value) => update((next) => (value > 1 ? next.set('page', String(value)) : next.delete('page')), false),
    setPerPage: (value) => update((next) => next.set('per_page', String(value))),
    setSortStatus: (status) =>
      update((next) => next.set('sort', `${status.direction === 'desc' ? '-' : ''}${String(status.columnAccessor)}`)),
    setSearch: (value) => update((next) => (value ? next.set('search', value) : next.delete('search'))),
    setFilter: (key, value) =>
      update((next) => (value === null || value === '' ? next.delete(`filter[${key}]`) : next.set(`filter[${key}]`, value))),
    resetFilters: () =>
      update((next) => {
        for (const key of [...next.keys()]) if (FILTER.test(key) || key === 'search') next.delete(key)
      }),
    apiParams,
  }
}
