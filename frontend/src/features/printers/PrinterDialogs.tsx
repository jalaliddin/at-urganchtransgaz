import {
  Button,
  Checkbox,
  Group,
  Modal,
  NumberInput,
  SegmentedControl,
  Select,
  Stack,
  Switch,
  Text,
  Textarea,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import dayjs, { APP_TIMEZONE } from '@/lib/dayjs'
import { formatDate, formatNumber } from '@/lib/format'
import * as p from './api'
import { usePrinterMutation } from './queries'
import { PRINTER_CONNECTIONS, type PrinterDetail, type Replacement, type ReplacementSource } from './types'

const today = () => dayjs().tz(APP_TIMEZONE).format('YYYY-MM-DD')
type Errors = Record<string, string>

/** Field errors of a 422 as {field: first message}; other failures as a notification. */
function useFailure(setErrors: (e: Errors) => void) {
  return (e: unknown) => {
    const error = toApiError(e)
    const fields = Object.fromEntries(Object.entries(error.fieldErrors).map(([k, v]) => [k, v[0] ?? '']))
    if (Object.keys(fields).length > 0) setErrors(fields)
    else notifications.show({ color: 'red', message: error.message })
  }
}

/** Put a cartridge into the printer: from a warehouse (stock goes down) or from outside it. */
export function ReplaceModal({ printer, onClose }: { printer: PrinterDetail; onClose: () => void }) {
  const { t } = useTranslation(['printers', 'common', 'validation'])
  const options = printer.cartridges
  const [cartridgeId, setCartridgeId] = useState<string | null>(
    options.length === 1 ? String(options[0]!.id) : null,
  )
  const [source, setSource] = useState<ReplacementSource>(
    printer.can.issue_from_warehouse ? 'warehouse' : 'other',
  )
  const [warehouseId, setWarehouseId] = useState<string | null>(null)
  const [date, setDate] = useState<string | null>(today())
  const [counter, setCounter] = useState<number | string>('')
  const [reset, setReset] = useState(false)
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<Errors>({})
  const cartridge = options.find((c) => String(c.id) === cartridgeId)
  const stock = cartridge?.warehouses ?? []
  const warehouse = stock.find((w) => String(w.warehouse.id) === warehouseId) ?? stock[0]
  const run = usePrinterMutation(() =>
    p.replaceCartridge(printer.id, {
      cartridge_model_id: cartridge?.id,
      replaced_at: date,
      source,
      warehouse_id: source === 'warehouse' ? warehouse?.warehouse.id : null,
      note: note.trim() || null,
      counter: counter === '' ? null : { total: Number(counter), is_reset: reset },
    }),
  )
  const fail = useFailure(setErrors)

  const submit = () => {
    const local: Errors = {}
    if (!cartridge) local.cartridge_model_id = t('validation:required')
    if (!date) local.replaced_at = t('validation:required')
    if (source === 'warehouse' && !warehouse) local.warehouse_id = t('replace.noStock')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    run.mutate(undefined, {
      onSuccess: () => {
        notifications.show({ color: 'green', message: t('replace.done') })
        onClose()
      },
      onError: fail,
    })
  }

  return (
    <Modal opened onClose={onClose} title={`${t('replace.title')}: ${printer.name}`} centered size="lg">
      <Stack>
        {!printer.compatibility_defined && (
          <Text size="sm" c="dimmed">
            {t('replace.noCompatibility')}
          </Text>
        )}
        <Select
          label={t('fields.cartridge')}
          placeholder={t('replace.pickCartridge')}
          searchable
          data-autofocus
          data={options.map((c) => ({
            value: String(c.id),
            label: `${c.name}${c.part_number ? ` (${c.part_number})` : ''}, ${t('replace.inStock', { count: Number(c.stock ?? 0) })}`,
          }))}
          value={cartridgeId}
          onChange={setCartridgeId}
          error={errors.cartridge_model_id}
          nothingFoundMessage={t('replace.noCartridges')}
          required
        />
        <SegmentedControl
          aria-label={t('fields.source')}
          value={source}
          onChange={(v) => setSource(v as ReplacementSource)}
          data={[
            { value: 'warehouse', label: t('source.warehouse'), disabled: !printer.can.issue_from_warehouse },
            { value: 'other', label: t('source.other') },
          ]}
        />
        {source === 'warehouse' ? (
          <Select
            label={t('fields.warehouse')}
            data={stock.map((w) => ({
              value: String(w.warehouse.id),
              label: `${w.warehouse.name} (${w.warehouse.organization}), ${t('replace.inStock', { count: Number(w.quantity) })}`,
            }))}
            value={warehouse ? String(warehouse.warehouse.id) : null}
            onChange={setWarehouseId}
            error={errors.warehouse_id}
            placeholder={cartridge && stock.length === 0 ? t('replace.noStock') : undefined}
            description={t('replace.warehouseHint')}
            allowDeselect={false}
          />
        ) : (
          <Text size="sm" c="dimmed">
            {t('replace.otherHint')}
          </Text>
        )}
        <Group grow align="flex-start">
          <DateInput
            label={t('fields.replacedAt')}
            value={date}
            onChange={setDate}
            valueFormat="DD.MM.YYYY"
            maxDate={new Date()}
            error={errors.replaced_at}
            required
          />
          <NumberInput
            label={t('fields.counter')}
            description={
              printer.last_counter
                ? t('counter.last', {
                    total: formatNumber(printer.last_counter.total),
                    date: formatDate(printer.last_counter.read_at),
                  })
                : t('counter.optional')
            }
            min={0}
            allowDecimal={false}
            thousandSeparator=" "
            value={counter}
            onChange={setCounter}
            error={errors['counter.total']}
          />
        </Group>
        {counter !== '' && (
          <Checkbox
            label={t('counter.reset')}
            description={t('counter.resetHint')}
            checked={reset}
            onChange={(e) => setReset(e.currentTarget.checked)}
          />
        )}
        <Textarea
          label={t('fields.note')}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          autosize
          minRows={2}
          maxLength={1000}
        />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button onClick={submit} loading={run.isPending}>
            {t('replace.submit')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function CounterModal({ printer, onClose }: { printer: PrinterDetail; onClose: () => void }) {
  const { t } = useTranslation(['printers', 'common', 'validation'])
  const [date, setDate] = useState<string | null>(today())
  const [total, setTotal] = useState<number | string>('')
  const [reset, setReset] = useState(false)
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<Errors>({})
  const run = usePrinterMutation(() =>
    p.addCounter(printer.id, {
      read_at: date,
      total: Number(total),
      is_reset: reset,
      note: note.trim() || null,
    }),
  )
  const fail = useFailure(setErrors)

  const submit = () => {
    const local: Errors = {}
    if (!date) local.read_at = t('validation:required')
    if (total === '') local.total = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    run.mutate(undefined, {
      onSuccess: () => {
        notifications.show({ color: 'green', message: t('counter.done') })
        onClose()
      },
      onError: fail,
    })
  }

  return (
    <Modal opened onClose={onClose} title={`${t('counter.title')}: ${printer.name}`} centered>
      <Stack>
        <DateInput
          label={t('fields.readAt')}
          value={date}
          onChange={setDate}
          valueFormat="DD.MM.YYYY"
          maxDate={new Date()}
          error={errors.read_at}
          required
        />
        <NumberInput
          label={t('fields.total')}
          description={
            printer.last_counter
              ? t('counter.last', {
                  total: formatNumber(printer.last_counter.total),
                  date: formatDate(printer.last_counter.read_at),
                })
              : undefined
          }
          min={0}
          allowDecimal={false}
          thousandSeparator=" "
          value={total}
          onChange={setTotal}
          error={errors.total}
          required
          data-autofocus
        />
        <Checkbox
          label={t('counter.reset')}
          description={t('counter.resetHint')}
          checked={reset}
          onChange={(e) => setReset(e.currentTarget.checked)}
        />
        <Textarea
          label={t('fields.note')}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          autosize
          minRows={2}
          maxLength={500}
        />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button onClick={submit} loading={run.isPending}>
            {t('counter.submit')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function ProfileModal({ printer, onClose }: { printer: PrinterDetail; onClose: () => void }) {
  const { t } = useTranslation(['printers', 'common'])
  const [connection, setConnection] = useState<string | null>(printer.connection_type)
  const [color, setColor] = useState(printer.is_color)
  const [note, setNote] = useState(printer.note ?? '')
  const [errors, setErrors] = useState<Errors>({})
  const run = usePrinterMutation(() =>
    p.savePrinterProfile(printer.id, {
      connection_type: connection,
      is_color: color,
      note: note.trim() || null,
    }),
  )
  const fail = useFailure(setErrors)

  return (
    <Modal opened onClose={onClose} title={t('profile.title')} centered>
      <Stack>
        <Select
          label={t('fields.connection')}
          clearable
          data={PRINTER_CONNECTIONS.map((v) => ({ value: v, label: t(`connection.${v}`) }))}
          value={connection}
          onChange={setConnection}
          error={errors.connection_type}
          data-autofocus
        />
        <Switch
          label={t('fields.isColor')}
          checked={color}
          onChange={(e) => setColor(e.currentTarget.checked)}
        />
        <Textarea
          label={t('fields.note')}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          autosize
          minRows={2}
          maxLength={2000}
          error={errors.note}
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
                  notifications.show({ color: 'green', message: t('common:saved') })
                  onClose()
                },
                onError: fail,
              })
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** Cancelling returns a warehouse cartridge to its warehouse; the record stays, struck through. */
export function CancelReplacementModal({
  replacement,
  onClose,
}: {
  replacement: Replacement
  onClose: () => void
}) {
  const { t } = useTranslation(['printers', 'common', 'validation'])
  const [reason, setReason] = useState('')
  const [errors, setErrors] = useState<Errors>({})
  const run = usePrinterMutation(() => p.cancelReplacement(replacement.id, reason.trim()))
  const fail = useFailure(setErrors)

  return (
    <Modal opened onClose={onClose} title={t('cancel.title')} centered>
      <Stack>
        <Text size="sm">
          {t('cancel.summary', {
            cartridge: replacement.cartridge.name,
            date: formatDate(replacement.replaced_at),
          })}
        </Text>
        {replacement.stock_document && (
          <Text size="sm" c="dimmed">
            {t('cancel.stockBack', { warehouse: replacement.warehouse?.name ?? '' })}
          </Text>
        )}
        <Textarea
          label={t('cancel.reason')}
          value={reason}
          onChange={(e) => setReason(e.currentTarget.value)}
          error={errors.reason}
          autosize
          minRows={2}
          maxLength={500}
          required
          data-autofocus
        />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.close')}
          </Button>
          <Button
            color="red"
            loading={run.isPending}
            onClick={() => {
              if (!reason.trim()) {
                setErrors({ reason: t('validation:required') })
                return
              }
              run.mutate(undefined, {
                onSuccess: () => {
                  notifications.show({ color: 'green', message: t('cancel.done') })
                  onClose()
                },
                onError: fail,
              })
            }}
          >
            {t('cancel.submit')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
