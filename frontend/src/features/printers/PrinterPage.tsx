import {
  ActionIcon,
  Anchor,
  Badge,
  Button,
  Card,
  Grid,
  Group,
  Pagination,
  Progress,
  SimpleGrid,
  Stack,
  Table,
  Text,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconExternalLink, IconPencil, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { AssetStatusBadge } from '@/features/assets/AssetBadges'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import dayjs from '@/lib/dayjs'
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@/lib/format'
import * as p from './api'
import { CancelledBadge, CartridgeColorLabel } from './PrinterBadges'
import { CancelReplacementModal, CounterModal, ProfileModal, ReplaceModal } from './PrinterDialogs'
import { useCounters, usePrinter, usePrinterMutation, usePrinterReplacements } from './queries'
import type { PrinterDetail, Replacement } from './types'

const PER_PAGE = 10

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <Stack gap={2}>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" component="div">
        {children}
      </Text>
    </Stack>
  )
}

function Section({
  title,
  children,
  extra,
}: {
  title: string
  children: React.ReactNode
  extra?: React.ReactNode
}) {
  return (
    <Card withBorder>
      <Group justify="space-between" mb="sm">
        <Title order={4}>{title}</Title>
        {extra}
      </Group>
      {children}
    </Card>
  )
}

function Cartridges({ printer }: { printer: PrinterDetail }) {
  const { t } = useTranslation('printers')
  if (printer.cartridges.length === 0) return <Text c="dimmed">{t('detail.noCartridges')}</Text>
  return (
    <Stack gap="xs">
      {!printer.compatibility_defined && (
        <Text size="sm" c="dimmed">
          {t('replace.noCompatibility')}
        </Text>
      )}
      <Table.ScrollContainer minWidth={520}>
        <Table>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>{t('fields.cartridge')}</Table.Th>
              <Table.Th>{t('fields.color')}</Table.Th>
              <Table.Th ta="right">{t('fields.pageYield')}</Table.Th>
              <Table.Th ta="right">{t('fields.stock')}</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {printer.cartridges.map((c) => (
              <Table.Tr key={c.id}>
                <Table.Td>
                  <Text size="sm" fw={500}>
                    {c.name}
                  </Text>
                  {c.part_number && (
                    <Text size="xs" c="dimmed" ff="monospace">
                      {c.part_number}
                    </Text>
                  )}
                </Table.Td>
                <Table.Td>
                  <CartridgeColorLabel color={c.color} />
                </Table.Td>
                <Table.Td ta="right">{formatNumber(c.page_yield)}</Table.Td>
                <Table.Td ta="right">
                  <Text size="sm" fw={500} c={Number(c.stock ?? 0) > 0 ? undefined : 'red.8'}>
                    {formatNumber(Number(c.stock ?? 0))}
                  </Text>
                  {c.warehouses.map((w) => (
                    <Text key={w.warehouse.id} size="xs" c="dimmed">
                      {w.warehouse.name}: {formatNumber(Number(w.quantity))}
                    </Text>
                  ))}
                </Table.Td>
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
    </Stack>
  )
}

function History({ printer, onCancel }: { printer: PrinterDetail; onCancel: (r: Replacement) => void }) {
  const { t } = useTranslation('printers')
  const [page, setPage] = useState(1)
  const query = usePrinterReplacements(printer.id, { page, per_page: PER_PAGE })
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const rows = query.data?.data ?? []
  if (query.data && rows.length === 0) return <Text c="dimmed">{t('detail.noReplacements')}</Text>
  return (
    <Stack gap="xs">
      <Table.ScrollContainer minWidth={640}>
        <Table>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>{t('fields.replacedAt')}</Table.Th>
              <Table.Th>{t('fields.cartridge')}</Table.Th>
              <Table.Th>{t('fields.source')}</Table.Th>
              <Table.Th ta="right">{t('fields.counter')}</Table.Th>
              <Table.Th ta="right">{t('fields.pagesSince')}</Table.Th>
              <Table.Th />
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {rows.map((r) => {
              const cancelled = r.cancelled_at !== null
              return (
                <Table.Tr
                  key={r.id}
                  c={cancelled ? 'dimmed' : undefined}
                  td={cancelled ? 'line-through' : undefined}
                >
                  <Table.Td>
                    {formatDate(r.replaced_at)}
                    <Text size="xs" c="dimmed" td="none">
                      {r.performed_by?.name}
                    </Text>
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{r.cartridge.name}</Text>
                    {r.note && (
                      <Text size="xs" c="dimmed">
                        {r.note}
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td>
                    <Text size="sm">{t(`source.${r.source}`)}</Text>
                    {r.stock_document && (
                      <Anchor component={Link} to={`/warehouse/documents/${r.stock_document.id}`} size="xs">
                        {r.stock_document.number}
                      </Anchor>
                    )}
                    {r.cost && (
                      <Text size="xs" c="dimmed">
                        {formatMoney(r.cost)}
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td ta="right">{r.counter ? formatNumber(r.counter.total) : '—'}</Table.Td>
                  <Table.Td ta="right">
                    {r.pages_since_previous === null ? '—' : formatNumber(r.pages_since_previous)}
                    {r.pages_since_previous !== null && r.cartridge.page_yield && (
                      <Text size="xs" c="dimmed">
                        {t('detail.ofYield', { yield: formatNumber(r.cartridge.page_yield) })}
                      </Text>
                    )}
                  </Table.Td>
                  <Table.Td ta="right" td="none">
                    {cancelled ? (
                      <Stack gap={2} align="flex-end">
                        <CancelledBadge />
                        {r.cancel_reason && (
                          <Text size="xs" c="dimmed">
                            {r.cancel_reason}
                          </Text>
                        )}
                      </Stack>
                    ) : (
                      r.can_cancel && (
                        <Button size="compact-xs" variant="subtle" color="red" onClick={() => onCancel(r)}>
                          {t('cancel.action')}
                        </Button>
                      )
                    )}
                  </Table.Td>
                </Table.Tr>
              )
            })}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
      {(query.data?.meta.last_page ?? 1) > 1 && (
        <Pagination size="sm" total={query.data?.meta.last_page ?? 1} value={page} onChange={setPage} />
      )}
    </Stack>
  )
}

function Counters({ printer }: { printer: PrinterDetail }) {
  const { t } = useTranslation(['printers', 'common'])
  const [page, setPage] = useState(1)
  const query = useCounters(printer.id, { page, per_page: PER_PAGE })
  const remove = usePrinterMutation((id: number) => p.deleteCounter(id))
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const rows = query.data?.data ?? []
  if (query.data && rows.length === 0) return <Text c="dimmed">{t('detail.noCounters')}</Text>
  return (
    <Stack gap="xs">
      <Table.ScrollContainer minWidth={480}>
        <Table>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>{t('fields.readAt')}</Table.Th>
              <Table.Th ta="right">{t('fields.total')}</Table.Th>
              <Table.Th ta="right">{t('fields.pages')}</Table.Th>
              <Table.Th>{t('fields.source')}</Table.Th>
              <Table.Th />
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {rows.map((c) => (
              <Table.Tr key={c.id}>
                <Table.Td>{formatDate(c.read_at)}</Table.Td>
                <Table.Td ta="right">
                  {formatNumber(c.total)}
                  {c.is_reset && (
                    <Badge ml={6} size="xs" variant="light" color="orange" tt="none">
                      {t('counter.resetBadge')}
                    </Badge>
                  )}
                </Table.Td>
                <Table.Td ta="right">{c.pages === null ? '—' : formatNumber(c.pages)}</Table.Td>
                <Table.Td>
                  <Text size="sm">{t(`counterSource.${c.source}`)}</Text>
                  {c.note && (
                    <Text size="xs" c="dimmed">
                      {c.note}
                    </Text>
                  )}
                </Table.Td>
                <Table.Td ta="right">
                  {c.can_delete && (
                    <ActionIcon
                      variant="subtle"
                      color="red"
                      aria-label={t('counter.delete')}
                      onClick={async () => {
                        if (
                          await confirmAction({
                            message: t('counter.deleteConfirm', {
                              total: formatNumber(c.total),
                              date: formatDate(c.read_at),
                            }),
                            confirmLabel: t('common:actions.delete'),
                            danger: true,
                          })
                        )
                          remove.mutate(c.id, {
                            onError: (e) =>
                              notifications.show({ color: 'red', message: toApiError(e).message }),
                          })
                      }}
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
      {(query.data?.meta.last_page ?? 1) > 1 && (
        <Pagination size="sm" total={query.data?.meta.last_page ?? 1} value={page} onChange={setPage} />
      )}
    </Stack>
  )
}

/** Pages per month as a bar list; months without readings say so instead of showing zero. */
function MonthlyPages({ printer }: { printer: PrinterDetail }) {
  const { t, i18n } = useTranslation('printers')
  const max = Math.max(1, ...printer.monthly_pages.map((m) => m.pages ?? 0))
  if (printer.monthly_pages.every((m) => m.pages === null))
    return <Text c="dimmed">{t('detail.noPages')}</Text>
  return (
    <Stack gap={6}>
      {[...printer.monthly_pages].reverse().map((m) => (
        <Group key={m.month} gap="sm" wrap="nowrap">
          <Text size="xs" w={70} c="dimmed" tt="capitalize">
            {dayjs(`${m.month}-01`)
              .locale(i18n.language === 'ru' ? 'ru' : 'uz-latn')
              .format('MMM YYYY')}
          </Text>
          <Progress value={((m.pages ?? 0) / max) * 100} size="sm" style={{ flex: 1 }} aria-hidden />
          <Text size="xs" w={64} ta="right">
            {m.pages === null ? '—' : formatNumber(m.pages)}
          </Text>
        </Group>
      ))}
    </Stack>
  )
}

function Repairs({ printer }: { printer: PrinterDetail }) {
  const { t } = useTranslation('printers')
  if (printer.maintenances.length === 0) return <Text c="dimmed">{t('detail.noRepairs')}</Text>
  return (
    <Stack gap="sm">
      {printer.maintenances.map((m) => (
        <Stack key={m.id} gap={2}>
          <Group gap="xs">
            <Badge size="sm" variant="light" color={m.status === 'open' ? 'orange' : 'gray'} tt="none">
              {t(`repair.${m.status}`)}
            </Badge>
            <Text size="xs" c="dimmed">
              {formatDateTime(m.started_at)}
              {m.finished_at ? ` — ${formatDateTime(m.finished_at)}` : ''}
            </Text>
          </Group>
          <Text size="sm">{m.description}</Text>
          {(m.result || m.vendor) && (
            <Text size="xs" c="dimmed">
              {[m.vendor, m.result].filter(Boolean).join(': ')}
            </Text>
          )}
          {m.ticket && (
            <Anchor component={Link} to={`/helpdesk/${m.ticket.id}`} size="xs">
              {m.ticket.number}
            </Anchor>
          )}
        </Stack>
      ))}
    </Stack>
  )
}

export function PrinterPage() {
  const { t } = useTranslation(['printers', 'common'])
  const id = Number(useParams().id)
  const query = usePrinter(id)
  const [dialog, setDialog] = useState<'replace' | 'counter' | 'profile' | null>(null)
  const [cancelling, setCancelling] = useState<Replacement | null>(null)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const printer = query.data
  if (!printer) return null
  const model = printer.model
    ? [printer.model.manufacturer, printer.model.name].filter(Boolean).join(' ')
    : null

  return (
    <>
      <PageHeader
        title={printer.name}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {printer.inventory_number}
            </Text>
            <AssetStatusBadge status={printer.status} />
          </Group>
        }
        description={[model, printer.organization.name].filter(Boolean).join(', ')}
        actions={
          <Group gap="sm">
            {printer.can.service && (
              <Button onClick={() => setDialog('replace')}>{t('replace.title')}</Button>
            )}
            {printer.can.service && (
              <Button variant="default" onClick={() => setDialog('counter')}>
                {t('counter.title')}
              </Button>
            )}
            {printer.can.view_asset && (
              <Button
                variant="subtle"
                component={Link}
                to={`/assets/${printer.id}`}
                rightSection={<IconExternalLink size={14} />}
              >
                {t('detail.asset')}
              </Button>
            )}
          </Group>
        }
      />
      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Stack>
            <Section
              title={t('detail.profile')}
              extra={
                printer.can.service && (
                  <ActionIcon
                    variant="subtle"
                    aria-label={t('profile.title')}
                    onClick={() => setDialog('profile')}
                  >
                    <IconPencil size={16} />
                  </ActionIcon>
                )
              }
            >
              <SimpleGrid cols={{ base: 1, xs: 2, sm: 3 }}>
                <Field label={t('fields.place')}>
                  {[printer.organization.name, printer.department?.name, printer.location?.name, printer.room]
                    .filter(Boolean)
                    .join(', ')}
                </Field>
                <Field label={t('fields.holder')}>
                  {printer.holder ? (
                    <Anchor component={Link} to={`/employees/${printer.holder.id}`} size="sm">
                      {printer.holder.full_name}
                    </Anchor>
                  ) : (
                    '—'
                  )}
                </Field>
                <Field label={t('fields.connection')}>
                  {printer.connection_type ? t(`connection.${printer.connection_type}`) : '—'}
                </Field>
                <Field label={t('fields.ip')}>
                  <Text size="sm" ff="monospace">
                    {[printer.ip_address, printer.hostname].filter(Boolean).join(' / ') || '—'}
                  </Text>
                </Field>
                <Field label={t('fields.serial')}>{printer.serial_number ?? '—'}</Field>
                <Field label={t('fields.isColor')}>
                  {printer.is_color ? t('detail.color') : t('detail.mono')}
                </Field>
                {printer.note && <Field label={t('fields.note')}>{printer.note}</Field>}
              </SimpleGrid>
            </Section>
            <Section title={t('detail.cartridges')}>
              <Cartridges printer={printer} />
            </Section>
            <Section title={t('detail.history')}>
              <History printer={printer} onCancel={setCancelling} />
            </Section>
            <Section title={t('detail.counters')}>
              <Counters printer={printer} />
            </Section>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Stack>
            <Section title={t('detail.monthlyPages')}>
              <MonthlyPages printer={printer} />
            </Section>
            <Section title={t('detail.repairs')}>
              <Repairs printer={printer} />
            </Section>
          </Stack>
        </Grid.Col>
      </Grid>
      {dialog === 'replace' && <ReplaceModal printer={printer} onClose={() => setDialog(null)} />}
      {dialog === 'counter' && <CounterModal printer={printer} onClose={() => setDialog(null)} />}
      {dialog === 'profile' && <ProfileModal printer={printer} onClose={() => setDialog(null)} />}
      {cancelling && <CancelReplacementModal replacement={cancelling} onClose={() => setCancelling(null)} />}
    </>
  )
}
