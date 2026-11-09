import {
  ActionIcon,
  Anchor,
  Button,
  Card,
  Code,
  Grid,
  Group,
  Modal,
  SimpleGrid,
  Stack,
  Table,
  Text,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconEye, IconPencil, IconTrash } from '@tabler/icons-react'
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
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import * as s from './api'
import { useSim, useSimMutation } from './queries'
import { SimActionModal } from './SimActionModal'
import { SimStatusBadge } from './SimBadges'
import { SimFormModal } from './SimFormModal'
import { formatPhone, SIM_ACTIONS, type SimAction, type SimDetail, type SimHistory } from './types'

const HOLDER_ACTIONS: SimAction[] = ['assign', 'return', 'block', 'unblock']
const CENTRAL_ACTIONS: SimAction[] = ['transfer', 'replace', 'cancel']

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

/** One line per history event, in the user's language; payload values are plain data. */
function eventLine(h: SimHistory, t: (key: string, o?: Record<string, unknown>) => string): string {
  const p = h.payload
  switch (h.event) {
    case 'assigned':
      return [p.employee, p.department].filter(Boolean).join(', ')
    case 'returned':
    case 'registered':
      return p.date ? formatDate(String(p.date)) : ''
    case 'blocked':
    case 'cancelled':
      return p.reason ? String(p.reason) : ''
    case 'unblocked':
      return p.status ? t(`status.${String(p.status)}`) : ''
    case 'replaced':
      return t('history.replaced', { old: p.old_last4 ?? '—', new: p.new_last4 ?? '—' })
    case 'allocated':
      return p.requisition ? t('history.requisition', { number: p.requisition }) : ''
    case 'updated':
      return Object.keys(p)
        .map((k) => t(`history.fields.${k}`))
        .join(', ')
    default:
      return p.note ? String(p.note) : ''
  }
}

function Assignments({ card }: { card: SimDetail }) {
  const { t } = useTranslation('sim')
  if (card.assignments.length === 0) return <Text c="dimmed">{t('assignments.empty')}</Text>
  return (
    <Table.ScrollContainer minWidth={420}>
      <Table>
        <Table.Thead>
          <Table.Tr>
            <Table.Th>{t('fields.holder')}</Table.Th>
            <Table.Th>{t('fields.allocatedAt')}</Table.Th>
            <Table.Th>{t('fields.returnedAt')}</Table.Th>
          </Table.Tr>
        </Table.Thead>
        <Table.Tbody>
          {card.assignments.map((a) => (
            <Table.Tr key={a.id}>
              <Table.Td>
                {a.employee ? (
                  <Anchor component={Link} to={`/employees/${a.employee.id}`} size="sm">
                    {a.employee.full_name}
                  </Anchor>
                ) : (
                  a.department?.name
                )}
                {a.employee && a.department && (
                  <Text size="xs" c="dimmed">
                    {a.department.name}
                  </Text>
                )}
              </Table.Td>
              <Table.Td>{formatDate(a.assigned_at)}</Table.Td>
              <Table.Td>{a.returned_at ? formatDate(a.returned_at) : t('assignments.current')}</Table.Td>
            </Table.Tr>
          ))}
        </Table.Tbody>
      </Table>
    </Table.ScrollContainer>
  )
}

export function SimDetailPage() {
  const { t } = useTranslation(['sim', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useSim(id)
  const canManage = useCan('sim.manage')
  const canHolder = useCan(['sim.assign', 'sim.manage'], 'any')
  const [action, setAction] = useState<SimAction | null>(null)
  const [editing, setEditing] = useState(false)
  const [iccid, setIccid] = useState<string | null>(null)
  const reveal = useSimMutation(() => s.revealIccid(id))
  const remove = useSimMutation(() => s.deleteSim(id))
  const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const card = query.data
  if (!card) return null

  const actions = [...(canHolder ? HOLDER_ACTIONS : []), ...(canManage ? CENTRAL_ACTIONS : [])].filter((a) =>
    SIM_ACTIONS[a](card),
  )

  return (
    <>
      <PageHeader
        title={formatPhone(card.phone_number)}
        meta={<SimStatusBadge status={card.status} />}
        description={`${card.provider.name}, ${card.organization.name}`}
        actions={
          <Group gap="xs">
            {actions.map((a) => (
              <Button
                key={a}
                size="xs"
                variant={a === 'cancel' ? 'light' : 'default'}
                color={a === 'cancel' ? 'red' : undefined}
                onClick={() => setAction(a)}
              >
                {t(`actions.${a}`)}
              </Button>
            ))}
            {canManage && (
              <ActionIcon
                variant="subtle"
                aria-label={t('common:actions.edit')}
                onClick={() => setEditing(true)}
              >
                <IconPencil size={16} />
              </ActionIcon>
            )}
            {canManage && card.status === 'in_stock' && card.assignments.length === 0 && (
              <ActionIcon
                variant="subtle"
                color="red"
                aria-label={t('common:actions.delete')}
                onClick={async () => {
                  if (
                    await confirmAction({
                      message: t('form.deleteConfirm'),
                      confirmLabel: t('common:actions.delete'),
                      danger: true,
                    })
                  )
                    remove.mutate(undefined, { onSuccess: () => navigate('/sim'), onError: fail })
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
                <Field label={t('fields.holder')}>
                  {card.employee ? (
                    <Anchor component={Link} to={`/employees/${card.employee.id}`} size="sm">
                      {card.employee.full_name}
                    </Anchor>
                  ) : (
                    (card.department?.name ?? '—')
                  )}
                </Field>
                <Field label={t('fields.department')}>{card.department?.name ?? '—'}</Field>
                <Field label={t('fields.iccid')}>
                  <Group gap={4} wrap="nowrap">
                    <Code>{card.iccid_masked ?? '—'}</Code>
                    {canManage && card.has_iccid && (
                      <ActionIcon
                        variant="subtle"
                        size="sm"
                        aria-label={t('reveal')}
                        loading={reveal.isPending}
                        onClick={() =>
                          reveal.mutate(undefined, {
                            onSuccess: (r) => setIccid(r.iccid ?? ''),
                            onError: fail,
                          })
                        }
                      >
                        <IconEye size={14} />
                      </ActionIcon>
                    )}
                  </Group>
                </Field>
                <Field label={t('fields.tariff')}>{card.tariff ?? '—'}</Field>
                <Field label={t('fields.account')}>{card.account_number ?? '—'}</Field>
                <Field label={t('fields.monthlyFee')}>{formatMoney(card.monthly_fee)}</Field>
                <Field label={t('fields.allocatedAt')}>
                  {card.allocated_at ? formatDate(card.allocated_at) : '—'}
                </Field>
                <Field label={t('fields.returnedAt')}>
                  {card.returned_at ? formatDate(card.returned_at) : '—'}
                </Field>
              </SimpleGrid>
              {card.note && (
                <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>
                  {card.note}
                </Text>
              )}
            </Card>
            <Card withBorder>
              <Title order={4} mb="sm">
                {t('assignments.title')}
              </Title>
              <Assignments card={card} />
            </Card>
            <Card withBorder>
              <Title order={4} mb="sm">
                {t('documents')}
              </Title>
              <LinkedDocuments
                queryKey={['sim', 'documents', card.id]}
                fetch={() => s.simDocuments(card.id)}
              />
            </Card>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 5 }}>
          <Card withBorder>
            <Title order={4} mb="sm">
              {t('history.title')}
            </Title>
            <Timeline bulletSize={12} lineWidth={2}>
              {card.history.map((h) => (
                <Timeline.Item key={h.id} title={t(`events.${h.event}`)}>
                  <Text size="sm">{eventLine(h, t)}</Text>
                  <Text size="xs" c="dimmed">
                    {formatDateTime(h.created_at)}
                    {h.user ? `, ${h.user.name}` : ''}
                  </Text>
                  {h.requisition_id && (
                    <Anchor component={Link} to={`/requisitions/${h.requisition_id}`} size="xs">
                      {t('history.openRequisition')}
                    </Anchor>
                  )}
                </Timeline.Item>
              ))}
            </Timeline>
          </Card>
        </Grid.Col>
      </Grid>
      {action && <SimActionModal card={card} action={action} onClose={() => setAction(null)} />}
      {editing && <SimFormModal card={card} onClose={() => setEditing(false)} />}
      {iccid !== null && (
        <Modal opened onClose={() => setIccid(null)} title={t('fields.iccid')} centered>
          <Stack>
            <Code block data-testid="sim-iccid">
              {iccid}
            </Code>
            <Text size="xs" c="dimmed">
              {t('revealAudited')}
            </Text>
          </Stack>
        </Modal>
      )}
    </>
  )
}
