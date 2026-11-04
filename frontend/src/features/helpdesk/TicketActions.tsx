import { Button, Group, Modal, NumberInput, Rating, Select, Stack, Text, Textarea } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import {
  IconCheck,
  IconClockPlus,
  IconHandGrab,
  IconRefresh,
  IconStar,
  IconUserPlus,
} from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { useAssignees, useTicketAction } from './queries'
import type { Ticket, TicketStatus } from './types'

type Dialog =
  | { kind: 'transition'; status: TicketStatus }
  | { kind: 'assign' }
  | { kind: 'time' }
  | { kind: 'rate' }
  | null

/** Statuses whose change asks for a note; the first two require it (resolution / reason). */
const NOTE_REQUIRED: TicketStatus[] = ['resolved', 'rejected']
const NOTE_ASKED: TicketStatus[] = [...NOTE_REQUIRED, 'waiting_user']

function useNotify() {
  const { t } = useTranslation('helpdesk')
  return {
    ok: () => notifications.show({ color: 'green', message: t('workflow.done') }),
    fail: (error: unknown) => notifications.show({ color: 'red', message: toApiError(error).message }),
  }
}

/** Buttons come only from the server's allowed_actions / allowed_transitions. */
export function TicketActions({ ticket }: { ticket: Ticket }) {
  const { t } = useTranslation('helpdesk')
  const actions = useTicketAction(ticket.id)
  const notify = useNotify()
  const [dialog, setDialog] = useState<Dialog>(null)
  const can = (action: Ticket['allowed_actions'][number]) => ticket.allowed_actions.includes(action)
  const finished = ticket.status === 'resolved' || ticket.status === 'closed'

  const transitionLabel = (status: TicketStatus) =>
    status === 'in_progress' && finished ? t('workflow.to.reopen') : t(`workflow.to.${status}`)

  const go = (status: TicketStatus) => {
    if (NOTE_ASKED.includes(status)) setDialog({ kind: 'transition', status })
    else actions.transition.mutate({ status }, { onSuccess: notify.ok, onError: notify.fail })
  }

  const primary = ticket.allowed_transitions[0]
  const secondary = ticket.allowed_transitions.slice(1)

  return (
    <>
      <Group gap="sm" wrap="wrap">
        {primary && (
          <Button
            leftSection={
              primary === 'in_progress' && finished ? <IconRefresh size={16} /> : <IconCheck size={16} />
            }
            onClick={() => go(primary)}
            loading={actions.transition.isPending}
          >
            {transitionLabel(primary)}
          </Button>
        )}
        {secondary.map((status) => (
          <Button
            key={status}
            variant="default"
            onClick={() => go(status)}
            color={status === 'rejected' ? 'red' : undefined}
          >
            {transitionLabel(status)}
          </Button>
        ))}
        {can('take') && (
          <Button
            variant="light"
            leftSection={<IconHandGrab size={16} />}
            loading={actions.take.isPending}
            onClick={() => actions.take.mutate(undefined, { onSuccess: notify.ok, onError: notify.fail })}
          >
            {t('workflow.take')}
          </Button>
        )}
        {can('assign') && (
          <Button
            variant="light"
            leftSection={<IconUserPlus size={16} />}
            onClick={() => setDialog({ kind: 'assign' })}
          >
            {t('workflow.assign')}
          </Button>
        )}
        {can('rate') && (
          <Button
            variant="light"
            leftSection={<IconStar size={16} />}
            onClick={() => setDialog({ kind: 'rate' })}
          >
            {t('workflow.rate')}
          </Button>
        )}
        {can('log_time') && (
          <Button
            variant="subtle"
            leftSection={<IconClockPlus size={16} />}
            onClick={() => setDialog({ kind: 'time' })}
          >
            {t('workflow.logTime')}
          </Button>
        )}
      </Group>

      {dialog?.kind === 'transition' && (
        <TransitionDialog
          ticket={ticket}
          status={dialog.status}
          label={transitionLabel(dialog.status)}
          onClose={() => setDialog(null)}
        />
      )}
      {dialog?.kind === 'assign' && <AssignDialog ticket={ticket} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'time' && <TimeDialog ticket={ticket} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'rate' && <RateDialog ticket={ticket} onClose={() => setDialog(null)} />}
    </>
  )
}

function TransitionDialog({
  ticket,
  status,
  label,
  onClose,
}: {
  ticket: Ticket
  status: TicketStatus
  label: string
  onClose: () => void
}) {
  const { t } = useTranslation(['helpdesk', 'validation'])
  const { transition } = useTicketAction(ticket.id)
  const notify = useNotify()
  const [note, setNote] = useState('')
  const [error, setError] = useState<string | null>(null)
  const required = NOTE_REQUIRED.includes(status)
  const noteLabel =
    status === 'resolved'
      ? t('workflow.resolutionLabel')
      : status === 'rejected'
        ? t('workflow.rejectLabel')
        : t('workflow.waitingLabel')

  const submit = () => {
    if (required && note.trim() === '') {
      setError(t('validation:required'))
      return
    }
    transition.mutate(
      { status, note: note.trim() || undefined },
      {
        onSuccess: () => {
          notify.ok()
          onClose()
        },
        onError: (e) => setError(toApiError(e).fieldErrors.note?.[0] ?? toApiError(e).message),
      },
    )
  }

  return (
    <Modal opened onClose={onClose} title={label} centered>
      <Stack>
        <Textarea
          label={noteLabel}
          required={required}
          autosize
          minRows={3}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          error={error}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button onClick={submit} loading={transition.isPending}>
            {t('workflow.confirm')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function AssignDialog({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const { t } = useTranslation('helpdesk')
  const { assign } = useTicketAction(ticket.id)
  const assignees = useAssignees(ticket.organization?.id ?? null)
  const notify = useNotify()
  const [value, setValue] = useState<string | null>(ticket.assignee ? String(ticket.assignee.id) : null)

  return (
    <Modal opened onClose={onClose} title={t('workflow.assign')} centered>
      <Stack>
        <Select
          label={t('workflow.assignee')}
          searchable
          data={(assignees.data ?? []).map((u) => ({ value: String(u.id), label: u.name }))}
          value={value}
          onChange={setValue}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button
            disabled={value === null}
            loading={assign.isPending}
            onClick={() =>
              value !== null &&
              assign.mutate(Number(value), {
                onSuccess: () => {
                  notify.ok()
                  onClose()
                },
                onError: notify.fail,
              })
            }
          >
            {t('workflow.confirm')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function TimeDialog({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const { t } = useTranslation('helpdesk')
  const { logTime } = useTicketAction(ticket.id)
  const notify = useNotify()
  const [minutes, setMinutes] = useState<number | string>(30)
  const [note, setNote] = useState('')

  return (
    <Modal opened onClose={onClose} title={t('workflow.logTime')} centered>
      <Stack>
        <NumberInput
          label={t('workflow.minutes')}
          min={1}
          max={1440}
          value={minutes}
          onChange={setMinutes}
          data-autofocus
        />
        <Textarea
          label={t('workflow.noteLabel')}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={logTime.isPending}
            disabled={Number(minutes) < 1}
            onClick={() =>
              logTime.mutate(
                { minutes: Number(minutes), note: note.trim() || undefined },
                {
                  onSuccess: () => {
                    notify.ok()
                    onClose()
                  },
                  onError: notify.fail,
                },
              )
            }
          >
            {t('workflow.confirm')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function RateDialog({ ticket, onClose }: { ticket: Ticket; onClose: () => void }) {
  const { t } = useTranslation('helpdesk')
  const { rate } = useTicketAction(ticket.id)
  const notify = useNotify()
  const [rating, setRating] = useState(ticket.rating ?? 0)
  const [comment, setComment] = useState(ticket.rating_comment ?? '')

  return (
    <Modal opened onClose={onClose} title={t('workflow.rate')} centered>
      <Stack>
        <Stack gap={4}>
          <Text size="sm" fw={500} id="rating-label">
            {t('fields.rating')}
          </Text>
          <Rating value={rating} onChange={setRating} size="lg" aria-labelledby="rating-label" />
        </Stack>
        <Textarea
          label={t('workflow.ratingComment')}
          value={comment}
          onChange={(e) => setComment(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            disabled={rating < 1}
            loading={rate.isPending}
            onClick={() =>
              rate.mutate(
                { rating, comment: comment.trim() || undefined },
                {
                  onSuccess: () => {
                    notify.ok()
                    onClose()
                  },
                  onError: notify.fail,
                },
              )
            }
          >
            {t('workflow.confirm')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
