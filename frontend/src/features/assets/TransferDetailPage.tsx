import { ActionIcon, Anchor, Button, Card, Group, Select, Stack, Table, Text, Title } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconFileTypePdf, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { formatDateTime } from '@/lib/format'
import { addTransferItems, removeTransferItem, transferAction } from './api'
import { TransferStatusBadge } from './AssetBadges'
import { useAssetMutation, useAssets, useTransfer } from './queries'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

export function TransferDetailPage() {
  const { t } = useTranslation(['assets', 'common'])
  const id = Number(useParams().id)
  const query = useTransfer(id)
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const transfer = query.data
  const candidates = useAssets(
    {
      search: debounced,
      per_page: 15,
      'filter[organization]': transfer?.from.id ?? 0,
      'filter[status]': 'in_stock,in_use,reserve',
    },
    Boolean(transfer?.can.edit) && debounced.length >= 2,
  )
  const add = useAssetMutation((assetId: number) => addTransferItems(id, [assetId]))
  const remove = useAssetMutation((assetId: number) => removeTransferItem(id, assetId))
  const act = useAssetMutation((action: 'send' | 'receive' | 'cancel') => transferAction(id, action))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  if (!transfer) return null

  const run = async (action: 'send' | 'receive' | 'cancel', message: string) => {
    const ok = await confirmAction({
      title: t(`transfers.${action}`),
      message,
      confirmLabel: t(`transfers.${action}`),
      danger: action === 'cancel',
    })
    if (ok) act.mutate(action, { onError: fail })
  }

  return (
    <>
      <PageHeader
        title={`${transfer.from.name} → ${transfer.to.name}`}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {transfer.number}
            </Text>
            <TransferStatusBadge status={transfer.status} />
          </Group>
        }
        actions={
          <Group gap="sm">
            {transfer.act && (
              <Button
                component="a"
                href={fileUrl(transfer.act.uuid)}
                variant="default"
                leftSection={<IconFileTypePdf size={16} />}
              >
                {t('transfers.act')}
              </Button>
            )}
            {transfer.can.send && (
              <Button
                onClick={() => void run('send', t('transfers.sendConfirm'))}
                disabled={transfer.items_count === 0}
                loading={act.isPending}
              >
                {t('transfers.send')}
              </Button>
            )}
            {transfer.can.receive && (
              <Button
                onClick={() => void run('receive', t('transfers.receiveConfirm'))}
                loading={act.isPending}
              >
                {t('transfers.receive')}
              </Button>
            )}
            {transfer.can.cancel && (
              <Button variant="subtle" color="red" onClick={() => void run('cancel', transfer.number)}>
                {t('transfers.cancel')}
              </Button>
            )}
          </Group>
        }
      />
      <Stack gap="md">
        {transfer.note && <Text size="sm">{transfer.note}</Text>}
        <Text size="xs" c="dimmed">
          {[
            transfer.created_by,
            formatDateTime(transfer.created_at),
            transfer.sent_at && `${t('transfers.status.sent')}: ${formatDateTime(transfer.sent_at)}`,
            transfer.received_at &&
              `${t('transfers.status.received')}: ${formatDateTime(transfer.received_at)}`,
          ]
            .filter(Boolean)
            .join(' · ')}
        </Text>
        <Card withBorder p={0}>
          <Group justify="space-between" p="md" pb={0}>
            <Title order={3} fz="sm">
              {t('transfers.items')} ({transfer.items_count})
            </Title>
          </Group>
          {transfer.can.edit && (
            <Select
              m="md"
              mb={0}
              aria-label={t('transfers.addAssets')}
              placeholder={t('transfers.addAssets')}
              searchable
              searchValue={search}
              onSearchChange={setSearch}
              filter={({ options }) => options}
              data={(candidates.data?.data ?? [])
                .filter((a) => !a.in_transfer)
                .map((a) => ({ value: String(a.id), label: `${a.inventory_number} — ${a.name}` }))}
              value={null}
              onChange={(v) => {
                if (v) add.mutate(Number(v), { onSuccess: () => setSearch(''), onError: fail })
              }}
            />
          )}
          <Table.ScrollContainer minWidth={560}>
            <Table verticalSpacing="xs">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.inventory_number')}</Table.Th>
                  <Table.Th>{t('fields.name')}</Table.Th>
                  <Table.Th>{t('fields.serial_number')}</Table.Th>
                  <Table.Th w={48} />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {transfer.items?.map((item) => (
                  <Table.Tr key={item.asset.id}>
                    <Table.Td>
                      <Anchor component={Link} to={`/assets/${item.asset.id}`} size="sm" ff="monospace">
                        {item.asset.inventory_number}
                      </Anchor>
                    </Table.Td>
                    <Table.Td>
                      {item.asset.name}
                      <Text size="xs" c="dimmed">
                        {item.asset.category}
                      </Text>
                    </Table.Td>
                    <Table.Td ff="monospace">{item.asset.serial_number ?? '—'}</Table.Td>
                    <Table.Td>
                      {transfer.can.edit && (
                        <ActionIcon
                          variant="subtle"
                          color="red"
                          aria-label={`${t('common:actions.delete')}: ${item.asset.inventory_number}`}
                          onClick={() => remove.mutate(item.asset.id, { onError: fail })}
                        >
                          <IconTrash size={16} />
                        </ActionIcon>
                      )}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
      </Stack>
    </>
  )
}
