import { ActionIcon, Anchor, Button, Card, Grid, Group, SimpleGrid, Table, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatMoney } from '@/lib/format'
import * as s from './api'
import { Field } from './ContractDetailPage'
import { ExtraInvoiceModal } from './InvoiceDialogs'
import { useInvoices, useService, useServicesMutation } from './queries'
import { InvoiceStatusBadge } from './ServiceBadges'
import { ServiceModal } from './ServiceModal'

export function ServiceDetailPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useService(id)
  const invoices = useInvoices({ 'filter[service]': id, per_page: 24, sort: '-due_date' })
  const canManage = useCan('services.manage')
  const canInvoice = useCan('services.invoice')
  const [dialog, setDialog] = useState<'edit' | 'extra' | null>(null)
  const remove = useServicesMutation(() => s.deleteService(id))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const sv = query.data
  if (!sv) return null

  return (
    <>
      <PageHeader
        title={sv.name}
        description={`${t(`serviceType.${sv.type}`)}, ${sv.provider.name}, ${sv.organization.name}`}
        actions={
          <Group gap="xs">
            {canInvoice && (
              <Button size="xs" variant="default" onClick={() => setDialog('extra')}>
                {t('invoices.addExtra')}
              </Button>
            )}
            {canManage && (
              <>
                <ActionIcon
                  variant="subtle"
                  aria-label={t('common:actions.edit')}
                  onClick={() => setDialog('edit')}
                >
                  <IconPencil size={16} />
                </ActionIcon>
                <ActionIcon
                  variant="subtle"
                  color="red"
                  aria-label={t('common:actions.delete')}
                  onClick={async () => {
                    if (
                      await confirmAction({
                        message: t('services.deleteConfirm'),
                        confirmLabel: t('common:actions.delete'),
                        danger: true,
                      })
                    )
                      remove.mutate(undefined, {
                        onSuccess: () => navigate('/services'),
                        onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                      })
                  }}
                >
                  <IconTrash size={16} />
                </ActionIcon>
              </>
            )}
          </Group>
        }
      />
      <Grid>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <Card withBorder>
            <SimpleGrid cols={{ base: 1, xs: 2, md: 1 }}>
              <Field label={t('services.contract')}>
                {sv.contract ? (
                  <Anchor component={Link} to={`/services/contracts/${sv.contract.id}`} size="sm">
                    {sv.contract.number}
                  </Anchor>
                ) : (
                  '—'
                )}
              </Field>
              <Field label={t('services.identifier')}>{sv.identifier ?? '—'}</Field>
              <Field label={t('services.tariff')}>{sv.tariff ?? '—'}</Field>
              <Field label={t('services.address')}>{sv.address ?? '—'}</Field>
              <Field label={t('services.fee')}>
                {formatMoney(sv.fee)} ({t(`billingCycle.${sv.billing_cycle}`)})
              </Field>
              <Field label={t('services.dueDay')}>{sv.due_day}</Field>
              <Field label={t('services.startedAt')}>{sv.started_at ? formatDate(sv.started_at) : '—'}</Field>
              <Field label={t('services.endedAt')}>{sv.ended_at ? formatDate(sv.ended_at) : '—'}</Field>
              <Field label={t('services.active')}>
                {sv.is_active ? t('common:labels.yes') : t('common:labels.no')}
              </Field>
            </SimpleGrid>
          </Card>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 7 }}>
          <Card withBorder>
            <Title order={4} mb="sm">
              {t('invoices.title')}
            </Title>
            {invoices.data?.data.length === 0 ? (
              <Text c="dimmed">{t('invoices.empty')}</Text>
            ) : (
              <Table.ScrollContainer minWidth={460}>
                <Table highlightOnHover>
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{t('invoices.period')}</Table.Th>
                      <Table.Th ta="right">{t('invoices.amount')}</Table.Th>
                      <Table.Th>{t('invoices.dueDate')}</Table.Th>
                      <Table.Th>{t('fields.status')}</Table.Th>
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {invoices.data?.data.map((i) => (
                      <Table.Tr
                        key={i.id}
                        style={{ cursor: 'pointer' }}
                        onClick={() => navigate(`/services/invoices/${i.id}`)}
                      >
                        <Table.Td>
                          {i.kind === 'extra'
                            ? (i.description ?? t('invoices.extra'))
                            : `${formatDate(i.period_start)} – ${formatDate(i.period_end)}`}
                        </Table.Td>
                        <Table.Td ta="right">{formatMoney(i.amount ?? i.planned_amount)}</Table.Td>
                        <Table.Td>{formatDate(i.due_date)}</Table.Td>
                        <Table.Td>
                          <InvoiceStatusBadge status={i.status} />
                        </Table.Td>
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            )}
          </Card>
        </Grid.Col>
      </Grid>
      {dialog === 'edit' && <ServiceModal service={sv} onClose={() => setDialog(null)} />}
      {dialog === 'extra' && <ExtraInvoiceModal serviceId={sv.id} onClose={() => setDialog(null)} />}
    </>
  )
}
