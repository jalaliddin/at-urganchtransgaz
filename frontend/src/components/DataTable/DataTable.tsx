import { Button, Checkbox, Group, Menu, Paper, Skeleton, Stack } from '@mantine/core'
import { IconColumns } from '@tabler/icons-react'
import {
  DataTable as MantineDataTable,
  useDataTableColumns,
  type DataTableColumn,
  type DataTableSortStatus,
} from 'mantine-datatable'
import type { ReactNode } from 'react'
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
  /** Filters shown in one row above the table, next to the column menu. */
  toolbar?: ReactNode
}

/**
 * Server-side table: paging, sorting and column visibility, with loading,
 * empty and error states. Filters go in the toolbar row above the table.
 */
export function DataTable<T extends { id: number | string }>(props: DataTableProps<T>) {
  const { t } = useTranslation('table')
  const { effectiveColumns, columnsToggle, setColumnsToggle, resetColumnsToggle } = useDataTableColumns<T>({
    key: props.storeKey,
    columns: props.columns,
    getInitialValueInEffect: false,
  })

  const titleOf = (accessor: string) => {
    const column = props.columns.find((c) => String(c.accessor) === accessor)
    return typeof column?.title === 'string' ? column.title : accessor
  }
  const visibleCount = columnsToggle.filter((c) => c.toggled).length

  const columnMenu = (
    <Menu position="bottom-end" closeOnItemClick={false}>
      <Menu.Target>
        <Button variant="default" leftSection={<IconColumns size={16} />}>
          {t('columns')}
        </Button>
      </Menu.Target>
      <Menu.Dropdown>
        {columnsToggle.map((column) => (
          <Menu.Item key={column.accessor}>
            <Checkbox
              label={titleOf(column.accessor)}
              checked={column.toggled}
              // At least one column always stays visible.
              disabled={column.toggled && visibleCount === 1}
              onChange={(event) =>
                setColumnsToggle(
                  columnsToggle.map((c) =>
                    c.accessor === column.accessor ? { ...c, toggled: event.currentTarget.checked } : c,
                  ),
                )
              }
            />
          </Menu.Item>
        ))}
        <Menu.Divider />
        <Menu.Item onClick={resetColumnsToggle}>{t('showAllColumns')}</Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )

  return (
    <Stack gap="sm">
      <Group justify="space-between" align="flex-end" gap="sm" wrap="wrap">
        <Group gap="sm" wrap="wrap" style={{ flex: '1 1 auto' }}>
          {props.toolbar}
        </Group>
        {columnMenu}
      </Group>
      <TableBody {...props} columns={effectiveColumns} />
    </Stack>
  )
}

function TableBody<T extends { id: number | string }>(props: DataTableProps<T>) {
  const { t } = useTranslation('table')
  const { meta, params } = props

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
  const shared = {
    storeColumnsKey: props.storeKey,
    columns: props.columns,
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

  // Pagination props are all-or-nothing in mantine-datatable's types, hence two branches.
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
