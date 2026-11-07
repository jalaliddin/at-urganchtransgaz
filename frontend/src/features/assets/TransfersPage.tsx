import { Anchor, Button, Group, Modal, SegmentedControl, Select, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { createTransfer, transferDestinations } from './api'
import { TransferStatusBadge } from './AssetBadges'
import { useAssetMutation, useTransfers } from './queries'
import type { Transfer } from './types'

function NewTransferModal({ onClose }: { onClose: (id?: number) => void }) {
  const { t } = useTranslation(['assets', 'common', 'validation'])
  const { data: me } = useMe()
  const destinations = useQuery({ queryKey: ['assets', 'destinations'], queryFn: transferDestinations })
  const [from, setFrom] = useState<number | null>(me?.organization?.id ?? null)
  const [to, setTo] = useState<string | null>(null)
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const create = useAssetMutation(() =>
    createTransfer({
      from_organization_id: from ?? 0,
      to_organization_id: Number(to),
      note: note || undefined,
    }),
  )

  return (
    <Modal opened onClose={() => onClose()} title={t('transfers.create')} centered>
      <Stack>
        <OrganizationSelect label={t('transfers.from')} required onlyActive value={from} onChange={setFrom} />
        <Select
          label={t('transfers.to')}
          required
          searchable
          data={(destinations.data ?? [])
            .filter((o) => o.id !== from)
            .map((o) => ({ value: String(o.id), label: o.name }))}
          value={to}
          onChange={setTo}
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
            loading={create.isPending}
            onClick={() => {
              if (from === null || to === null) {
                setError(t('validation:required'))
                return
              }
              create.mutate(undefined, {
                onSuccess: (transfer) => onClose(transfer.id),
                onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
              })
            }}
          >
            {t('common:actions.create')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function TransfersPage() {
  const { t } = useTranslation('assets')
  const navigate = useNavigate()
  const canTransfer = useCan('assets.transfer')
  const params = useTableParams({ defaultSort: '-id', filterKeys: ['direction', 'status'] })
  const list = useTransfers(params.apiParams)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('transfers.title')}
        description={t('transfers.description')}
        actions={
          canTransfer && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('transfers.create')}
            </Button>
          )
        }
      />
      <DataTable<Transfer>
        storeKey="asset-transfers"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('transfers.empty')}
        onRowClick={(r) => navigate(`/assets/transfers/${r.id}`)}
        toolbar={
          <SegmentedControl
            aria-label={t('transfers.title')}
            value={params.filters.direction ?? 'all'}
            onChange={(v) => params.setFilter('direction', v === 'all' ? null : v)}
            data={[
              { value: 'all', label: t('transfers.title') },
              { value: 'incoming', label: t('transfers.direction.incoming') },
              { value: 'outgoing', label: t('transfers.direction.outgoing') },
            ]}
          />
        }
        columns={[
          {
            accessor: 'number',
            title: t('transfers.number'),
            noWrap: true,
            render: (r) => (
              <Anchor
                component={Link}
                to={`/assets/transfers/${r.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
                fw={500}
              >
                {r.number}
              </Anchor>
            ),
          },
          {
            accessor: 'route',
            title: `${t('transfers.from')} → ${t('transfers.to')}`,
            render: (r) => (
              <Text size="sm">
                {r.from.name} → {r.to.name}
              </Text>
            ),
          },
          { accessor: 'items_count', title: t('transfers.items'), render: (r) => r.items_count },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (r) => <TransferStatusBadge status={r.status} />,
          },
          {
            accessor: 'created_at',
            title: '',
            noWrap: true,
            render: (r) => formatDateTime(r.sent_at ?? r.created_at),
          },
        ]}
      />
      {creating && (
        <NewTransferModal
          onClose={(id) => {
            setCreating(false)
            if (id) navigate(`/assets/transfers/${id}`)
          }}
        />
      )}
    </>
  )
}
