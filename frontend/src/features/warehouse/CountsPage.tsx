import { Anchor, Button, Group, Modal, Progress, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { startCount } from './api'
import { WarehouseSelect } from './Pickers'
import { useCounts, useWarehouseMutation } from './queries'
import { CountStatusBadge } from './WarehouseBadges'
import type { StockCount } from './types'

function StartModal({ onClose }: { onClose: (id?: number) => void }) {
  const { t } = useTranslation(['warehouse', 'common', 'validation'])
  const [warehouseId, setWarehouseId] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const start = useWarehouseMutation(() => startCount(warehouseId ?? 0, note || undefined))

  return (
    <Modal opened onClose={() => onClose()} title={t('counts.start')} centered>
      <Stack>
        <Text size="sm" c="dimmed">
          {t('counts.freezeHint')}
        </Text>
        <WarehouseSelect
          label={t('fields.warehouse')}
          required
          value={warehouseId}
          onChange={setWarehouseId}
          error={error}
        />
        <Textarea
          label={t('fields.note')}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={start.isPending}
            onClick={() => {
              if (!warehouseId) {
                setError(t('validation:required'))
                return
              }
              start.mutate(undefined, {
                onSuccess: (count) => onClose(count.id),
                onError: (e) =>
                  setError(toApiError(e).fieldErrors.warehouse_id?.[0] ?? toApiError(e).message),
              })
            }}
          >
            {t('counts.start')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function CountsPage() {
  const { t } = useTranslation('warehouse')
  const navigate = useNavigate()
  const canCount = useCan('warehouse.inventory')
  const params = useTableParams({ defaultSort: '-id', filterKeys: ['warehouse', 'status'] })
  const list = useCounts(params.apiParams)
  const [starting, setStarting] = useState(false)

  return (
    <>
      <PageHeader
        title={t('counts.title')}
        description={t('counts.description')}
        actions={
          canCount && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setStarting(true)}>
              {t('counts.start')}
            </Button>
          )
        }
      />
      <DataTable<StockCount>
        storeKey="stock-counts"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('counts.empty')}
        onRowClick={(c) => navigate(`/warehouse/counts/${c.id}`)}
        columns={[
          {
            accessor: 'number',
            title: t('fields.number'),
            render: (c) => (
              <Anchor
                component={Link}
                to={`/warehouse/counts/${c.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
              >
                {c.number}
              </Anchor>
            ),
          },
          { accessor: 'warehouse', title: t('fields.warehouse'), render: (c) => c.warehouse.name },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (c) => <CountStatusBadge status={c.status} />,
          },
          {
            accessor: 'progress',
            title: t('counts.progress'),
            render: (c) => (
              <Stack gap={2} w={160}>
                <Progress
                  value={c.progress.total ? (c.progress.counted / c.progress.total) * 100 : 0}
                  size="sm"
                />
                <Text size="xs" c="dimmed">
                  {c.progress.counted} / {c.progress.total}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'started_at',
            title: t('counts.startedAt'),
            render: (c) => `${formatDateTime(c.started_at)} · ${c.started_by?.name ?? ''}`,
          },
        ]}
      />
      {starting && (
        <StartModal
          onClose={(id) => {
            setStarting(false)
            if (id) navigate(`/warehouse/counts/${id}`)
            else if (id === undefined) return
            notifications.show({ color: 'green', message: t('counts.started') })
          }}
        />
      )}
    </>
  )
}
