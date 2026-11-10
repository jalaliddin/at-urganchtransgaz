import {
  Button,
  Checkbox,
  Group,
  Modal,
  SegmentedControl,
  Stack,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import dayjs, { APP_TIMEZONE } from '@/lib/dayjs'
import { formatDate } from '@/lib/format'
import * as m from './api'
import { useMaintenanceMutation } from './queries'
import type { TaskResult, WorkItem } from './types'

const today = () => dayjs().tz(APP_TIMEZONE).format('YYYY-MM-DD')

/** Service record: each checklist step, result, note and date (design M4). */
export function CompleteModal({ item, onClose }: { item: WorkItem; onClose: () => void }) {
  const { t } = useTranslation(['maintenance', 'common'])
  const [answers, setAnswers] = useState(item.plan.checklist.map(() => ({ done: true, note: '' })))
  const [result, setResult] = useState<TaskResult>('ok')
  const [note, setNote] = useState('')
  const [date, setDate] = useState<string | null>(today())
  const [error, setError] = useState<string | null>(null)
  const run = useMaintenanceMutation(() =>
    m.completeWork(item.id, {
      result,
      note: note.trim() || null,
      completed_on: date,
      checklist: answers.map((a) => ({ done: a.done, note: a.note.trim() || null })),
    }),
  )

  return (
    <Modal opened onClose={onClose} title={`${item.plan.title}: ${item.asset.name}`} centered size="lg">
      <Stack>
        <Text size="sm" c="dimmed">
          {t('complete.due', { date: formatDate(item.due_on), number: item.asset.inventory_number })}
        </Text>
        {item.plan.checklist.length > 0 && (
          <Stack gap="xs">
            {item.plan.checklist.map((step, i) => (
              <Group key={i} gap="sm" wrap="nowrap" align="flex-start">
                <Checkbox
                  mt={6}
                  label={step}
                  checked={answers[i]?.done ?? false}
                  onChange={(e) =>
                    setAnswers(answers.map((a, n) => (n === i ? { ...a, done: e.currentTarget.checked } : a)))
                  }
                  style={{ flex: 1 }}
                />
                <TextInput
                  aria-label={t('complete.stepNote', { step })}
                  placeholder={t('complete.stepNotePlaceholder')}
                  value={answers[i]?.note ?? ''}
                  onChange={(e) =>
                    setAnswers(answers.map((a, n) => (n === i ? { ...a, note: e.currentTarget.value } : a)))
                  }
                  w={180}
                  size="xs"
                />
              </Group>
            ))}
          </Stack>
        )}
        <SegmentedControl
          aria-label={t('fields.result')}
          value={result}
          onChange={(v) => setResult(v as TaskResult)}
          data={[
            { value: 'ok', label: t('result.ok') },
            { value: 'issues', label: t('result.issues') },
          ]}
        />
        <Textarea
          label={t('fields.note')}
          description={result === 'issues' ? t('complete.issuesHint') : undefined}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          autosize
          minRows={2}
        />
        <DateInput
          label={t('fields.completedOn')}
          value={date}
          onChange={setDate}
          valueFormat="DD.MM.YYYY"
          maxDate={new Date()}
          error={error}
        />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button
            loading={run.isPending}
            onClick={() =>
              run.mutate(undefined, {
                onSuccess: () => {
                  notifications.show({ color: 'green', message: t('complete.done') })
                  onClose()
                },
                onError: (e) => {
                  const apiError = toApiError(e)
                  setError(Object.values(apiError.fieldErrors)[0]?.[0] ?? null)
                  if (Object.keys(apiError.fieldErrors).length === 0)
                    notifications.show({ color: 'red', message: apiError.message })
                },
              })
            }
          >
            {t('complete.submit')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function SkipModal({ item, onClose }: { item: WorkItem; onClose: () => void }) {
  const { t } = useTranslation(['maintenance', 'common', 'validation'])
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)
  const run = useMaintenanceMutation(() => m.skipWork(item.id, reason.trim()))

  return (
    <Modal opened onClose={onClose} title={t('skip.title')} centered>
      <Stack>
        <Text size="sm">{t('skip.hint')}</Text>
        <Textarea
          label={t('skip.reason')}
          value={reason}
          onChange={(e) => setReason(e.currentTarget.value)}
          error={error}
          required
          autosize
          minRows={2}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.close')}
          </Button>
          <Button
            color="orange"
            loading={run.isPending}
            onClick={() => {
              if (!reason.trim()) {
                setError(t('validation:required'))
                return
              }
              run.mutate(undefined, { onSuccess: onClose, onError: (e) => setError(toApiError(e).message) })
            }}
          >
            {t('skip.submit')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
