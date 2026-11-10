import {
  ActionIcon,
  Anchor,
  Button,
  Card,
  Grid,
  Group,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconLink, IconPencil, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { ChangeFormModal } from '@/features/changes/ChangeFormModal'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { TicketStatusBadge } from '@/features/helpdesk/TicketBadges'
import type { TicketStatus } from '@/features/helpdesk/types'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime } from '@/lib/format'
import * as p from './api'
import { ProblemPriorityBadge, ProblemStatusBadge } from './ProblemBadges'
import { LinkTicketsModal, TransitionModal } from './ProblemDialogs'
import { ProblemFormModal } from './ProblemFormModal'
import { useProblem, useProblemMutation } from './queries'
import type { HistoryEntry, ProblemStatus } from './types'

function Block({ label, text }: { label: string; text: string | null }) {
  return (
    <Stack gap={2}>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
        {text || '—'}
      </Text>
    </Stack>
  )
}

function historyLine(h: HistoryEntry, t: (k: string, o?: Record<string, unknown>) => string): string {
  const p = h.payload ?? {}
  switch (h.event) {
    case 'status_changed':
      return `${t(`status.${String(p.from)}`)} → ${t(`status.${String(p.to)}`)}`
    case 'assigned':
      return String(p.assignee ?? '—')
    case 'tickets_linked':
      return ((p.numbers as string[] | undefined) ?? []).join(', ')
    case 'ticket_unlinked':
      return String(p.number ?? '')
    case 'updated':
      return ((p.fields as string[] | undefined) ?? [])
        .map((f) => t(`fields.${f === 'kb_article_id' ? 'article' : f}`))
        .join(', ')
    default:
      return ''
  }
}

export function ProblemPage() {
  const { t } = useTranslation(['problems', 'common'])
  const id = Number(useParams().id)
  const query = useProblem(id)
  const [dialog, setDialog] = useState<'edit' | 'link' | 'change' | ProblemStatus | null>(null)
  const canChange = useCan('changes.create')
  const [comment, setComment] = useState('')
  const unlink = useProblemMutation((ticketId: number) => p.unlinkTicket(id, ticketId))
  const addComment = useProblemMutation(() => p.commentProblem(id, comment.trim()))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const problem = query.data
  if (!problem) return null
  const manage = problem.can.manage && problem.status !== 'closed'

  return (
    <>
      <PageHeader
        title={problem.title}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {problem.number}
            </Text>
            <ProblemStatusBadge status={problem.status} />
            <ProblemPriorityBadge priority={problem.priority} />
          </Group>
        }
        description={[problem.organization.name, problem.category?.name].filter(Boolean).join(', ')}
        actions={
          <Group gap="xs">
            {problem.can.transitions.map((s) => (
              <Button
                key={s}
                size="xs"
                variant={s === 'closed' ? 'subtle' : 'default'}
                onClick={() => setDialog(s)}
              >
                {t(`transition.${s}`)}
              </Button>
            ))}
            {canChange && problem.status !== 'closed' && (
              <Button size="xs" variant="light" onClick={() => setDialog('change')}>
                {t('createChange')}
              </Button>
            )}
            {manage && (
              <ActionIcon
                variant="subtle"
                aria-label={t('common:actions.edit')}
                onClick={() => setDialog('edit')}
              >
                <IconPencil size={16} />
              </ActionIcon>
            )}
          </Group>
        }
      />
      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Stack>
            <Card withBorder>
              <Stack>
                <Block label={t('fields.description')} text={problem.description} />
                <SimpleGrid cols={{ base: 1, sm: 3 }}>
                  <Block label={t('fields.workaround')} text={problem.workaround} />
                  <Block label={t('fields.root_cause')} text={problem.root_cause} />
                  <Block label={t('fields.resolution')} text={problem.resolution} />
                </SimpleGrid>
                {problem.close_reason && (
                  <Block label={t('fields.closeReason')} text={problem.close_reason} />
                )}
                {problem.article && (
                  <Text size="sm">
                    {t('fields.article')}:{' '}
                    <Anchor component={Link} to={`/kb/${problem.article.id}`} size="sm">
                      {problem.article.number} {problem.article.title}
                    </Anchor>
                  </Text>
                )}
              </Stack>
            </Card>
            <Card withBorder>
              <Group justify="space-between" mb="sm">
                <Title order={4}>{t('tickets.title', { count: problem.tickets.length })}</Title>
                {manage && (
                  <Button
                    size="xs"
                    variant="light"
                    leftSection={<IconLink size={14} />}
                    onClick={() => setDialog('link')}
                  >
                    {t('tickets.link')}
                  </Button>
                )}
              </Group>
              {problem.tickets.length === 0 ? (
                <Text c="dimmed">{t('tickets.empty')}</Text>
              ) : (
                <Stack gap="xs">
                  {problem.tickets.map((tk) => (
                    <Group key={tk.id} justify="space-between" wrap="nowrap" gap="xs">
                      <Stack gap={0} style={{ minWidth: 0 }}>
                        <Anchor component={Link} to={`/helpdesk/${tk.id}`} size="sm" truncate>
                          {tk.number}: {tk.title}
                        </Anchor>
                        <Text size="xs" c="dimmed">
                          {[tk.organization?.name, formatDate(tk.created_at)].filter(Boolean).join(', ')}
                        </Text>
                      </Stack>
                      <Group gap={4} wrap="nowrap">
                        <TicketStatusBadge status={tk.status as TicketStatus} />
                        {manage && (
                          <ActionIcon
                            variant="subtle"
                            color="red"
                            size="sm"
                            aria-label={t('tickets.unlink')}
                            onClick={() => unlink.mutate(tk.id)}
                          >
                            <IconX size={14} />
                          </ActionIcon>
                        )}
                      </Group>
                    </Group>
                  ))}
                </Stack>
              )}
            </Card>
            {problem.changes.length > 0 && (
              <Card withBorder>
                <Title order={4} mb="sm">
                  {t('changes')}
                </Title>
                <Stack gap={4}>
                  {problem.changes.map((c) => (
                    <Anchor key={c.id} component={Link} to={`/changes/${c.id}`} size="sm">
                      {c.number}: {c.title}
                    </Anchor>
                  ))}
                </Stack>
              </Card>
            )}
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Stack>
            <Card withBorder>
              <Stack gap="xs">
                <Block label={t('fields.assignee')} text={problem.assignee?.name ?? null} />
                <Block label={t('fields.creator')} text={problem.creator?.name ?? null} />
                <Block label={t('fields.createdAt')} text={formatDateTime(problem.created_at)} />
                {problem.resolved_at && (
                  <Block label={t('fields.resolvedAt')} text={formatDateTime(problem.resolved_at)} />
                )}
              </Stack>
            </Card>
            <Card withBorder>
              <Title order={5} mb="sm">
                {t('history.title')}
              </Title>
              {problem.can.manage && (
                <Stack gap="xs" mb="md">
                  <Textarea
                    aria-label={t('history.comment')}
                    placeholder={t('history.comment')}
                    value={comment}
                    onChange={(e) => setComment(e.currentTarget.value)}
                    autosize
                    minRows={2}
                  />
                  <Button
                    size="xs"
                    disabled={!comment.trim()}
                    loading={addComment.isPending}
                    onClick={() =>
                      addComment.mutate(undefined, {
                        onSuccess: () => setComment(''),
                        onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                      })
                    }
                  >
                    {t('history.add')}
                  </Button>
                </Stack>
              )}
              <Timeline bulletSize={12} lineWidth={2}>
                {problem.history.map((h) => (
                  <Timeline.Item
                    key={h.id}
                    title={
                      <Text size="sm" fw={500}>
                        {t(`event.${h.event}`)}
                      </Text>
                    }
                  >
                    {historyLine(h, t) && <Text size="xs">{historyLine(h, t)}</Text>}
                    {h.comment && (
                      <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                        {h.comment}
                      </Text>
                    )}
                    <Text size="xs" c="dimmed">
                      {[h.user?.name, formatDateTime(h.created_at)].filter(Boolean).join(', ')}
                    </Text>
                  </Timeline.Item>
                ))}
              </Timeline>
            </Card>
          </Stack>
        </Grid.Col>
      </Grid>
      {dialog === 'edit' && <ProblemFormModal problem={problem} onClose={() => setDialog(null)} />}
      {dialog === 'link' && <LinkTicketsModal problem={problem} onClose={() => setDialog(null)} />}
      {dialog === 'change' && (
        <ChangeFormModal
          problem={{ id: problem.id, organizationId: problem.organization.id, title: problem.title }}
          onClose={() => setDialog(null)}
        />
      )}
      {dialog && dialog !== 'edit' && dialog !== 'link' && dialog !== 'change' && (
        <TransitionModal problem={problem} target={dialog} onClose={() => setDialog(null)} />
      )}
    </>
  )
}
