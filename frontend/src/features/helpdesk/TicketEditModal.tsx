import {
  Button,
  Group,
  Modal,
  SegmentedControl,
  Select,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import type { TicketInput } from './api'
import { useCategories, useUpdateTicket } from './queries'
import { TICKET_PRIORITIES, type Ticket, type TicketPriority } from './types'

type Editable = Pick<
  TicketInput,
  'title' | 'description' | 'category_id' | 'priority' | 'contact_phone' | 'location'
>

function initial(ticket: Ticket): Editable {
  return {
    title: ticket.title,
    description: ticket.description,
    category_id: ticket.category?.id ?? 0,
    priority: ticket.priority,
    contact_phone: ticket.contact_phone ?? '',
    location: ticket.location ?? '',
  }
}

/** Requesters fix their own new ticket; handlers re-classify (category/priority recompute the SLA). */
export function TicketEditModal({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const categories = useCategories(true)
  const update = useUpdateTicket(ticket.id)
  const [form, setForm] = useState<Editable>(() => initial(ticket))
  const errors = update.error instanceof ApiError ? update.error.fieldErrors : {}

  const save = () => {
    const before = initial(ticket)
    const changed = Object.fromEntries(
      (Object.keys(form) as (keyof Editable)[])
        .filter((key) => form[key] !== before[key])
        .map((key) => [key, typeof form[key] === 'string' && form[key] === '' ? null : form[key]]),
    ) as Partial<TicketInput>
    if (Object.keys(changed).length === 0) {
      onClose()
      return
    }
    update.mutate(changed, { onSuccess: onClose })
  }

  return (
    <Modal opened onClose={onClose} title={t('workflow.edit')} size="lg" centered>
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <Select
            label={t('fields.category')}
            data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
            value={String(form.category_id)}
            onChange={(v) => v && setForm({ ...form, category_id: Number(v) })}
            allowDeselect={false}
            error={errors.category_id?.[0]}
          />
          <Stack gap={4}>
            <Text size="sm" fw={500} id="edit-priority-label">
              {t('fields.priority')}
            </Text>
            <SegmentedControl
              aria-labelledby="edit-priority-label"
              fullWidth
              value={form.priority}
              onChange={(v) => setForm({ ...form, priority: v as TicketPriority })}
              data={TICKET_PRIORITIES.map((p) => ({ value: p, label: t(`priority.${p}`) }))}
            />
          </Stack>
        </SimpleGrid>
        <TextInput
          label={t('fields.title')}
          value={form.title}
          onChange={(e) => setForm({ ...form, title: e.currentTarget.value })}
          error={errors.title?.[0]}
        />
        <Textarea
          label={t('fields.description')}
          autosize
          minRows={3}
          value={form.description}
          onChange={(e) => setForm({ ...form, description: e.currentTarget.value })}
          error={errors.description?.[0]}
        />
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <TextInput
            label={t('fields.contactPhone')}
            value={form.contact_phone ?? ''}
            onChange={(e) => setForm({ ...form, contact_phone: e.currentTarget.value })}
            error={errors.contact_phone?.[0]}
          />
          <TextInput
            label={t('fields.location')}
            value={form.location ?? ''}
            onChange={(e) => setForm({ ...form, location: e.currentTarget.value })}
            error={errors.location?.[0]}
          />
        </SimpleGrid>
        {update.error && !(update.error instanceof ApiError && update.error.status === 422) && (
          <Text size="sm" c="red" role="alert">
            {toApiError(update.error).message}
          </Text>
        )}
        <Group justify="flex-end">
          <Button onClick={save} loading={update.isPending}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
