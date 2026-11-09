import {
  ActionIcon,
  Anchor,
  Badge,
  Button,
  Checkbox,
  FileButton,
  Group,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  TextInput,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPaperclip, IconPencil, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { fileUrl } from '@/features/exports/api'
import { formatDate, formatDateTime } from '@/lib/format'
import * as s from './api'
import { useBoard, useTaskMutation } from './queries'
import { LabelChips, OverdueBadge, PriorityBadge } from './TaskBadges'
import { TaskFormModal } from './TaskFormModal'
import type { TaskDetail as Task } from './types'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

function Fact({ label, children }: { label: string; children: React.ReactNode }) {
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

/** Everything about one task: facts, column, checklist, comments, files and history (drawer and page). */
export function TaskDetail({ task, onDeleted }: { task: Task; onDeleted?: () => void }) {
  const { t } = useTranslation(['tasks', 'common'])
  const board = useBoard(task.board.id)
  const [editing, setEditing] = useState(false)
  const [item, setItem] = useState('')
  const [comment, setComment] = useState('')
  const move = useTaskMutation((columnId: number) => s.moveTask(task.id, columnId, 1))
  const addItem = useTaskMutation(() => s.addChecklistItem(task.id, item.trim()))
  const toggle = useTaskMutation((args: { id: number; done: boolean }) =>
    s.updateChecklistItem(args.id, { is_done: args.done }),
  )
  const removeItem = useTaskMutation((id: number) => s.deleteChecklistItem(id))
  const send = useTaskMutation(() => s.addComment(task.id, comment.trim()))
  const upload = useTaskMutation((files: File[]) => s.addAttachments(task.id, files))
  const remove = useTaskMutation(() => s.deleteTask(task.id))

  return (
    <Stack>
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <Stack gap={4}>
          <Text size="xs" c="dimmed" ff="monospace">
            {task.number}
          </Text>
          <Title order={3}>{task.title}</Title>
          <Group gap={6}>
            <Badge
              variant="light"
              color={
                task.column.category === 'open' ? 'blue' : task.column.category === 'done' ? 'green' : 'gray'
              }
            >
              {task.column.name}
            </Badge>
            <PriorityBadge priority={task.priority} />
            {task.is_overdue && <OverdueBadge />}
            <LabelChips labels={task.labels} />
          </Group>
        </Stack>
        <Group gap={4} wrap="nowrap">
          {task.can.edit && (
            <ActionIcon
              variant="subtle"
              aria-label={t('common:actions.edit')}
              onClick={() => setEditing(true)}
            >
              <IconPencil size={16} />
            </ActionIcon>
          )}
          {task.can.delete && (
            <ActionIcon
              variant="subtle"
              color="red"
              aria-label={t('common:actions.delete')}
              onClick={async () => {
                if (
                  await confirmAction({
                    message: t('detail.deleteConfirm'),
                    confirmLabel: t('common:actions.delete'),
                    danger: true,
                  })
                )
                  remove.mutate(undefined, { onSuccess: () => onDeleted?.(), onError: fail })
              }}
            >
              <IconTrash size={16} />
            </ActionIcon>
          )}
        </Group>
      </Group>

      {task.can.edit && (
        <Select
          label={t('detail.moveTo')}
          data={(board.data?.columns ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
          value={String(task.column.id)}
          allowDeselect={false}
          onChange={(v) => v && Number(v) !== task.column.id && move.mutate(Number(v), { onError: fail })}
          maw={280}
        />
      )}

      <SimpleGrid cols={{ base: 1, xs: 2 }}>
        <Fact label={t('fields.assignee')}>{task.assignee?.name ?? '—'}</Fact>
        <Fact label={t('fields.organization')}>{task.organization.name}</Fact>
        <Fact label={t('fields.startDate')}>{task.start_date ? formatDate(task.start_date) : '—'}</Fact>
        <Fact label={t('fields.dueDate')}>{task.due_date ? formatDate(task.due_date) : '—'}</Fact>
        <Fact label={t('fields.project')}>{task.project ?? '—'}</Fact>
        <Fact label={t('fields.watchers')}>{task.watchers.map((w) => w.name).join(', ') || '—'}</Fact>
        <Fact label={t('detail.board')}>
          <Anchor component={Link} to={`/tasks/boards/${task.board.id}`} size="sm">
            {task.board.name}
          </Anchor>
        </Fact>
        {task.ticket && (
          <Fact label={t('detail.ticket')}>
            <Anchor component={Link} to={`/helpdesk/${task.ticket.id}`} size="sm">
              {task.ticket.number}
            </Anchor>
          </Fact>
        )}
      </SimpleGrid>
      {task.description && (
        <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
          {task.description}
        </Text>
      )}

      <Paper withBorder p="md">
        <Title order={5} mb="xs">
          {t('detail.checklist')}
          {task.checklist.length > 0 &&
            ` (${task.checklist.filter((i) => i.is_done).length}/${task.checklist.length})`}
        </Title>
        <Stack gap={6}>
          {task.checklist.map((i) => (
            <Group key={i.id} justify="space-between" wrap="nowrap">
              <Checkbox
                label={i.title}
                checked={i.is_done}
                disabled={!task.can.edit}
                onChange={(e) =>
                  toggle.mutate({ id: i.id, done: e.currentTarget.checked }, { onError: fail })
                }
              />
              {task.can.edit && (
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  color="red"
                  aria-label={`${t('common:actions.delete')}: ${i.title}`}
                  onClick={() => removeItem.mutate(i.id, { onError: fail })}
                >
                  <IconTrash size={14} />
                </ActionIcon>
              )}
            </Group>
          ))}
          {task.can.edit && (
            <Group gap="xs" wrap="nowrap">
              <TextInput
                aria-label={t('detail.newItem')}
                placeholder={t('detail.newItem')}
                value={item}
                onChange={(e) => setItem(e.currentTarget.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && item.trim())
                    addItem.mutate(undefined, { onSuccess: () => setItem(''), onError: fail })
                }}
                style={{ flex: 1 }}
              />
              <Button
                variant="default"
                disabled={!item.trim()}
                loading={addItem.isPending}
                onClick={() => addItem.mutate(undefined, { onSuccess: () => setItem(''), onError: fail })}
              >
                {t('common:actions.add')}
              </Button>
            </Group>
          )}
        </Stack>
      </Paper>

      <Paper withBorder p="md">
        <Group justify="space-between" mb="xs">
          <Title order={5}>{t('detail.files')}</Title>
          {task.can.comment && (
            <FileButton
              multiple
              onChange={(files) => files.length > 0 && upload.mutate(files, { onError: fail })}
            >
              {(props) => (
                <Button
                  {...props}
                  size="xs"
                  variant="default"
                  leftSection={<IconPaperclip size={14} />}
                  loading={upload.isPending}
                >
                  {t('detail.attach')}
                </Button>
              )}
            </FileButton>
          )}
        </Group>
        {task.attachments.length === 0 ? (
          <Text size="sm" c="dimmed">
            {t('detail.noFiles')}
          </Text>
        ) : (
          <Stack gap={4}>
            {task.attachments.map((a) => (
              <Anchor key={a.id} href={fileUrl(a.file.uuid)} size="sm">
                {a.file.name}
              </Anchor>
            ))}
          </Stack>
        )}
      </Paper>

      <Paper withBorder p="md">
        <Title order={5} mb="xs">
          {t('detail.comments')}
        </Title>
        <Stack gap="sm">
          {task.comments.map((c) => (
            <Stack key={c.id} gap={2}>
              <Text size="xs" c="dimmed">
                {c.user?.name ?? '—'}, {formatDateTime(c.created_at)}
              </Text>
              <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                {c.body}
              </Text>
            </Stack>
          ))}
          {task.can.comment && (
            <>
              <Textarea
                aria-label={t('detail.comment')}
                placeholder={t('detail.comment')}
                autosize
                minRows={2}
                value={comment}
                onChange={(e) => setComment(e.currentTarget.value)}
              />
              <Group justify="flex-end">
                <Button
                  size="xs"
                  disabled={!comment.trim()}
                  loading={send.isPending}
                  onClick={() => send.mutate(undefined, { onSuccess: () => setComment(''), onError: fail })}
                >
                  {t('detail.send')}
                </Button>
              </Group>
            </>
          )}
        </Stack>
      </Paper>

      <Paper withBorder p="md">
        <Title order={5} mb="xs">
          {t('detail.history')}
        </Title>
        <Timeline bulletSize={10} lineWidth={2}>
          {task.history.map((h) => (
            <Timeline.Item key={h.id} title={t(`events.${h.event}`)}>
              {h.event === 'moved' && (
                <Text size="sm">
                  {String(h.payload.from ?? '')} → {String(h.payload.to ?? '')}
                </Text>
              )}
              {h.event === 'assigned' && <Text size="sm">{String(h.payload.assignee ?? '—')}</Text>}
              <Text size="xs" c="dimmed">
                {formatDateTime(h.created_at)}
                {h.user ? `, ${h.user.name}` : ''}
              </Text>
            </Timeline.Item>
          ))}
        </Timeline>
      </Paper>
      {editing && <TaskFormModal boardId={task.board.id} task={task} onClose={() => setEditing(false)} />}
    </Stack>
  )
}
