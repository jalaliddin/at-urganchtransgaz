import {
  closestCorners,
  DndContext,
  DragOverlay,
  KeyboardSensor,
  PointerSensor,
  TouchSensor,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { SortableContext, sortableKeyboardCoordinates, verticalListSortingStrategy } from '@dnd-kit/sortable'
import {
  Badge,
  Button,
  Checkbox,
  Drawer,
  Group,
  Paper,
  ScrollArea,
  Select,
  Stack,
  Text,
  TextInput,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconRepeat, IconSearch, IconSettings } from '@tabler/icons-react'
import { useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams, useSearchParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import * as s from './api'
import { BoardSettingsModal } from './BoardSettingsModal'
import { useBoard, useBoardCards, useBoardMembers, useTask, useTaskMutation } from './queries'
import { RecurrencesModal } from './RecurrencesModal'
import { TaskCard } from './TaskCard'
import { TaskDetail } from './TaskDetail'
import { TaskFormModal } from './TaskFormModal'
import type { BoardCard, BoardColumn, Column } from './types'

const cardKey = (id: number) => `card-${id}`
const columnKey = (id: number) => `column-${id}`
const idOf = (key: string | number) => Number(String(key).split('-')[1])

function KanbanColumn({
  column,
  columns,
  onOpen,
  onMove,
}: {
  column: BoardColumn
  columns: Column[]
  onOpen: (id: number) => void
  onMove: (card: BoardCard, columnId: number) => void
}) {
  const { t } = useTranslation('tasks')
  const { setNodeRef, isOver } = useDroppable({ id: columnKey(column.id) })
  const overLimit = column.wip_limit !== null && column.cards.length > column.wip_limit

  return (
    <Paper
      ref={setNodeRef}
      withBorder
      radius="md"
      p="sm"
      w={280}
      miw={280}
      bg={isOver ? 'var(--mantine-color-blue-light)' : 'var(--mantine-color-gray-light)'}
      style={{ alignSelf: 'flex-start' }}
      aria-label={column.name}
    >
      <Group justify="space-between" mb="sm" wrap="nowrap">
        <Text fw={600} size="sm" truncate>
          {column.name}
        </Text>
        <Badge variant="light" color={overLimit ? 'red' : 'gray'}>
          {column.wip_limit !== null ? `${column.cards.length}/${column.wip_limit}` : column.cards.length}
        </Badge>
      </Group>
      <SortableContext items={column.cards.map((c) => cardKey(c.id))} strategy={verticalListSortingStrategy}>
        <Stack gap="xs" mih={40}>
          {column.cards.map((card) => (
            <TaskCard key={card.id} card={card} columns={columns} onOpen={onOpen} onMove={onMove} />
          ))}
        </Stack>
      </SortableContext>
      {column.hidden_done > 0 && (
        <Text size="xs" c="dimmed" mt="xs">
          {t('board.hiddenDone', { count: column.hidden_done })}
        </Text>
      )}
    </Paper>
  )
}

/** Kanban view of one board: drag cards between and within columns; filters live in the URL. */
export function BoardPage() {
  const { t } = useTranslation(['tasks', 'common'])
  const id = Number(useParams().id)
  const [params, setParams] = useSearchParams()
  const board = useBoard(id)
  const members = useBoardMembers(id)
  const canCreate = useCan('tasks.create')
  const canManage = useCan('tasks.manage')
  const [search, setSearch] = useState(params.get('search') ?? '')
  const [debounced] = useDebouncedValue(search, 300)
  const filters: Record<string, string | number> = {}
  for (const key of ['mine', 'overdue', 'assignee', 'label'] as const) {
    const v = params.get(key)
    if (v) filters[`filter[${key}]`] = v
  }
  if (debounced.trim()) filters.search = debounced.trim()
  const cards = useBoardCards(id, filters)
  const queryClient = useQueryClient()
  /** Columns while a card is being dragged; otherwise the server's (optimistically updated) columns. */
  const [dragColumns, setDragColumns] = useState<BoardColumn[] | null>(null)
  const columns = useMemo(() => dragColumns ?? cards.data?.columns ?? [], [dragColumns, cards.data])
  const [active, setActive] = useState<BoardCard | null>(null)
  const [dialog, setDialog] = useState<'new' | 'settings' | 'recurrences' | null>(null)
  const openTaskId = params.get('task') ? Number(params.get('task')) : null
  const openTask = useTask(openTaskId)
  const move = useTaskMutation((args: { id: number; column: number; position: number }) =>
    s.moveTask(args.id, args.column, args.position),
  )
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(TouchSensor, { activationConstraint: { delay: 250, tolerance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const columnMeta = useMemo<Column[]>(
    () =>
      columns.map((c) => ({
        id: c.id,
        name: c.name,
        category: c.category,
        position: c.position,
        wip_limit: c.wip_limit,
      })),
    [columns],
  )

  const setParam = (key: string, value: string | null) =>
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) next.set(key, value)
      else next.delete(key)
      return next
    })

  const findColumn = (key: string | number): BoardColumn | undefined =>
    String(key).startsWith('column-')
      ? columns.find((c) => c.id === idOf(key))
      : columns.find((c) => c.cards.some((card) => card.id === idOf(key)))

  const persist = (cardId: number, columnId: number, position: number) =>
    move.mutate(
      { id: cardId, column: columnId, position },
      {
        onError: (e) => {
          notifications.show({ color: 'red', message: toApiError(e).message })
          void queryClient.invalidateQueries({ queryKey: ['tasks', 'cards', id] })
        },
      },
    )

  const onDragStart = (e: DragStartEvent) => {
    setDragColumns(cards.data?.columns ?? [])
    setActive((e.active.data.current?.card as BoardCard | undefined) ?? null)
  }

  /** Ends the drag showing the final columns at once (cache); the server confirms or the error handler reloads. */
  const finish = (final: BoardColumn[]) => {
    queryClient.setQueryData(['tasks', 'cards', id, filters], { columns: final })
    setDragColumns(null)
  }

  const onDragOver = ({ active: a, over }: DragOverEvent) => {
    if (!over) return
    const from = findColumn(a.id)
    const to = findColumn(over.id)
    if (!from || !to || from.id === to.id) return
    setDragColumns((current) => {
      const prev = current ?? columns
      const card = from.cards.find((c) => c.id === idOf(a.id))
      if (!card) return prev
      const overIndex = String(over.id).startsWith('card-')
        ? to.cards.findIndex((c) => c.id === idOf(over.id))
        : to.cards.length
      return prev.map((col) => {
        if (col.id === from.id) return { ...col, cards: col.cards.filter((c) => c.id !== card.id) }
        if (col.id === to.id) {
          const next = [...col.cards]
          next.splice(overIndex < 0 ? next.length : overIndex, 0, { ...card, column_id: to.id })
          return { ...col, cards: next }
        }
        return col
      })
    })
  }

  const onDragEnd = ({ active: a, over }: DragEndEvent) => {
    const card = active
    setActive(null)
    const column = findColumn(a.id)
    if (!over || !card || !column) {
      setDragColumns(null)
      return
    }
    let ordered = column.cards
    if (String(over.id).startsWith('card-') && over.id !== a.id) {
      const from = ordered.findIndex((c) => c.id === idOf(a.id))
      const to = ordered.findIndex((c) => c.id === idOf(over.id))
      if (from >= 0 && to >= 0) {
        ordered = [...ordered]
        const [moved] = ordered.splice(from, 1)
        if (moved) ordered.splice(to, 0, moved)
      }
    }
    const position = ordered.findIndex((c) => c.id === card.id) + 1
    const original = cards.data?.columns.find((c) => c.cards.some((x) => x.id === card.id))
    const originalPosition = (original?.cards.findIndex((x) => x.id === card.id) ?? -1) + 1
    finish(columns.map((c) => (c.id === column.id ? { ...c, cards: ordered } : c)))
    if (original?.id === column.id && originalPosition === position) return
    persist(card.id, column.id, position)
  }

  if (board.error instanceof ApiError && board.error.status === 404) return <NotFoundPage />
  if (board.error) return <ErrorState error={board.error} onRetry={() => void board.refetch()} />
  const b = board.data
  if (!b) return null
  const writable = !b.is_archived

  return (
    <>
      <PageHeader
        title={b.name}
        meta={b.is_archived ? <Badge color="gray">{t('boards.archived')}</Badge> : undefined}
        description={b.description ?? b.organization.name}
        actions={
          <Group gap="xs">
            {canManage && writable && (
              <Button
                variant="default"
                leftSection={<IconRepeat size={16} />}
                onClick={() => setDialog('recurrences')}
              >
                {t('recurrences.title')}
              </Button>
            )}
            {canManage && (
              <Button
                variant="default"
                leftSection={<IconSettings size={16} />}
                onClick={() => setDialog('settings')}
              >
                {t('board.settings')}
              </Button>
            )}
            {canCreate && writable && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setDialog('new')}>
                {t('form.add')}
              </Button>
            )}
          </Group>
        }
      />
      <Group gap="sm" mb="md" wrap="wrap">
        <TextInput
          leftSection={<IconSearch size={16} />}
          placeholder={t('board.search')}
          aria-label={t('board.search')}
          value={search}
          onChange={(e) => {
            setSearch(e.currentTarget.value)
            setParam('search', e.currentTarget.value || null)
          }}
          w={{ base: '100%', sm: 220 }}
        />
        <Select
          placeholder={t('fields.assignee')}
          aria-label={t('fields.assignee')}
          clearable
          searchable
          data={(members.data ?? []).map((m) => ({ value: String(m.id), label: m.name }))}
          value={params.get('assignee')}
          onChange={(v) => setParam('assignee', v)}
          w={{ base: '100%', sm: 200 }}
        />
        <Select
          placeholder={t('fields.labels')}
          aria-label={t('fields.labels')}
          clearable
          data={b.labels.map((l) => ({ value: String(l.id), label: l.name }))}
          value={params.get('label')}
          onChange={(v) => setParam('label', v)}
          w={{ base: '100%', sm: 170 }}
        />
        <Checkbox
          label={t('board.mine')}
          checked={params.get('mine') === '1'}
          onChange={(e) => setParam('mine', e.currentTarget.checked ? '1' : null)}
        />
        <Checkbox
          label={t('board.overdueOnly')}
          checked={params.get('overdue') === '1'}
          onChange={(e) => setParam('overdue', e.currentTarget.checked ? '1' : null)}
        />
      </Group>
      {cards.error ? (
        <ErrorState error={cards.error} onRetry={() => void cards.refetch()} />
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCorners}
          onDragStart={onDragStart}
          onDragOver={onDragOver}
          onDragEnd={onDragEnd}
          onDragCancel={() => {
            setActive(null)
            setDragColumns(null)
          }}
        >
          <ScrollArea type="auto" offsetScrollbars>
            <Group align="flex-start" gap="md" wrap="nowrap" pb="md">
              {columns.map((column) => (
                <KanbanColumn
                  key={column.id}
                  column={column}
                  columns={columnMeta}
                  onOpen={(taskId) => setParam('task', String(taskId))}
                  onMove={(card, columnId) => persist(card.id, columnId, 9999)}
                />
              ))}
            </Group>
          </ScrollArea>
          <DragOverlay>
            {active && (
              <Paper withBorder shadow="md" p="sm" radius="md" w={260}>
                <Text size="sm" fw={500}>
                  {active.title}
                </Text>
              </Paper>
            )}
          </DragOverlay>
        </DndContext>
      )}
      <Drawer
        opened={openTaskId !== null}
        onClose={() => setParam('task', null)}
        position="right"
        size="lg"
        title={openTask.data?.number}
      >
        {openTask.data && <TaskDetail task={openTask.data} onDeleted={() => setParam('task', null)} />}
      </Drawer>
      {dialog === 'new' && <TaskFormModal boardId={id} onClose={() => setDialog(null)} />}
      {dialog === 'settings' && <BoardSettingsModal board={b} onClose={() => setDialog(null)} />}
      {dialog === 'recurrences' && <RecurrencesModal board={b} onClose={() => setDialog(null)} />}
    </>
  )
}
