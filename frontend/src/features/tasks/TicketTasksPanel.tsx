import { Anchor, Button, Card, Group, Modal, Select, Stack, Text, Title } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { useCan } from '@/hooks/usePermissions'
import * as s from './api'
import { useBoardMembers, useBoards, useTaskMutation, useTicketTasks } from './queries'
import { OverdueBadge } from './TaskBadges'

function CreateFromTicket({ ticketId, onClose }: { ticketId: number; onClose: () => void }) {
  const { t } = useTranslation(['tasks', 'common', 'validation'])
  const navigate = useNavigate()
  const boards = useBoards()
  const [boardId, setBoardId] = useState<string | null>(null)
  const members = useBoardMembers(Number(boardId ?? 0), boardId !== null)
  const [assignee, setAssignee] = useState<string | null>(null)
  const [due, setDue] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const create = useTaskMutation(() =>
    s.taskFromTicket(ticketId, {
      board_id: Number(boardId),
      assignee_id: assignee ? Number(assignee) : null,
      due_date: due,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('ticket.create')} centered>
      <Stack>
        <Select
          label={t('ticket.board')}
          required
          data={(boards.data ?? []).map((b) => ({ value: String(b.id), label: b.name }))}
          value={boardId}
          onChange={(v) => {
            setBoardId(v)
            setAssignee(null)
          }}
          error={error}
          data-autofocus
        />
        <Select
          label={t('fields.assignee')}
          clearable
          searchable
          disabled={!boardId}
          data={(members.data ?? [])
            .filter((m) => m.can_be_assignee)
            .map((m) => ({ value: String(m.id), label: m.name }))}
          value={assignee}
          onChange={setAssignee}
        />
        <DateInput
          label={t('fields.dueDate')}
          value={due}
          onChange={setDue}
          valueFormat="DD.MM.YYYY"
          clearable
        />
        <Group justify="flex-end">
          <Button
            loading={create.isPending}
            onClick={() => {
              if (!boardId) {
                setError(t('validation:required'))
                return
              }
              create.mutate(undefined, {
                onSuccess: (task) => {
                  onClose()
                  navigate(`/tasks/${task.id}`)
                },
                onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
              })
            }}
          >
            {t('ticket.create')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** Tasks made from a ticket, on the ticket card. */
export function TicketTasksPanel({ ticketId }: { ticketId: number }) {
  const { t } = useTranslation('tasks')
  const canView = useCan('tasks.view')
  const canCreate = useCan('tasks.create')
  const tasks = useTicketTasks(ticketId, canView)
  const [creating, setCreating] = useState(false)
  if (!canView) return null
  return (
    <Card withBorder radius="md" p="md">
      <Group justify="space-between" mb="sm">
        <Title order={4} fz="sm">
          {t('ticket.title')}
        </Title>
        {canCreate && (
          <Button
            size="compact-sm"
            variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={() => setCreating(true)}
          >
            {t('ticket.create')}
          </Button>
        )}
      </Group>
      {tasks.data?.length === 0 && (
        <Text size="sm" c="dimmed">
          {t('ticket.empty')}
        </Text>
      )}
      <Stack gap={6}>
        {tasks.data?.map((task) => (
          <Group key={task.id} gap="xs" wrap="nowrap" justify="space-between">
            <Anchor component={Link} to={`/tasks/${task.id}`} size="sm">
              {task.number}: {task.title}
            </Anchor>
            {task.is_overdue && <OverdueBadge />}
          </Group>
        ))}
      </Stack>
      {creating && <CreateFromTicket ticketId={ticketId} onClose={() => setCreating(false)} />}
    </Card>
  )
}
