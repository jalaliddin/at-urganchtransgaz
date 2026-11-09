import {
  Button,
  Group,
  Modal,
  MultiSelect,
  Select,
  SimpleGrid,
  Stack,
  TextInput,
  Textarea,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import * as s from './api'
import { useBoard, useBoardMembers, useTaskMutation } from './queries'
import { PRIORITIES, type TaskDetail } from './types'

/** New task on a board (optionally in a given column), or the editable fields of an existing one. */
export function TaskFormModal({
  boardId,
  columnId,
  task,
  onClose,
  onCreated,
}: {
  boardId: number
  columnId?: number
  task?: TaskDetail
  onClose: () => void
  onCreated?: (task: TaskDetail) => void
}) {
  const { t } = useTranslation(['tasks', 'common', 'validation'])
  const board = useBoard(boardId)
  const members = useBoardMembers(boardId)
  const [title, setTitle] = useState(task?.title ?? '')
  const [description, setDescription] = useState(task?.description ?? '')
  const [column, setColumn] = useState<string | null>(columnId ? String(columnId) : null)
  const [assignee, setAssignee] = useState<string | null>(task?.assignee ? String(task.assignee.id) : null)
  const [priority, setPriority] = useState<string | null>(task?.priority ?? 'normal')
  const [start, setStart] = useState<string | null>(task?.start_date ?? null)
  const [due, setDue] = useState<string | null>(task?.due_date ?? null)
  const [labels, setLabels] = useState<string[]>(task?.labels.map((l) => String(l.id)) ?? [])
  const [watchers, setWatchers] = useState<string[]>(task?.watchers.map((w) => String(w.id)) ?? [])
  const [project, setProject] = useState(task?.project ?? '')
  const [organizationId, setOrganizationId] = useState<number | null>(task?.organization.id ?? null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useTaskMutation(() => {
    const values = {
      title,
      description: description || null,
      assignee_id: assignee ? Number(assignee) : null,
      priority,
      start_date: start,
      due_date: due,
      label_ids: labels.map(Number),
      watcher_ids: watchers.map(Number),
      project: project || null,
      organization_id: organizationId,
    }
    return task
      ? s.updateTask(task.id, values)
      : s.createTask(boardId, { ...values, column_id: column ? Number(column) : null })
  })
  const people = (members.data ?? []).map((m) => ({ value: String(m.id), label: m.name }))
  const workers = (members.data ?? [])
    .filter((m) => m.can_be_assignee)
    .map((m) => ({ value: String(m.id), label: m.name }))

  const submit = () => {
    if (!title.trim()) {
      setErrors({ title: t('validation:required') })
      return
    }
    save.mutate(undefined, {
      onSuccess: (saved) => {
        onClose()
        if (!task) onCreated?.(saved)
      },
      onError: (e) => {
        const fe = Object.fromEntries(
          Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
        )
        if (Object.keys(fe).length) setErrors(fe)
        else notifications.show({ color: 'red', message: toApiError(e).message })
      },
    })
  }

  return (
    <Modal opened onClose={onClose} title={task ? t('form.edit') : t('form.add')} centered size="lg">
      <Stack>
        <TextInput
          label={t('fields.title')}
          required
          value={title}
          onChange={(e) => setTitle(e.currentTarget.value)}
          error={errors.title}
          data-autofocus
        />
        <Textarea
          label={t('fields.description')}
          autosize
          minRows={3}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
        />
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {!task && (
            <Select
              label={t('fields.column')}
              data={(board.data?.columns ?? [])
                .filter((c) => c.category === 'open')
                .map((c) => ({ value: String(c.id), label: c.name }))}
              value={column}
              onChange={setColumn}
              placeholder={board.data?.columns[0]?.name}
              clearable
            />
          )}
          <Select
            label={t('fields.assignee')}
            searchable
            clearable
            data={workers}
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
          <DateInput
            label={t('fields.startDate')}
            value={start}
            onChange={setStart}
            valueFormat="DD.MM.YYYY"
            clearable
          />
          <DateInput
            label={t('fields.dueDate')}
            value={due}
            onChange={setDue}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.due_date}
          />
          <TextInput
            label={t('fields.project')}
            value={project}
            onChange={(e) => setProject(e.currentTarget.value)}
          />
          <OrganizationSelect
            label={t('fields.organization')}
            description={t('form.organizationHint')}
            value={organizationId}
            onChange={setOrganizationId}
            error={errors.organization_id}
          />
        </SimpleGrid>
        <MultiSelect
          label={t('fields.labels')}
          data={(board.data?.labels ?? []).map((l) => ({ value: String(l.id), label: l.name }))}
          value={labels}
          onChange={setLabels}
          clearable
        />
        <MultiSelect
          label={t('fields.watchers')}
          searchable
          data={people}
          value={watchers}
          onChange={setWatchers}
          clearable
          error={errors.watcher_ids}
        />
        <Group justify="flex-end">
          <Button loading={save.isPending} onClick={submit}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
