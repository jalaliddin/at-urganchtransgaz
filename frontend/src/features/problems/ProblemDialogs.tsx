import { Button, Checkbox, Group, Modal, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { useTickets } from '@/features/helpdesk/queries'
import * as p from './api'
import { useProblemMutation } from './queries'
import type { Problem, ProblemStatus } from './types'

const NEEDS: Partial<Record<ProblemStatus, ('workaround' | 'root_cause' | 'resolution')[]>> = {
  known_error: ['workaround'],
  resolved: ['root_cause', 'resolution'],
}

/** Status change with the fields the target status needs (design R2). */
export function TransitionModal({
  problem,
  target,
  onClose,
}: {
  problem: Problem
  target: ProblemStatus
  onClose: () => void
}) {
  const { t } = useTranslation(['problems', 'common', 'validation'])
  const [fields, setFields] = useState({
    workaround: problem.workaround ?? '',
    root_cause: problem.root_cause ?? '',
    resolution: problem.resolution ?? '',
  })
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const needsReason = target === 'closed' && problem.status !== 'resolved'
  const run = useProblemMutation(() =>
    p.transitionProblem(problem.id, { status: target, ...fields, reason: reason.trim() || null }),
  )

  return (
    <Modal opened onClose={onClose} title={t(`transition.${target}`)} centered size="lg">
      <Stack>
        {(NEEDS[target] ?? []).map((f, i) => (
          <Textarea
            key={f}
            label={t(`fields.${f}`)}
            value={fields[f]}
            onChange={(e) => setFields({ ...fields, [f]: e.currentTarget.value })}
            error={errors[f]}
            autosize
            minRows={3}
            required
            data-autofocus={i === 0 || undefined}
          />
        ))}
        {target === 'closed' && (
          <Textarea
            label={t('fields.closeReason')}
            description={needsReason ? t('transition.reasonHint') : undefined}
            value={reason}
            onChange={(e) => setReason(e.currentTarget.value)}
            error={errors.reason}
            required={needsReason}
            autosize
            minRows={2}
          />
        )}
        {target === 'investigating' && <Text size="sm">{t('transition.reopenHint')}</Text>}
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button
            loading={run.isPending}
            onClick={() =>
              run.mutate(undefined, {
                onSuccess: () => {
                  notifications.show({ color: 'green', message: t('transition.done') })
                  onClose()
                },
                onError: (e) => {
                  const error = toApiError(e)
                  const fe = Object.fromEntries(
                    Object.entries(error.fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
                  )
                  if (Object.keys(fe).length > 0) setErrors(fe)
                  else notifications.show({ color: 'red', message: error.message })
                },
              })
            }
          >
            {t(`transition.${target}`)}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** Link tickets of the problem's organization subtree (design R3). */
export function LinkTicketsModal({ problem, onClose }: { problem: Problem; onClose: () => void }) {
  const { t } = useTranslation(['problems', 'common'])
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const [chosen, setChosen] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)
  const linked = new Set(problem.tickets.map((tk) => tk.id))
  const tickets = useTickets({
    per_page: 20,
    'filter[organization]': problem.organization.id,
    ...(debounced ? { search: debounced } : {}),
  })
  const run = useProblemMutation(() => p.linkTickets(problem.id, chosen))

  return (
    <Modal opened onClose={onClose} title={t('tickets.link')} centered size="lg">
      <Stack>
        <TextInput
          placeholder={t('tickets.search')}
          aria-label={t('tickets.search')}
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          data-autofocus
        />
        <Stack gap={6} mah={340} style={{ overflowY: 'auto' }}>
          {(tickets.data?.data ?? []).map((tk) => (
            <Checkbox
              key={tk.id}
              disabled={linked.has(tk.id)}
              checked={linked.has(tk.id) || chosen.includes(tk.id)}
              onChange={(e) =>
                setChosen(e.currentTarget.checked ? [...chosen, tk.id] : chosen.filter((x) => x !== tk.id))
              }
              label={`${tk.number}: ${tk.title}`}
            />
          ))}
        </Stack>
        {error && (
          <Text size="sm" c="red">
            {error}
          </Text>
        )}
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button
            disabled={chosen.length === 0}
            loading={run.isPending}
            onClick={() =>
              run.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => setError(toApiError(e).message),
              })
            }
          >
            {t('tickets.linkSelected', { count: chosen.length })}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
