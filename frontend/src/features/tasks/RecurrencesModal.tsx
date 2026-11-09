import {
  ActionIcon,
  Button,
  Divider,
  Group,
  Modal,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Switch,
  Text,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { formatDate } from '@/lib/format'
import * as s from './api'
import { useBoardMembers, useRecurrences, useTaskMutation } from './queries'
import { PRIORITIES, type BoardDetail, type Recurrence } from './types'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]

/** Routine work of a board: every N days, weeks (on a weekday) or months (on a day). */
export function RecurrencesModal({ board, onClose }: { board: BoardDetail; onClose: () => void }) {
  const { t } = useTranslation(['tasks', 'common', 'validation'])
  const list = useRecurrences(board.id)
  const members = useBoardMembers(board.id)
  const [title, setTitle] = useState('')
  const [frequency, setFrequency] = useState<string | null>('weekly')
  const [interval, setInterval] = useState<string | number>(1)
  const [weekday, setWeekday] = useState<string | null>('1')
  const [monthDay, setMonthDay] = useState<string | number>(1)
  const [dueIn, setDueIn] = useState<string | number>('')
  const [assignee, setAssignee] = useState<string | null>(null)
  const [priority, setPriority] = useState<string | null>('normal')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useTaskMutation(() =>
    s.saveRecurrence(board.id, {
      title,
      frequency,
      interval: Number(interval) || 1,
      weekday: frequency === 'weekly' ? Number(weekday) : null,
      month_day: frequency === 'monthly' ? Number(monthDay) : null,
      due_in_days: dueIn === '' ? null : Number(dueIn),
      assignee_id: assignee ? Number(assignee) : null,
      priority,
    }),
  )
  const toggle = useTaskMutation((r: Recurrence) =>
    s.saveRecurrence(board.id, { is_active: !r.is_active }, r.id),
  )
  const remove = useTaskMutation((id: number) => s.deleteRecurrence(id))
  const describe = (r: Recurrence) =>
    r.frequency === 'weekly'
      ? t('recurrences.weeklyOn', { count: r.interval, day: t(`weekdays.${r.weekday ?? 1}`) })
      : r.frequency === 'monthly'
        ? t('recurrences.monthlyOn', { count: r.interval, day: r.month_day })
        : t('recurrences.daily', { count: r.interval })

  return (
    <Modal opened onClose={onClose} title={t('recurrences.title')} centered size="lg">
      <Stack>
        {list.data?.length === 0 && (
          <Text c="dimmed" size="sm">
            {t('recurrences.empty')}
          </Text>
        )}
        {list.data?.map((r) => (
          <Group key={r.id} justify="space-between" wrap="nowrap">
            <Stack gap={0}>
              <Text size="sm" fw={500}>
                {r.title}
              </Text>
              <Text size="xs" c="dimmed">
                {describe(r)}, {t('recurrences.next', { date: formatDate(r.next_run_on) })}
                {r.assignee ? `, ${r.assignee.name}` : ''}
              </Text>
            </Stack>
            <Group gap={4} wrap="nowrap">
              <Switch
                aria-label={`${t('recurrences.active')}: ${r.title}`}
                checked={r.is_active}
                onChange={() => toggle.mutate(r, { onError: fail })}
              />
              <ActionIcon
                variant="subtle"
                color="red"
                aria-label={`${t('common:actions.delete')}: ${r.title}`}
                onClick={() => remove.mutate(r.id, { onError: fail })}
              >
                <IconTrash size={16} />
              </ActionIcon>
            </Group>
          </Group>
        ))}
        <Divider label={t('recurrences.add')} labelPosition="left" />
        <TextInput
          label={t('fields.title')}
          required
          value={title}
          onChange={(e) => setTitle(e.currentTarget.value)}
          error={errors.title}
        />
        <SimpleGrid cols={{ base: 1, sm: 3 }}>
          <Select
            label={t('recurrences.frequency')}
            data={(['daily', 'weekly', 'monthly'] as const).map((v) => ({
              value: v,
              label: t(`frequency.${v}`),
            }))}
            value={frequency}
            onChange={setFrequency}
            allowDeselect={false}
          />
          <NumberInput
            label={t('recurrences.interval')}
            min={1}
            max={12}
            value={interval}
            onChange={setInterval}
          />
          {frequency === 'weekly' && (
            <Select
              label={t('recurrences.weekday')}
              data={WEEKDAYS.map((d) => ({ value: String(d), label: t(`weekdays.${d}`) }))}
              value={weekday}
              onChange={setWeekday}
              allowDeselect={false}
              error={errors.weekday}
            />
          )}
          {frequency === 'monthly' && (
            <NumberInput
              label={t('recurrences.monthDay')}
              min={1}
              max={28}
              value={monthDay}
              onChange={setMonthDay}
              error={errors.month_day}
            />
          )}
          <NumberInput label={t('recurrences.dueIn')} min={0} max={60} value={dueIn} onChange={setDueIn} />
          <Select
            label={t('fields.assignee')}
            clearable
            searchable
            data={(members.data ?? []).map((m) => ({ value: String(m.id), label: m.name }))}
            value={assignee}
            onChange={setAssignee}
            error={errors.assignee_id}
          />
          <Select
            label={t('fields.priority')}
            data={PRIORITIES.map((p) => ({ value: p, label: t(`priority.${p}`) }))}
            value={priority}
            onChange={setPriority}
            allowDeselect={false}
          />
        </SimpleGrid>
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              if (!title.trim()) {
                setErrors({ title: t('validation:required') })
                return
              }
              save.mutate(undefined, {
                onSuccess: () => {
                  setTitle('')
                  setErrors({})
                },
                onError: (e) => {
                  const fe = Object.fromEntries(
                    Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
                  )
                  if (Object.keys(fe).length) setErrors(fe)
                  else fail(e)
                },
              })
            }}
          >
            {t('recurrences.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
