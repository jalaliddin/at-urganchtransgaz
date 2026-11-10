import { Alert, Card, Grid, Group, Rating, SimpleGrid, Stack, Tabs, Text, Title } from '@mantine/core'
import { IconClockPause, IconMessages, IconTimeline } from '@tabler/icons-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { ApiError } from '@/api/errors'
import { formatDateTime } from '@/lib/format'
import { useTicket } from './queries'
import { TicketActions } from './TicketActions'
import { PriorityBadge, SlaBadge, TicketStatusBadge } from './TicketBadges'
import { TicketConversation } from './TicketConversation'
import { TicketHistory } from './TicketHistory'
import { AttachmentsPanel, DocumentsPanel, LinksPanel } from './TicketSidePanels'
import { TicketKnowledgePanel } from '@/features/knowledge/TicketKnowledgePanel'
import { TicketProblemsPanel } from '@/features/problems/TicketProblemsPanel'
import { TicketTasksPanel } from '@/features/tasks/TicketTasksPanel'

function Fact({ label, children }: { label: string; children: ReactNode }) {
  return (
    <Stack gap={0}>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" component="div">
        {children}
      </Text>
    </Stack>
  )
}

export function TicketDetailPage() {
  const { t } = useTranslation('helpdesk')
  const id = Number(useParams().id)
  const query = useTicket(id)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const ticket = query.data
  if (!ticket) return null

  return (
    <>
      <PageHeader
        title={ticket.title}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {ticket.number}
            </Text>
            <TicketStatusBadge status={ticket.status} />
            <PriorityBadge priority={ticket.priority} />
            <SlaBadge ticket={ticket} showDue />
          </Group>
        }
      />
      <Stack gap="md">
        <TicketActions ticket={ticket} />
        {ticket.paused_at && (
          <Alert color="yellow" icon={<IconClockPause size={18} />}>
            {t('detail.paused')}
          </Alert>
        )}
        <Grid gap="md">
          <Grid.Col span={{ base: 12, md: 8 }}>
            <Stack gap="md">
              <Card withBorder radius="md" p="md">
                <Text size="sm" style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>
                  {ticket.description}
                </Text>
                {ticket.resolution && (
                  <Alert color="green" mt="md" title={t('fields.resolution')}>
                    <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                      {ticket.resolution}
                    </Text>
                  </Alert>
                )}
                {ticket.reject_reason && (
                  <Alert color="red" mt="md" title={t('fields.rejectReason')}>
                    <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                      {ticket.reject_reason}
                    </Text>
                  </Alert>
                )}
              </Card>
              <Tabs defaultValue="conversation" keepMounted={false}>
                <Tabs.List>
                  <Tabs.Tab value="conversation" leftSection={<IconMessages size={16} />}>
                    {t('detail.conversation')}
                  </Tabs.Tab>
                  <Tabs.Tab value="history" leftSection={<IconTimeline size={16} />}>
                    {t('detail.history')}
                  </Tabs.Tab>
                </Tabs.List>
                <Tabs.Panel value="conversation" pt="md">
                  <TicketConversation ticket={ticket} />
                </Tabs.Panel>
                <Tabs.Panel value="history" pt="md">
                  <TicketHistory ticketId={ticket.id} />
                </Tabs.Panel>
              </Tabs>
            </Stack>
          </Grid.Col>
          <Grid.Col span={{ base: 12, md: 4 }}>
            <Stack gap="md">
              <Card withBorder radius="md" p="md">
                <Title order={4} fz="sm" mb="sm">
                  {t('detail.facts')}
                </Title>
                <SimpleGrid cols={{ base: 2, md: 1 }} spacing="sm">
                  <Fact label={t('fields.organization')}>{ticket.organization?.name ?? '—'}</Fact>
                  <Fact label={t('fields.category')}>{ticket.category?.name ?? '—'}</Fact>
                  <Fact label={t('fields.requester')}>{ticket.requester.name ?? '—'}</Fact>
                  <Fact label={t('fields.assignee')}>{ticket.assignee?.name ?? '—'}</Fact>
                  <Fact label={t('fields.contactPhone')}>{ticket.contact_phone ?? '—'}</Fact>
                  <Fact label={t('fields.location')}>{ticket.location ?? '—'}</Fact>
                  <Fact label={t('fields.createdAt')}>{formatDateTime(ticket.created_at)}</Fact>
                  <Fact label={t('fields.responseDue')}>{formatDateTime(ticket.response_due_at)}</Fact>
                  <Fact label={t('fields.resolutionDue')}>{formatDateTime(ticket.resolution_due_at)}</Fact>
                  {ticket.time_spent_minutes > 0 && (
                    <Fact label={t('fields.timeSpent')}>
                      {t('sla.minutes', { count: ticket.time_spent_minutes })}
                    </Fact>
                  )}
                  {ticket.rating !== null && (
                    <Fact label={t('fields.rating')}>
                      <Rating value={ticket.rating} readOnly size="sm" />
                    </Fact>
                  )}
                  {ticket.reopened_count > 0 && (
                    <Fact label={t('fields.status')}>
                      {t('detail.reopened', { count: ticket.reopened_count })}
                    </Fact>
                  )}
                </SimpleGrid>
              </Card>
              <AttachmentsPanel ticket={ticket} />
              <LinksPanel ticket={ticket} />
              <DocumentsPanel ticket={ticket} />
              <TicketTasksPanel ticketId={ticket.id} />
              <TicketProblemsPanel ticketId={ticket.id} />
              <TicketKnowledgePanel ticketId={ticket.id} status={ticket.status} />
            </Stack>
          </Grid.Col>
        </Grid>
      </Stack>
    </>
  )
}
