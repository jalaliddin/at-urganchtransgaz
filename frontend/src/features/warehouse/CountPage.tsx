import {
  Alert,
  Anchor,
  Button,
  Card,
  Group,
  Modal,
  NumberInput,
  Progress,
  Stack,
  Table,
  Text,
  TextInput,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useMediaQuery } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconLock, IconPlus, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { formatDate, formatDateTime } from '@/lib/format'
import { addCountLine, cancelCount, closeCount, recordCount } from './api'
import { MaterialSelect } from './Pickers'
import { useCount, useWarehouseMutation } from './queries'
import { CountStatusBadge, qty } from './WarehouseBadges'
import type { Material, StockCountDetail } from './types'

function AddLineModal({ countId, onClose }: { countId: number; onClose: () => void }) {
  const { t } = useTranslation(['warehouse', 'common', 'validation'])
  const [material, setMaterial] = useState<Material | null>(null)
  const [batch, setBatch] = useState('')
  const [expires, setExpires] = useState<string | null>(null)
  const [counted, setCounted] = useState<string>('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const add = useWarehouseMutation(() =>
    addCountLine(countId, {
      material_id: material?.id,
      batch: batch || null,
      expires_at: expires,
      counted_quantity: counted,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('counts.addLine')} centered>
      <Stack>
        <MaterialSelect
          label={t('lines.material')}
          required
          value={material?.id ?? null}
          onChange={setMaterial}
          error={errors.material_id}
        />
        {material?.track_batches && (
          <TextInput
            label={t('lines.batch')}
            required
            value={batch}
            onChange={(e) => setBatch(e.currentTarget.value)}
            error={errors.batch}
          />
        )}
        {material?.track_batches && (
          <DateInput
            label={t('lines.expiresAt')}
            value={expires}
            onChange={setExpires}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.expires_at}
          />
        )}
        <NumberInput
          label={t('counts.counted')}
          required
          min={0}
          decimalScale={3}
          value={counted}
          onChange={(v) => setCounted(String(v))}
          error={errors.counted_quantity}
        />
        <Group justify="flex-end">
          <Button
            loading={add.isPending}
            onClick={() => {
              if (!material || counted === '') {
                setErrors({
                  ...(material ? {} : { material_id: t('validation:required') }),
                  ...(counted === '' ? { counted_quantity: t('validation:required') } : {}),
                })
                return
              }
              add.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => {
                  const error = toApiError(e)
                  setErrors(
                    Object.fromEntries(Object.entries(error.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])),
                  )
                  if (Object.keys(error.fieldErrors).length === 0)
                    notifications.show({ color: 'red', message: error.message })
                },
              })
            }}
          >
            {t('common:actions.add')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function CountTable({ count }: { count: StockCountDetail }) {
  const { t } = useTranslation('warehouse')
  const phone = useMediaQuery('(max-width: 48em)')
  const [draft, setDraft] = useState<Record<number, string>>({})
  const save = useWarehouseMutation((lines: { id: number; counted_quantity: string | null }[]) =>
    recordCount(count.id, lines),
  )
  const dirty = Object.entries(draft).map(([id, v]) => ({
    id: Number(id),
    counted_quantity: v === '' ? null : v,
  }))
  const rows = count.lines.map((l) => {
    const value = draft[l.id] ?? l.counted_quantity ?? ''
    const diff = value === '' ? null : Number(value) - Number(l.expected_quantity)
    return { line: l, value, diff }
  })
  const diffText = (diff: number | null) =>
    diff === null ? '—' : `${diff > 0 ? '+' : ''}${qty(String(diff))}`
  const diffColor = (diff: number | null) =>
    diff === null || diff === 0 ? undefined : diff < 0 ? 'red' : 'green.8'
  const input = (l: StockCountDetail['lines'][number], value: string) =>
    count.can.manage ? (
      <NumberInput
        aria-label={`${t('counts.counted')}: ${l.material.name}${l.batch ? ` ${l.batch}` : ''}`}
        min={0}
        decimalScale={3}
        hideControls
        inputMode="decimal"
        value={value === '' ? '' : Number(value)}
        onChange={(v) => setDraft((d) => ({ ...d, [l.id]: String(v) }))}
        styles={{ input: { textAlign: 'right' } }}
      />
    ) : (
      <Text ta="right" ff="monospace" size="sm">
        {qty(l.counted_quantity)}
      </Text>
    )

  return (
    <Card withBorder p={0}>
      {phone ? (
        <Stack gap={0}>
          {rows.map(({ line: l, value, diff }) => (
            <Stack
              key={l.id}
              gap={6}
              p="sm"
              style={{ borderBottom: '1px solid var(--mantine-color-default-border)' }}
            >
              <Text size="sm" fw={500}>
                {l.material.name}
              </Text>
              <Text size="xs" c="dimmed">
                {l.batch ? `${t('lines.batch')}: ${l.batch} · ` : ''}
                {t('counts.expected')}: {qty(l.expected_quantity)} {l.material.unit}
              </Text>
              <Group gap="sm" wrap="nowrap" align="center">
                <div style={{ flex: 1 }}>{input(l, value)}</div>
                <Text size="sm" ff="monospace" c={diffColor(diff)} w={72} ta="right">
                  {diffText(diff)}
                </Text>
              </Group>
            </Stack>
          ))}
        </Stack>
      ) : (
        <Table.ScrollContainer minWidth={640}>
          <Table verticalSpacing="xs">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{t('fields.material')}</Table.Th>
                <Table.Th>{t('lines.batch')}</Table.Th>
                <Table.Th ta="right">{t('counts.expected')}</Table.Th>
                <Table.Th ta="right" w={160}>
                  {t('counts.counted')}
                </Table.Th>
                <Table.Th ta="right">{t('counts.difference')}</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map(({ line: l, value, diff }) => (
                <Table.Tr key={l.id}>
                  <Table.Td>
                    <Text size="sm">{l.material.name}</Text>
                    <Text size="xs" c="dimmed" ff="monospace">
                      {l.material.code} · {l.material.unit}
                    </Text>
                  </Table.Td>
                  <Table.Td ff="monospace">
                    {l.batch ?? '—'}
                    {l.expires_at ? ` · ${formatDate(l.expires_at)}` : ''}
                  </Table.Td>
                  <Table.Td ta="right" ff="monospace">
                    {qty(l.expected_quantity)}
                  </Table.Td>
                  <Table.Td>{input(l, value)}</Table.Td>
                  <Table.Td ta="right" ff="monospace" c={diffColor(diff)}>
                    {diffText(diff)}
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
      {count.can.manage && (
        <Group justify="flex-end" p="md">
          <Button
            disabled={dirty.length === 0}
            loading={save.isPending}
            fullWidth={phone}
            onClick={() =>
              save.mutate(dirty, {
                onSuccess: () => {
                  setDraft({})
                  notifications.show({ color: 'green', message: t('counts.saved') })
                },
                onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
              })
            }
          >
            {t('counts.save')}
          </Button>
        </Group>
      )}
    </Card>
  )
}

export function CountPage() {
  const { t } = useTranslation(['warehouse', 'common'])
  const id = Number(useParams().id)
  const query = useCount(id)
  const close = useWarehouseMutation(() => closeCount(id))
  const cancel = useWarehouseMutation(() => cancelCount(id))
  const [adding, setAdding] = useState(false)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const count = query.data
  if (!count) return null
  const fail = (e: unknown) =>
    notifications.show({
      color: 'red',
      message: toApiError(e).fieldErrors.lines?.[0] ?? toApiError(e).message,
    })

  return (
    <>
      <PageHeader
        title={`${count.number} — ${count.warehouse.name}`}
        description={`${t('counts.startedAt')}: ${formatDateTime(count.started_at)} · ${count.started_by?.name ?? ''}`}
        meta={<CountStatusBadge status={count.status} />}
        actions={
          count.can.manage && (
            <Group gap="sm">
              <Button variant="default" leftSection={<IconPlus size={16} />} onClick={() => setAdding(true)}>
                {t('counts.addLine')}
              </Button>
              <Button
                variant="subtle"
                color="red"
                leftSection={<IconX size={16} />}
                loading={cancel.isPending}
                onClick={async () => {
                  if (
                    await confirmAction({
                      title: t('counts.cancel'),
                      message: t('counts.cancelConfirm'),
                      confirmLabel: t('counts.cancel'),
                      danger: true,
                    })
                  )
                    cancel.mutate(undefined, { onError: fail })
                }}
              >
                {t('counts.cancel')}
              </Button>
              <Button
                leftSection={<IconCheck size={16} />}
                loading={close.isPending}
                onClick={async () => {
                  if (
                    await confirmAction({
                      title: t('counts.close'),
                      message: t('counts.closeConfirm'),
                      confirmLabel: t('counts.close'),
                    })
                  )
                    close.mutate(undefined, { onError: fail })
                }}
              >
                {t('counts.close')}
              </Button>
            </Group>
          )
        }
      />
      <Stack gap="md">
        {count.status === 'open' && (
          <Alert color="orange" icon={<IconLock size={18} />}>
            {t('counts.frozen')}
          </Alert>
        )}
        <Group gap="md">
          <Progress
            value={count.progress.total ? (count.progress.counted / count.progress.total) * 100 : 0}
            w={240}
          />
          <Text size="sm">
            {t('counts.progressText', { counted: count.progress.counted, total: count.progress.total })}
          </Text>
        </Group>
        {count.adjustment_document && (
          <Text size="sm">
            {t('counts.adjustment')}:{' '}
            <Anchor
              component={Link}
              to={`/warehouse/documents/${count.adjustment_document.id}`}
              ff="monospace"
            >
              {count.adjustment_document.number}
            </Anchor>
          </Text>
        )}
        <CountTable count={count} />
      </Stack>
      {adding && <AddLineModal countId={count.id} onClose={() => setAdding(false)} />}
    </>
  )
}
