import {
  ActionIcon,
  Anchor,
  Button,
  Card,
  FileButton,
  Grid,
  Group,
  SimpleGrid,
  Stack,
  Table,
  Text,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPaperclip, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import * as s from './api'
import { Field } from './ContractDetailPage'
import { EnterInvoiceModal, PaymentModal } from './InvoiceDialogs'
import { useInvoice, useServicesMutation } from './queries'
import { InvoiceStatusBadge } from './ServiceBadges'
import type { StoredFileRef } from './types'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

function FileLink({ file }: { file: StoredFileRef | null }) {
  const { t } = useTranslation('services')
  if (!file) return <Text c="dimmed">{t('invoices.noFile')}</Text>
  return (
    <Anchor href={fileUrl(file.uuid)} size="sm">
      {file.name}
    </Anchor>
  )
}

export function InvoiceDetailPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useInvoice(id)
  const canInvoice = useCan('services.invoice')
  const canApprove = useCan('services.approve')
  const canPay = useCan('services.pay')
  const canManage = useCan('services.manage')
  const [dialog, setDialog] = useState<'enter' | 'pay' | null>(null)
  const approve = useServicesMutation(() => s.approveInvoice(id))
  const uploadInvoice = useServicesMutation((file: File) => s.uploadInvoiceFile(id, file))
  const uploadPayment = useServicesMutation((args: { paymentId: number; file: File }) =>
    s.uploadPaymentFile(args.paymentId, args.file),
  )
  const removePayment = useServicesMutation((paymentId: number) => s.deletePayment(paymentId))
  const remove = useServicesMutation(() => s.deleteInvoice(id))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const inv = query.data
  if (!inv) return null

  const entered = inv.amount !== null
  const remaining = entered ? Number(inv.amount) - Number(inv.paid_amount) : 0
  const title = inv.kind === 'extra' ? (inv.description ?? inv.service.name) : inv.service.name

  return (
    <>
      <PageHeader
        title={title}
        meta={<InvoiceStatusBadge status={inv.status} />}
        description={
          inv.kind === 'extra'
            ? t('invoices.extraOf', { service: inv.service.name })
            : t('invoices.periodOf', { from: formatDate(inv.period_start), to: formatDate(inv.period_end) })
        }
        actions={
          <Group gap="xs">
            {canInvoice && inv.status !== 'paid' && (
              <Button variant="default" size="xs" onClick={() => setDialog('enter')}>
                {entered ? t('invoices.change') : t('invoices.enter')}
              </Button>
            )}
            {canApprove && entered && !inv.approved_at && (
              <Button
                size="xs"
                loading={approve.isPending}
                onClick={() =>
                  approve.mutate(undefined, {
                    onSuccess: () => notifications.show({ color: 'green', message: t('invoices.approved') }),
                    onError: fail,
                  })
                }
              >
                {t('invoices.approve')}
              </Button>
            )}
            {canPay && inv.approved_at && remaining > 0 && (
              <Button size="xs" onClick={() => setDialog('pay')}>
                {t('payments.add')}
              </Button>
            )}
            {canManage && inv.kind === 'extra' && inv.payments.length === 0 && (
              <ActionIcon
                variant="subtle"
                color="red"
                aria-label={t('common:actions.delete')}
                onClick={async () => {
                  if (
                    await confirmAction({
                      message: t('invoices.deleteConfirm'),
                      confirmLabel: t('common:actions.delete'),
                      danger: true,
                    })
                  )
                    remove.mutate(undefined, {
                      onSuccess: () => navigate('/services/invoices'),
                      onError: fail,
                    })
                }}
              >
                <IconTrash size={16} />
              </ActionIcon>
            )}
          </Group>
        }
      />
      <Grid>
        <Grid.Col span={{ base: 12, md: 7 }}>
          <Stack>
            <Card withBorder>
              <SimpleGrid cols={{ base: 1, xs: 2 }}>
                <Field label={t('invoices.service')}>
                  <Anchor component={Link} to={`/services/${inv.service.id}`} size="sm">
                    {inv.service.name}
                  </Anchor>
                </Field>
                <Field label={t('fields.provider')}>{inv.service.provider.name}</Field>
                <Field label={t('fields.organization')}>{inv.organization.name}</Field>
                <Field label={t('invoices.planned')}>{formatMoney(inv.planned_amount)}</Field>
                <Field label={t('invoices.number')}>{inv.invoice_number ?? '—'}</Field>
                <Field label={t('invoices.date')}>
                  {inv.invoice_date ? formatDate(inv.invoice_date) : '—'}
                </Field>
                <Field label={t('invoices.amount')}>
                  {entered ? formatMoney(inv.amount) : t('invoices.notEntered')}
                </Field>
                <Field label={t('invoices.dueDate')}>{formatDate(inv.due_date)}</Field>
                <Field label={t('invoices.paid')}>{formatMoney(inv.paid_amount)}</Field>
                <Field label={t('invoices.approval')}>
                  {inv.approved_at
                    ? t('invoices.approvedBy', {
                        name: inv.approved_by?.name ?? '',
                        date: formatDateTime(inv.approved_at),
                      })
                    : entered
                      ? t('invoices.approvalPending')
                      : '—'}
                </Field>
              </SimpleGrid>
              {inv.note && (
                <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>
                  {inv.note}
                </Text>
              )}
            </Card>
            <Card withBorder>
              <Title order={4} mb="sm">
                {t('payments.title')}
              </Title>
              {inv.payments.length === 0 ? (
                <Text c="dimmed">{t('payments.empty')}</Text>
              ) : (
                <Table.ScrollContainer minWidth={480}>
                  <Table>
                    <Table.Thead>
                      <Table.Tr>
                        <Table.Th>{t('payments.paidAt')}</Table.Th>
                        <Table.Th ta="right">{t('payments.amount')}</Table.Th>
                        <Table.Th>{t('payments.document')}</Table.Th>
                        <Table.Th>{t('payments.file')}</Table.Th>
                        {canPay && <Table.Th w={72} />}
                      </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                      {inv.payments.map((p) => (
                        <Table.Tr key={p.id}>
                          <Table.Td>{formatDate(p.paid_at)}</Table.Td>
                          <Table.Td ta="right">{formatMoney(p.amount)}</Table.Td>
                          <Table.Td>{p.document_number ?? '—'}</Table.Td>
                          <Table.Td>{p.file ? <FileLink file={p.file} /> : '—'}</Table.Td>
                          {canPay && (
                            <Table.Td>
                              <Group gap={2} wrap="nowrap">
                                <FileButton
                                  onChange={(file) =>
                                    file && uploadPayment.mutate({ paymentId: p.id, file }, { onError: fail })
                                  }
                                  accept="application/pdf,image/*"
                                >
                                  {(props) => (
                                    <ActionIcon {...props} variant="subtle" aria-label={t('payments.attach')}>
                                      <IconPaperclip size={16} />
                                    </ActionIcon>
                                  )}
                                </FileButton>
                                <ActionIcon
                                  variant="subtle"
                                  color="red"
                                  aria-label={t('payments.delete')}
                                  onClick={async () => {
                                    if (
                                      await confirmAction({
                                        message: t('payments.deleteConfirm'),
                                        confirmLabel: t('common:actions.delete'),
                                        danger: true,
                                      })
                                    )
                                      removePayment.mutate(p.id, { onError: fail })
                                  }}
                                >
                                  <IconTrash size={16} />
                                </ActionIcon>
                              </Group>
                            </Table.Td>
                          )}
                        </Table.Tr>
                      ))}
                    </Table.Tbody>
                  </Table>
                </Table.ScrollContainer>
              )}
            </Card>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <Card withBorder>
            <Title order={4} mb="sm">
              {t('invoices.scan')}
            </Title>
            <Stack gap="sm">
              <FileLink file={inv.file} />
              {canInvoice && (
                <FileButton
                  onChange={(file) => file && uploadInvoice.mutate(file, { onError: fail })}
                  accept="application/pdf,image/*"
                >
                  {(props) => (
                    <Button
                      {...props}
                      variant="default"
                      size="xs"
                      leftSection={<IconPaperclip size={14} />}
                      loading={uploadInvoice.isPending}
                    >
                      {inv.file ? t('invoices.replaceFile') : t('invoices.attach')}
                    </Button>
                  )}
                </FileButton>
              )}
            </Stack>
          </Card>
        </Grid.Col>
      </Grid>
      {dialog === 'enter' && <EnterInvoiceModal invoice={inv} onClose={() => setDialog(null)} />}
      {dialog === 'pay' && <PaymentModal invoice={inv} onClose={() => setDialog(null)} />}
    </>
  )
}
