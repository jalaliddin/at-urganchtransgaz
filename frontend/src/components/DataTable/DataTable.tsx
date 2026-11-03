import { Paper, Skeleton, Stack } from '@mantine/core'
import {
  DataTable as MantineDataTable,
  useDataTableColumns,
  type DataTableColumn,
  type DataTableSortStatus,
} from 'mantine-datatable'
import { useMemo, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { PaginationMeta } from '@/api/types'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import type { TableParams } from '@/hooks/useTableParams'

const PAGE_SIZES = [10, 25, 50, 100]

export interface DataTableProps<T> {
  /** Unique key: column visibility is remembered per table on this device. */
  storeKey: string
  params: TableParams
  columns: DataTableColumn<T>[]
  records?: T[]
  meta?: PaginationMeta
  isLoading: boolean
  isFetching?: boolean
  error?: unknown
  onRetry: () => void
  emptyTitle: string
  emptyDescription?: ReactNode
  emptyAction?: ReactNode
  onRowClick?: (record: T) => void
}

/**
 * Server-side table: paging, sorting and column visibility, with loading,
 * empty and error states. Right-click a header to hide/show columns.
 */
export function DataTable<T extends { id: number | string }>(props: DataTableProps<T>) {
  const { t } = useTranslation('table')
  const columns = useMemo(() => props.columns.map((c) => ({ toggleable: true, ...c })), [props.columns])
  const { effectiveColumns } = useDataTableColumns<T>({
    key: props.storeKey,
    columns,
    getInitialValueInEffect: false,
  })

  if (props.error) {
    return (
      <Paper withBorder>
        <ErrorState error={props.error} onRetry={props.onRetry} />
      </Paper>
    )
  }

  if (props.isLoading && !props.records) {
    return (
      <Paper withBorder p="md" data-testid="table-skeleton" aria-busy="true">
        <Stack gap="sm">
          {Array.from({ length: 6 }, (_, i) => (
            <Skeleton key={i} height={28} radius="sm" />
          ))}
        </Stack>
      </Paper>
    )
  }

  const records = props.records ?? []
  const { meta, params } = props

  const shared = {
    storeColumnsKey: props.storeKey,
    columns: effectiveColumns,
    records,
    idAccessor: 'id' as const,
    minHeight: records.length ? undefined : 260,
    fetching: props.isFetching,
    highlightOnHover: true,
    verticalSpacing: 'sm' as const,
    horizontalSpacing: 'md' as const,
    noRecordsText: props.emptyTitle,
    emptyState: <EmptyState title={props.emptyTitle} description={props.emptyDescription} action={props.emptyAction} />,
    sortStatus: params.sortStatus as DataTableSortStatus<T>,
    onSortStatusChange: params.setSortStatus as (status: DataTableSortStatus<T>) => void,
    onRowClick: props.onRowClick ? ({ record }: { record: T }) => props.onRowClick?.(record) : undefined,
  }

  return (
    <Paper withBorder style={{ overflow: 'hidden' }}>
      {meta && records.length > 0 ? (
        <MantineDataTable<T>
          {...shared}
          totalRecords={meta.total}
          recordsPerPage={meta.per_page}
          page={meta.current_page}
          onPageChange={params.setPage}
          recordsPerPageOptions={PAGE_SIZES}
          onRecordsPerPageChange={params.setPerPage}
          recordsPerPageLabel={t('perPage')}
          paginationText={({ from, to, totalRecords }) => t('pagination', { from, to, total: totalRecords })}
        />
      ) : (
        <MantineDataTable<T> {...shared} />
      )}
    </Paper>
  )
}
