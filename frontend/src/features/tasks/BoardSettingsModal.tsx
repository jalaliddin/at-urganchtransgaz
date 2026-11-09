import {
  ActionIcon,
  Button,
  Checkbox,
  Group,
  Modal,
  NumberInput,
  Select,
  Stack,
  Tabs,
  Text,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconArrowDown, IconArrowUp, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import * as s from './api'
import { useTaskMutation } from './queries'
import { LABEL_COLORS, type BoardDetail, type ColumnCategory } from './types'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })
const CATEGORIES: ColumnCategory[] = ['open', 'done', 'cancelled']

/** Columns (name, kind, WIP limit, order), labels, archive and delete of a board. */
export function BoardSettingsModal({ board, onClose }: { board: BoardDetail; onClose: () => void }) {
  const { t } = useTranslation(['tasks', 'common'])
  const navigate = useNavigate()
  const [newColumn, setNewColumn] = useState('')
  const [newLabel, setNewLabel] = useState('')
  const [color, setColor] = useState<string | null>('blue')
  const updateColumn = useTaskMutation((args: { id: number; values: Record<string, unknown> }) =>
    s.updateColumn(args.id, args.values),
  )
  const addColumn = useTaskMutation(() => s.addColumn(board.id, { name: newColumn.trim(), category: 'open' }))
  const removeColumn = useTaskMutation((id: number) => s.deleteColumn(id))
  const order = useTaskMutation((ids: number[]) => s.orderColumns(board.id, ids))
  const saveLabel = useTaskMutation(() => s.saveLabel(board.id, { name: newLabel.trim(), color }))
  const removeLabel = useTaskMutation((id: number) => s.deleteLabel(id))
  const archive = useTaskMutation((value: boolean) => s.saveBoard({ is_archived: value }, board.id))
  const remove = useTaskMutation(() => s.deleteBoard(board.id))
  const ids = board.columns.map((c) => c.id)
  const shift = (index: number, by: number) => {
    const next = [...ids]
    const [moved] = next.splice(index, 1)
    if (moved !== undefined) next.splice(index + by, 0, moved)
    order.mutate(next, { onError: fail })
  }

  return (
    <Modal opened onClose={onClose} title={t('board.settings')} centered size="lg">
      <Tabs defaultValue="columns">
        <Tabs.List mb="md">
          <Tabs.Tab value="columns">{t('settings.columns')}</Tabs.Tab>
          <Tabs.Tab value="labels">{t('settings.labels')}</Tabs.Tab>
          <Tabs.Tab value="board">{t('settings.board')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="columns">
          <Stack gap="xs">
            {board.columns.map((c, i) => (
              <Group key={c.id} gap="xs" wrap="nowrap" align="flex-end">
                <TextInput
                  aria-label={t('settings.columnName')}
                  defaultValue={c.name}
                  disabled={board.is_archived}
                  onBlur={(e) =>
                    e.currentTarget.value.trim() &&
                    e.currentTarget.value !== c.name &&
                    updateColumn.mutate(
                      { id: c.id, values: { name: e.currentTarget.value.trim() } },
                      { onError: fail },
                    )
                  }
                  style={{ flex: 1 }}
                />
                <Select
                  aria-label={t('settings.category')}
                  data={CATEGORIES.map((v) => ({ value: v, label: t(`category.${v}`) }))}
                  value={c.category}
                  allowDeselect={false}
                  disabled={board.is_archived}
                  onChange={(v) =>
                    v &&
                    v !== c.category &&
                    updateColumn.mutate({ id: c.id, values: { category: v } }, { onError: fail })
                  }
                  w={130}
                />
                <NumberInput
                  aria-label={t('settings.wip')}
                  placeholder={t('settings.wip')}
                  min={1}
                  max={999}
                  defaultValue={c.wip_limit ?? ''}
                  disabled={board.is_archived}
                  onBlur={(e) => {
                    const v = e.currentTarget.value === '' ? null : Number(e.currentTarget.value)
                    if (v !== c.wip_limit)
                      updateColumn.mutate({ id: c.id, values: { wip_limit: v } }, { onError: fail })
                  }}
                  w={80}
                />
                <ActionIcon
                  variant="subtle"
                  aria-label={`${t('settings.up')}: ${c.name}`}
                  disabled={i === 0 || board.is_archived}
                  onClick={() => shift(i, -1)}
                >
                  <IconArrowUp size={16} />
                </ActionIcon>
                <ActionIcon
                  variant="subtle"
                  aria-label={`${t('settings.down')}: ${c.name}`}
                  disabled={i === ids.length - 1 || board.is_archived}
                  onClick={() => shift(i, 1)}
                >
                  <IconArrowDown size={16} />
                </ActionIcon>
                <ActionIcon
                  variant="subtle"
                  color="red"
                  aria-label={`${t('common:actions.delete')}: ${c.name}`}
                  disabled={board.is_archived}
                  onClick={() => removeColumn.mutate(c.id, { onError: fail })}
                >
                  <IconTrash size={16} />
                </ActionIcon>
              </Group>
            ))}
            {!board.is_archived && (
              <Group gap="xs" wrap="nowrap">
                <TextInput
                  aria-label={t('settings.newColumn')}
                  placeholder={t('settings.newColumn')}
                  value={newColumn}
                  onChange={(e) => setNewColumn(e.currentTarget.value)}
                  style={{ flex: 1 }}
                />
                <Button
                  variant="default"
                  disabled={!newColumn.trim()}
                  loading={addColumn.isPending}
                  onClick={() =>
                    addColumn.mutate(undefined, { onSuccess: () => setNewColumn(''), onError: fail })
                  }
                >
                  {t('common:actions.add')}
                </Button>
              </Group>
            )}
            <Text size="xs" c="dimmed">
              {t('settings.columnsHint')}
            </Text>
          </Stack>
        </Tabs.Panel>
        <Tabs.Panel value="labels">
          <Stack gap="xs">
            {board.labels.length === 0 && (
              <Text c="dimmed" size="sm">
                {t('settings.noLabels')}
              </Text>
            )}
            {board.labels.map((l) => (
              <Group key={l.id} justify="space-between">
                <Text size="sm" c={l.color}>
                  ● {l.name}
                </Text>
                <ActionIcon
                  variant="subtle"
                  color="red"
                  aria-label={`${t('common:actions.delete')}: ${l.name}`}
                  disabled={board.is_archived}
                  onClick={() => removeLabel.mutate(l.id, { onError: fail })}
                >
                  <IconTrash size={16} />
                </ActionIcon>
              </Group>
            ))}
            {!board.is_archived && (
              <Group gap="xs" wrap="nowrap">
                <TextInput
                  aria-label={t('settings.newLabel')}
                  placeholder={t('settings.newLabel')}
                  value={newLabel}
                  onChange={(e) => setNewLabel(e.currentTarget.value)}
                  style={{ flex: 1 }}
                />
                <Select
                  aria-label={t('settings.color')}
                  data={LABEL_COLORS.map((c) => ({ value: c, label: t(`colors.${c}`) }))}
                  value={color}
                  onChange={setColor}
                  allowDeselect={false}
                  w={120}
                />
                <Button
                  variant="default"
                  disabled={!newLabel.trim()}
                  loading={saveLabel.isPending}
                  onClick={() =>
                    saveLabel.mutate(undefined, { onSuccess: () => setNewLabel(''), onError: fail })
                  }
                >
                  {t('common:actions.add')}
                </Button>
              </Group>
            )}
          </Stack>
        </Tabs.Panel>
        <Tabs.Panel value="board">
          <Stack>
            <Checkbox
              label={t('settings.archived')}
              description={t('settings.archivedHint')}
              checked={board.is_archived}
              onChange={(e) => archive.mutate(e.currentTarget.checked, { onError: fail })}
            />
            <Group>
              <Button
                color="red"
                variant="light"
                onClick={async () => {
                  if (
                    await confirmAction({
                      message: t('settings.deleteConfirm'),
                      confirmLabel: t('common:actions.delete'),
                      danger: true,
                    })
                  )
                    remove.mutate(undefined, { onSuccess: () => navigate('/tasks'), onError: fail })
                }}
              >
                {t('settings.deleteBoard')}
              </Button>
            </Group>
          </Stack>
        </Tabs.Panel>
      </Tabs>
    </Modal>
  )
}
