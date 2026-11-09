import { ActionIcon, Anchor, Card, Grid, Group, SimpleGrid, Stack, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { LinkedDocuments } from '@/components/LinkedDocuments'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatMoney } from '@/lib/format'
import * as s from './api'
import { ContractModal } from './ContractModal'
import { useContract, useServices, useServicesMutation } from './queries'
import { ContractStatusBadge } from './ServiceBadges'

export function Field({ label, children }: { label: string; children: React.ReactNode }) {
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

export function ContractDetailPage() {
  const { t } = useTranslation(['services', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useContract(id)
  const services = useServices({ 'filter[contract]': id, per_page: 100 })
  const canManage = useCan('services.manage')
  const [editing, setEditing] = useState(false)
  const remove = useServicesMutation(() => s.deleteContract(id))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const c = query.data
  if (!c) return null

  return (
    <>
      <PageHeader
        title={t('contracts.titleOne', { number: c.number })}
        meta={<ContractStatusBadge status={c.status} />}
        description={`${c.provider.name}, ${c.organization.name}`}
        actions={
          canManage && (
            <Group gap="xs">
              <ActionIcon
                variant="subtle"
                aria-label={t('common:actions.edit')}
                onClick={() => setEditing(true)}
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
                      message: t('contracts.deleteConfirm'),
                      confirmLabel: t('common:actions.delete'),
                      danger: true,
                    })
                  )
                    remove.mutate(undefined, {
                      onSuccess: () => navigate('/services/contracts'),
                      onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                    })
                }}
              >
                <IconTrash size={16} />
              </ActionIcon>
            </Group>
          )
        }
      />
      <Grid>
        <Grid.Col span={{ base: 12, md: 7 }}>
          <Stack>
            <Card withBorder>
              <SimpleGrid cols={{ base: 1, xs: 2 }}>
                <Field label={t('contracts.subject')}>{c.subject ?? '—'}</Field>
                <Field label={t('contracts.signedAt')}>{c.signed_at ? formatDate(c.signed_at) : '—'}</Field>
                <Field label={t('contracts.validFrom')}>
                  {c.valid_from ? formatDate(c.valid_from) : '—'}
                </Field>
                <Field label={t('contracts.validTo')}>{c.valid_to ? formatDate(c.valid_to) : '—'}</Field>
                <Field label={t('contracts.amount')}>{formatMoney(c.amount)}</Field>
                <Field label={t('contracts.terminatedAt')}>
                  {c.terminated_at ? formatDate(c.terminated_at) : '—'}
                </Field>
              </SimpleGrid>
              {c.note && (
                <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>
                  {c.note}
                </Text>
              )}
            </Card>
            <Card withBorder>
              <Title order={4} mb="sm">
                {t('contracts.services')}
              </Title>
              {services.data?.data.length === 0 && <Text c="dimmed">{t('services.empty')}</Text>}
              <Stack gap={6}>
                {services.data?.data.map((sv) => (
                  <Group key={sv.id} justify="space-between" wrap="nowrap">
                    <Anchor component={Link} to={`/services/${sv.id}`} size="sm">
                      {sv.name}
                    </Anchor>
                    <Text size="sm" c="dimmed">
                      {formatMoney(sv.fee)}
                    </Text>
                  </Group>
                ))}
              </Stack>
            </Card>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <Card withBorder>
            <Title order={4} mb="sm">
              {t('documents')}
            </Title>
            <LinkedDocuments
              queryKey={['services', 'contract-documents', c.id]}
              fetch={() => s.contractDocuments(c.id)}
            />
          </Card>
        </Grid.Col>
      </Grid>
      {editing && <ContractModal contract={c} onClose={() => setEditing(false)} />}
    </>
  )
}
