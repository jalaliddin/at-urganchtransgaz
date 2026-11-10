import {
  ActionIcon,
  Alert,
  Anchor,
  Button,
  Card,
  Grid,
  Group,
  Modal,
  SegmentedControl,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconAlertTriangle, IconPencil } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { formatDateTime } from '@/lib/format'
import * as c from './api'
import { ChangeRiskBadge, ChangeStatusBadge, ChangeTypeBadge } from './ChangeBadges'
import { ChangeFormModal } from './ChangeFormModal'
import { useChange, useChangeMutation } from './queries'
import type { Change, ChangeAction } from './types'

/** Steps that ask for text: rejection comment, cancel reason, completion result. */
const NEEDS_TEXT: Partial<Record<ChangeAction, 'comment' | 'reason' | 'result'>> = {
  approve: 'comment',
  reject: 'comment',
  cancel: 'reason',
  complete: 'result',
}

function ActionModal({
  change,
  action,
  onClose,
}: {
  change: Change
  action: ChangeAction
  onClose: () => void
}) {
  const { t } = useTranslation(['changes', 'common', 'validation'])
  const field = NEEDS_TEXT[action]
  const [text, setText] = useState('')
  const [success, setSuccess] = useState('1')
  const [error, setError] = useState<string | null>(null)
  const required = action !== 'approve'
  const run = useChangeMutation(() =>
    c.changeAction(change.id, action, {
      ...(field ? { [field]: text.trim() || null } : {}),
      ...(action === 'complete' ? { success: success === '1' } : {}),
    }),
  )

  return (
    <Modal opened onClose={onClose} title={`${t(`actions.${action}`)}: ${change.number}`} centered>
      <Stack>
        {action === 'approve' && change.conflicts.length > 0 && (
          <Alert color="orange" icon={<IconAlertTriangle size={16} />}>
            {t('conflicts.atApproval', { count: change.conflicts.length })}
          </Alert>
        )}
        {action === 'complete' && (
          <SegmentedControl
            aria-label={t('fields.outcome')}
            value={success}
            onChange={setSuccess}
            data={[
              { value: '1', label: t('status.implemented') },
              { value: '0', label: t('status.failed') },
            ]}
          />
        )}
        {field && (
          <Textarea
            label={t(`fields.${field}`)}
            value={text}
            onChange={(e) => setText(e.currentTarget.value)}
            error={error}
            required={required}
            autosize
            minRows={3}
            data-autofocus
          />
        )}
        {!field && <Text size="sm">{t(`confirm.${action}`)}</Text>}
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.close')}
          </Button>
          <Button
            color={action === 'reject' || action === 'cancel' ? 'red' : undefined}
            loading={run.isPending}
            onClick={() => {
              if (field && required && !text.trim()) {
                setError(t('validation:required'))
                return
              }
              run.mutate(undefined, {
                onSuccess: () => {
                  notifications.show({ color: 'green', message: t(`done.${action}`) })
                  onClose()
                },
                onError: (e) => {
                  const apiError = toApiError(e)
                  const first = Object.values(apiError.fieldErrors)[0]?.[0]
                  if (first) setError(first)
                  else notifications.show({ color: 'red', message: apiError.message })
                },
              })
            }}
          >
            {t(`actions.${action}`)}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

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

export function ChangePage() {
  const { t } = useTranslation(['changes', 'common'])
  const id = Number(useParams().id)
  const query = useChange(id)
  const [action, setAction] = useState<ChangeAction | null>(null)
  const [editing, setEditing] = useState(false)
  const [comment, setComment] = useState('')
  const addComment = useChangeMutation(() => c.commentChange(id, comment.trim()))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const change = query.data
  if (!change) return null
  const can = change.can
  const actions: ChangeAction[] = [
    ...(can.submit ? (['submit'] as const) : []),
    ...(can.decide ? (['approve', 'reject'] as const) : []),
    ...(can.start ? (['start'] as const) : []),
    ...(can.complete ? (['complete'] as const) : []),
    ...(can.revise ? (['revise'] as const) : []),
    ...(can.cancel ? (['cancel'] as const) : []),
  ]

  return (
    <>
      <PageHeader
        title={change.title}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {change.number}
            </Text>
            <ChangeStatusBadge status={change.status} />
            <ChangeTypeBadge type={change.type} />
            <ChangeRiskBadge risk={change.risk} />
          </Group>
        }
        description={`${change.organization.name}, ${t(`category.${change.category}`)}`}
        actions={
          <Group gap="xs">
            {actions.map((a) => (
              <Button
                key={a}
                size="xs"
                variant={a === 'cancel' || a === 'reject' ? 'light' : a === actions[0] ? 'filled' : 'default'}
                color={a === 'cancel' || a === 'reject' ? 'red' : undefined}
                onClick={() => setAction(a)}
              >
                {t(`actions.${a}`)}
              </Button>
            ))}
            {can.edit && (
              <ActionIcon
                variant="subtle"
                aria-label={t('common:actions.edit')}
                onClick={() => setEditing(true)}
              >
                <IconPencil size={16} />
              </ActionIcon>
            )}
          </Group>
        }
      />
      {change.conflicts.length > 0 && (
        <Alert color="orange" icon={<IconAlertTriangle size={18} />} title={t('conflicts.title')} mb="md">
          <Stack gap={2}>
            {change.conflicts.map((o) => (
              <Anchor key={o.id} component={Link} to={`/changes/${o.id}`} size="sm">
                {o.number}: {o.title} ({formatDateTime(o.planned_start)})
              </Anchor>
            ))}
          </Stack>
        </Alert>
      )}
      <Grid>
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Stack>
            <Card withBorder>
              <SimpleGrid cols={{ base: 1, sm: 2 }} mb="md">
                <Block
                  label={t('fields.plannedStart')}
                  text={change.planned_start ? formatDateTime(change.planned_start) : null}
                />
                <Block
                  label={t('fields.plannedEnd')}
                  text={change.planned_end ? formatDateTime(change.planned_end) : null}
                />
                {change.actual_start && (
                  <Block label={t('fields.actualStart')} text={formatDateTime(change.actual_start)} />
                )}
                {change.actual_end && (
                  <Block label={t('fields.actualEnd')} text={formatDateTime(change.actual_end)} />
                )}
              </SimpleGrid>
              <Stack>
                <Block label={t('fields.description')} text={change.description} />
                <Block label={t('fields.impact')} text={change.impact} />
                <Block label={t('fields.plan')} text={change.plan} />
                <Block label={t('fields.backout_plan')} text={change.backout_plan} />
                {change.result && <Block label={t('fields.result')} text={change.result} />}
                {change.decision_comment && (
                  <Block label={t('fields.decision')} text={change.decision_comment} />
                )}
              </Stack>
            </Card>
            <Card withBorder>
              <Title order={4} mb="sm">
                {t('fields.assets')}
              </Title>
              {change.assets.length === 0 ? (
                <Text c="dimmed">{t('assetsEmpty')}</Text>
              ) : (
                <Stack gap={4}>
                  {change.assets.map((a) => (
                    <Anchor key={a.id} component={Link} to={`/assets/${a.id}`} size="sm">
                      {a.inventory_number}: {a.name}
                      {a.model ? `, ${a.model}` : ''}
                    </Anchor>
                  ))}
                </Stack>
              )}
            </Card>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Stack>
            <Card withBorder>
              <Stack gap="xs">
                <Block label={t('fields.implementer')} text={change.implementer?.name ?? null} />
                <Block label={t('fields.creator')} text={change.creator?.name ?? null} />
                {change.decided_by && (
                  <Block
                    label={t('fields.decidedBy')}
                    text={`${change.decided_by.name}, ${formatDateTime(change.decided_at)}`}
                  />
                )}
                {change.problem && (
                  <Stack gap={2}>
                    <Text size="xs" c="dimmed">
                      {t('fields.problem')}
                    </Text>
                    <Anchor component={Link} to={`/problems/${change.problem.id}`} size="sm">
                      {change.problem.number}: {change.problem.title}
                    </Anchor>
                  </Stack>
                )}
              </Stack>
            </Card>
            <Card withBorder>
              <Title order={5} mb="sm">
                {t('history.title')}
              </Title>
              {can.comment && (
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
                    onClick={() => addComment.mutate(undefined, { onSuccess: () => setComment('') })}
                  >
                    {t('history.add')}
                  </Button>
                </Stack>
              )}
              <Timeline bulletSize={12} lineWidth={2}>
                {change.history.map((h) => (
                  <Timeline.Item
                    key={h.id}
                    title={
                      <Text size="sm" fw={500}>
                        {t(`event.${h.event}`)}
                      </Text>
                    }
                  >
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
      {action && <ActionModal change={change} action={action} onClose={() => setAction(null)} />}
      {editing && <ChangeFormModal change={change} onClose={() => setEditing(false)} />}
    </>
  )
}
