import { Button, Group, Modal, NumberInput, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { formatMoney } from '@/lib/format'
import * as s from './api'
import { useServicesMutation } from './queries'
import type { Invoice } from './types'

const today = () => new Date().toISOString().slice(0, 10)
const fieldErrors = (e: unknown) =>
  Object.fromEntries(Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']))

/** Field errors go under the fields, anything else into a notification. */
const onError = (setErrors: (e: Record<string, string>) => void) => (e: unknown) => {
  const fe = fieldErrors(e)
  if (Object.keys(fe).length) setErrors(fe)
  else notifications.show({ color: 'red', message: toApiError(e).message })
}

/** Invoice as received from the provider; a new amount needs a new central approval. */
export function EnterInvoiceModal({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const { t } = useTranslation(['services', 'common', 'validation'])
  const [number, setNumber] = useState(invoice.invoice_number ?? '')
  const [date, setDate] = useState<string | null>(invoice.invoice_date ?? today())
  const [amount, setAmount] = useState<string | number>(invoice.amount ?? invoice.planned_amount)
  const [due, setDue] = useState<string | null>(invoice.due_date)
  const [note, setNote] = useState(invoice.note ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useServicesMutation(() =>
    s.enterInvoice(invoice.id, {
      invoice_number: number || null,
      invoice_date: date,
      amount: amount === '' ? null : String(amount),
      due_date: due,
      note: note || null,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('invoices.enter')} centered>
      <Stack>
        {invoice.approved_at && (
          <Text size="sm" c="orange.8">
            {t('invoices.reapproval')}
          </Text>
        )}
        <TextInput
          label={t('invoices.number')}
          value={number}
          onChange={(e) => setNumber(e.currentTarget.value)}
          data-autofocus
        />
        <DateInput
          label={t('invoices.date')}
          value={date}
          onChange={setDate}
          valueFormat="DD.MM.YYYY"
          maxDate={new Date()}
          error={errors.invoice_date}
        />
        <NumberInput
          label={t('invoices.amount')}
          required
          min={0}
          decimalScale={2}
          thousandSeparator=" "
          value={amount}
          onChange={setAmount}
          error={errors.amount}
        />
        <DateInput
          label={t('invoices.dueDate')}
          value={due}
          onChange={setDue}
          valueFormat="DD.MM.YYYY"
          error={errors.due_date}
        />
        <Textarea
          label={t('fields.note')}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              if (amount === '') {
                setErrors({ amount: t('validation:required') })
                return
              }
              save.mutate(undefined, { onSuccess: onClose, onError: onError(setErrors) })
            }}
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function PaymentModal({ invoice, onClose }: { invoice: Invoice; onClose: () => void }) {
  const { t } = useTranslation(['services', 'common', 'validation'])
  const remaining = (Number(invoice.amount ?? 0) - Number(invoice.paid_amount)).toFixed(2)
  const [amount, setAmount] = useState<string | number>(remaining)
  const [date, setDate] = useState<string | null>(today())
  const [document, setDocument] = useState('')
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useServicesMutation(() =>
    s.addPayment(invoice.id, {
      amount: String(amount),
      paid_at: date,
      document_number: document || null,
      note: note || null,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('payments.add')} centered>
      <Stack>
        <Text size="sm" c="dimmed">
          {t('payments.remaining', { amount: formatMoney(remaining) })}
        </Text>
        <NumberInput
          label={t('payments.amount')}
          required
          min={0.01}
          max={Number(remaining)}
          decimalScale={2}
          thousandSeparator=" "
          value={amount}
          onChange={setAmount}
          error={errors.amount}
          data-autofocus
        />
        <DateInput
          label={t('payments.paidAt')}
          required
          value={date}
          onChange={setDate}
          valueFormat="DD.MM.YYYY"
          maxDate={new Date()}
          error={errors.paid_at}
        />
        <TextInput
          label={t('payments.document')}
          value={document}
          onChange={(e) => setDocument(e.currentTarget.value)}
        />
        <Textarea
          label={t('fields.note')}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              const local: Record<string, string> = {}
              if (amount === '' || Number(amount) <= 0) local.amount = t('validation:required')
              if (!date) local.paid_at = t('validation:required')
              setErrors(local)
              if (Object.keys(local).length === 0)
                save.mutate(undefined, { onSuccess: onClose, onError: onError(setErrors) })
            }}
          >
            {t('payments.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** One-off additional cost of a service (installation, equipment …). */
export function ExtraInvoiceModal({ serviceId, onClose }: { serviceId: number; onClose: () => void }) {
  const { t } = useTranslation(['services', 'common', 'validation'])
  const navigate = useNavigate()
  const [description, setDescription] = useState('')
  const [amount, setAmount] = useState<string | number>('')
  const [number, setNumber] = useState('')
  const [date, setDate] = useState<string | null>(today())
  const [due, setDue] = useState<string | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useServicesMutation(() =>
    s.addExtraInvoice(serviceId, {
      description,
      amount: amount === '' ? null : String(amount),
      invoice_number: number || null,
      invoice_date: date,
      due_date: due,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('invoices.addExtra')} centered>
      <Stack>
        <TextInput
          label={t('invoices.descriptionField')}
          required
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
          error={errors.description}
          data-autofocus
        />
        <NumberInput
          label={t('invoices.amount')}
          required
          min={0}
          decimalScale={2}
          thousandSeparator=" "
          value={amount}
          onChange={setAmount}
          error={errors.amount}
        />
        <TextInput
          label={t('invoices.number')}
          value={number}
          onChange={(e) => setNumber(e.currentTarget.value)}
        />
        <DateInput
          label={t('invoices.date')}
          value={date}
          onChange={setDate}
          valueFormat="DD.MM.YYYY"
          maxDate={new Date()}
          clearable
        />
        <DateInput
          label={t('invoices.dueDate')}
          required
          value={due}
          onChange={setDue}
          valueFormat="DD.MM.YYYY"
          error={errors.due_date}
        />
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              const local: Record<string, string> = {}
              if (!description.trim()) local.description = t('validation:required')
              if (amount === '') local.amount = t('validation:required')
              if (!due) local.due_date = t('validation:required')
              setErrors(local)
              if (Object.keys(local).length === 0)
                save.mutate(undefined, {
                  onSuccess: (r) => {
                    onClose()
                    navigate(`/services/invoices/${r.id}`)
                  },
                  onError: onError(setErrors),
                })
            }}
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
