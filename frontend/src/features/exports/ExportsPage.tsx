import { Anchor, Badge, Group, Text, type MantineColor } from '@mantine/core'
import { IconDownload } from '@tabler/icons-react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime, formatNumber } from '@/lib/format'
import { fileUrl, listExports, type ExportStatus, type ReportExport } from './api'

const COLORS: Record<ExportStatus, MantineColor> = {
  pending: 'gray',
  processing: 'cobalt',
  done: 'green',
  failed: 'red',
}

/** Poll while anything is still being generated; stop once every export settled. */
export const POLL_MS = 5000

type Row = ReportExport & { id: string }

export function pollInterval(rows: ReportExport[] | undefined): number | false {
  return rows?.some((e) => e.status === 'pending' || e.status === 'processing') ? POLL_MS : false
}

export function ExportsPage() {
  const { t } = useTranslation('exports')
  const params = useTableParams({ defaultSort: '-created_at' })
  const query = useQuery({
    queryKey: ['exports', params.apiParams],
    queryFn: () => listExports(params.apiParams),
    placeholderData: keepPreviousData,
    refetchInterval: (q) => pollInterval(q.state.data?.data),
  })

  return (
    <>
      <PageHeader title={t('title')} description={t('description')} />
      <DataTable<Row>
        storeKey="exports"
        params={params}
        records={query.data?.data.map((e) => ({ ...e, id: e.uuid }))}
        meta={query.data?.meta}
        isLoading={query.isPending}
        isFetching={query.isFetching}
        error={query.error}
        onRetry={() => void query.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={t('empty.description')}
        columns={[
          {
            accessor: 'type',
            title: t('fields.report'),
            render: (e) => t(`reports.${e.type}`, { defaultValue: e.type }),
          },
          { accessor: 'format', title: t('fields.format'), render: (e) => e.format.toUpperCase() },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (e) => (
              <Group gap={6} wrap="nowrap">
                <Badge color={COLORS[e.status]} variant="light" tt="none">
                  {t(`status.${e.status}`)}
                </Badge>
                {e.error && (
                  <Text size="xs" c="red">
                    {e.error}
                  </Text>
                )}
              </Group>
            ),
          },
          {
            accessor: 'row_count',
            title: t('fields.rows'),
            render: (e) => (e.row_count === null ? '—' : formatNumber(e.row_count)),
          },
          {
            accessor: 'created_at',
            title: t('fields.createdAt'),
            noWrap: true,
            render: (e) => formatDateTime(e.created_at),
          },
          {
            accessor: 'file',
            title: t('fields.file'),
            render: (e) =>
              e.file ? (
                <Anchor href={fileUrl(e.file.uuid)} size="sm">
                  <Group gap={4} wrap="nowrap">
                    <IconDownload size={14} aria-hidden />
                    {t('download')}
                  </Group>
                </Anchor>
              ) : (
                '—'
              ),
          },
        ]}
      />
    </>
  )
}
