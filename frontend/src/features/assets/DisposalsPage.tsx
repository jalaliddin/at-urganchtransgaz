import { Anchor, Button, Group, Modal, SegmentedControl, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toApiError } from '@/api/errors'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { decideDisposal } from './api'
import { useAssetMutation, useDisposals } from './queries'
import type { Disposal } from './types'

function DecisionModal({
  disposal,
  approve,
  onClose,
}: {
  disposal: Disposal
  approve: boolean
  onClose: () => void
}) {
  const { t } = useTranslation(['assets', 'validation'])
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const decide = useAssetMutation(() => decideDisposal(disposal.id, approve, note || undefined))

  return (
    <Modal
      opened
      onClose={onClose}
      title={`${t(approve ? 'disposal.approve' : 'disposal.reject')}: ${disposal.asset.inventory_number}`}
      centered
    >
      <Stack>
        <Text size="sm">{disposal.reason}</Text>
        <Textarea
          label={t('disposal.decisionNote')}
          required={!approve}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          error={error}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button
            color={approve ? undefined : 'red'}
            loading={decide.isPending}
            onClick={() => {
              if (!approve && note.trim() === '') {
                setError(t('validation:required'))
                return
              }
              decide.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
              })
            }}
          >
            {t(approve ? 'disposal.approve' : 'disposal.reject')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function DisposalsPage() {
  const { t } = useTranslation('assets')
  const params = useTableParams({ defaultSort: '-requested_at', filterKeys: ['status'] })
  const list = useDisposals(params.apiParams)
  const [deciding, setDeciding] = useState<{ disposal: Disposal; approve: boolean } | null>(null)

  return (
    <>
      <PageHeader title={t('disposal.title')} description={t('disposal.description')} />
      <DataTable<Disposal>
        storeKey="asset-disposals"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('disposal.title')}
        toolbar={
          <SegmentedControl
            aria-label={t('fields.status')}
            value={params.filters.status ?? 'all'}
            onChange={(v) => params.setFilter('status', v === 'all' ? null : v)}
            data={[
              { value: 'all', label: t('filters.allStatuses') },
              ...(['pending', 'approved', 'rejected'] as const).map((s) => ({
                value: s,
                label: t(`disposal.status.${s}`),
              })),
            ]}
          />
        }
        columns={[
          {
            accessor: 'asset',
            title: t('crumb'),
            render: (d) => (
              <Stack gap={0}>
                <Anchor component={Link} to={`/assets/${d.asset.id}`} size="sm" ff="monospace">
                  {d.asset.inventory_number}
                </Anchor>
                <Text size="xs" c="dimmed">
                  {d.asset.name} · {d.asset.organization}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'reason',
            title: t('disposal.reason'),
            render: (d) => (
              <Text size="sm" lineClamp={2}>
                {d.reason}
              </Text>
            ),
          },
          {
            accessor: 'requested_at',
            title: '',
            noWrap: true,
            render: (d) => `${d.requested_by ?? ''} · ${formatDateTime(d.requested_at)}`,
          },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (d) =>
              d.can_decide ? (
                <Group gap={6} wrap="nowrap">
                  <Button size="compact-sm" onClick={() => setDeciding({ disposal: d, approve: true })}>
                    {t('disposal.approve')}
                  </Button>
                  <Button
                    size="compact-sm"
                    variant="default"
                    color="red"
                    onClick={() => setDeciding({ disposal: d, approve: false })}
                  >
                    {t('disposal.reject')}
                  </Button>
                </Group>
              ) : (
                <Text size="sm">{t(`disposal.status.${d.status}`)}</Text>
              ),
          },
        ]}
      />
      {deciding && (
        <DecisionModal
          disposal={deciding.disposal}
          approve={deciding.approve}
          onClose={() => setDeciding(null)}
        />
      )}
    </>
  )
}
