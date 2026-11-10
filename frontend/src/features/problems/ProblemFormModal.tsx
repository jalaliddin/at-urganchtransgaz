import { Button, Group, Modal, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCategories } from '@/features/helpdesk/queries'
import { usePeople } from '@/features/itsm/people'
import * as p from './api'
import { useProblemMutation } from './queries'
import { PROBLEM_PRIORITIES, type Problem, type ProblemPriority } from './types'

interface Prefill {
  organizationId?: number | null
  categoryId?: number | null
  title?: string
  ticketIds?: number[]
}

/** Create (optionally from a recurring-ticket candidate) or edit a problem. */
export function ProblemFormModal({
  problem,
  prefill,
  onClose,
}: {
  problem?: Problem
  prefill?: Prefill
  onClose: () => void
}) {
  const { t } = useTranslation(['problems', 'common', 'validation'])
  const navigate = useNavigate()
  const categories = useCategories(true)
  const [organizationId, setOrganizationId] = useState<number | null>(
    problem?.organization.id ?? prefill?.organizationId ?? null,
  )
  const [title, setTitle] = useState(problem?.title ?? prefill?.title ?? '')
  const [description, setDescription] = useState(problem?.description ?? '')
  const [category, setCategory] = useState<string | null>(
    problem?.category ? String(problem.category.id) : prefill?.categoryId ? String(prefill.categoryId) : null,
  )
  const [priority, setPriority] = useState<ProblemPriority>(problem?.priority ?? 'normal')
  const [assignee, setAssignee] = useState<string | null>(
    problem?.assignee ? String(problem.assignee.id) : null,
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const people = usePeople('problem', organizationId)
  const save = useProblemMutation(() =>
    p.saveProblem(
      {
        ...(problem ? {} : { organization_id: organizationId, ticket_ids: prefill?.ticketIds ?? [] }),
        title: title.trim(),
        description: description.trim() || null,
        category_id: category ? Number(category) : null,
        priority,
        assignee_id: assignee ? Number(assignee) : null,
      },
      problem?.id,
    ),
  )

  const submit = () => {
    const local: Record<string, string> = {}
    if (!title.trim()) local.title = t('validation:required')
    if (!problem && !organizationId) local.organization_id = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => {
        notifications.show({ color: 'green', message: t('common:saved') })
        onClose()
        if (!problem) navigate(`/problems/${saved.id}`)
      },
      onError: (e) => {
        const error = toApiError(e)
        const fields = Object.fromEntries(
          Object.entries(error.fieldErrors).map(([k, v]) => [
            k.startsWith('ticket_ids') ? 'ticket_ids' : k,
            v[0] ?? '',
          ]),
        )
        if (Object.keys(fields).length > 0) setErrors(fields)
        else notifications.show({ color: 'red', message: error.message })
      },
    })
  }

  return (
    <Modal opened onClose={onClose} title={problem ? t('form.edit') : t('form.new')} centered size="lg">
      <Stack>
        {!problem && (
          <OrganizationSelect
            label={t('fields.organization')}
            value={organizationId}
            onChange={(v) => {
              setOrganizationId(v)
              setAssignee(null)
            }}
            error={errors.organization_id}
            required
          />
        )}
        <TextInput
          label={t('fields.title')}
          value={title}
          onChange={(e) => setTitle(e.currentTarget.value)}
          error={errors.title}
          required
          maxLength={255}
          data-autofocus
        />
        <Textarea
          label={t('fields.description')}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
          autosize
          minRows={3}
        />
        <Group grow align="flex-start">
          <Select
            label={t('fields.category')}
            clearable
            data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
            value={category}
            onChange={setCategory}
          />
          <Select
            label={t('fields.priority')}
            data={PROBLEM_PRIORITIES.map((v) => ({ value: v, label: t(`priority.${v}`) }))}
            value={priority}
            onChange={(v) => v && setPriority(v as ProblemPriority)}
            allowDeselect={false}
          />
        </Group>
        <Select
          label={t('fields.assignee')}
          clearable
          searchable
          disabled={!organizationId}
          data={(people.data ?? []).map((u) => ({ value: String(u.id), label: u.name }))}
          value={assignee}
          onChange={setAssignee}
          error={errors.assignee_id}
        />
        {prefill?.ticketIds && prefill.ticketIds.length > 0 && (
          <TextInput
            label={t('fields.tickets')}
            value={t('form.ticketsLinked', { count: prefill.ticketIds.length })}
            readOnly
            error={errors.ticket_ids}
          />
        )}
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button onClick={submit} loading={save.isPending}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
