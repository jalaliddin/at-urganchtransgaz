import { ActionIcon, Avatar, Group, Menu, Paper, Stack, Text, Tooltip } from '@mantine/core'
import { useSortable } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { IconArrowsMove, IconChecklist, IconMessage } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { formatDate } from '@/lib/format'
import { LabelChips, PriorityBadge } from './TaskBadges'
import type { BoardCard, Column } from './types'

const initials = (name: string) =>
  name
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0] ?? '')
    .join('')
    .toUpperCase()

/** A draggable card; the move menu is the keyboard / small-screen alternative to dragging. */
export function TaskCard({
  card,
  columns,
  onOpen,
  onMove,
}: {
  card: BoardCard
  columns: Column[]
  onOpen: (id: number) => void
  onMove: (card: BoardCard, columnId: number) => void
}) {
  const { t } = useTranslation('tasks')
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: `card-${card.id}`,
    data: { card },
    disabled: !card.can_move,
  })

  return (
    <Paper
      ref={setNodeRef}
      withBorder
      p="sm"
      radius="md"
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
        cursor: card.can_move ? 'grab' : 'pointer',
        borderLeft: card.is_overdue ? '3px solid var(--mantine-color-red-6)' : undefined,
        touchAction: 'manipulation',
      }}
      {...attributes}
      {...listeners}
      onClick={() => onOpen(card.id)}
      aria-label={`${card.number}: ${card.title}`}
    >
      <Stack gap={6}>
        <Group justify="space-between" wrap="nowrap" gap={4}>
          <Text size="xs" c="dimmed" ff="monospace">
            {card.number}
          </Text>
          {card.can_move && (
            <Menu withinPortal position="bottom-end">
              <Menu.Target>
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  aria-label={`${t('board.moveTo')}: ${card.title}`}
                  onClick={(e) => e.stopPropagation()}
                  onPointerDown={(e) => e.stopPropagation()}
                  onKeyDown={(e) => e.stopPropagation()}
                >
                  <IconArrowsMove size={14} />
                </ActionIcon>
              </Menu.Target>
              <Menu.Dropdown onClick={(e) => e.stopPropagation()}>
                <Menu.Label>{t('board.moveTo')}</Menu.Label>
                {columns
                  .filter((c) => c.id !== card.column_id)
                  .map((c) => (
                    <Menu.Item key={c.id} onClick={() => onMove(card, c.id)}>
                      {c.name}
                    </Menu.Item>
                  ))}
              </Menu.Dropdown>
            </Menu>
          )}
        </Group>
        <Text size="sm" fw={500} lineClamp={3}>
          {card.title}
        </Text>
        <LabelChips labels={card.labels} />
        <Group justify="space-between" gap={4} wrap="nowrap">
          <Group gap={8} wrap="nowrap">
            <PriorityBadge priority={card.priority} />
            {card.due_date && (
              <Text size="xs" c={card.is_overdue ? 'red.7' : 'dimmed'} fw={card.is_overdue ? 600 : undefined}>
                {formatDate(card.due_date)}
              </Text>
            )}
            {(card.checklist_total ?? 0) > 0 && (
              <Group gap={2} wrap="nowrap">
                <IconChecklist size={12} />
                <Text size="xs" c="dimmed">
                  {card.checklist_done}/{card.checklist_total}
                </Text>
              </Group>
            )}
            {(card.comments_count ?? 0) > 0 && (
              <Group gap={2} wrap="nowrap">
                <IconMessage size={12} />
                <Text size="xs" c="dimmed">
                  {card.comments_count}
                </Text>
              </Group>
            )}
          </Group>
          {card.assignee && (
            <Tooltip label={card.assignee.name}>
              <Avatar size={22} radius="xl" color="blue">
                {initials(card.assignee.name)}
              </Avatar>
            </Tooltip>
          )}
        </Group>
      </Stack>
    </Paper>
  )
}
