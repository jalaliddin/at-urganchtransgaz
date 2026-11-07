import { Button, Group, Modal, NumberInput, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import * as a from './api'
import { useAssetMutation, useAssets } from './queries'
import {
  MANUAL_STATUSES,
  RELATION_TYPES,
  type Asset,
  type AssetStatus,
  type Maintenance,
  type RelationType,
} from './types'

export type AssetDialog = 'assign' | 'return' | 'status' | 'repair' | 'closeRepair' | 'dispose' | 'relation'

function useErrors() {
  const [errors, setErrors] = useState<Record<string, string>>({})
  const capture = (error: unknown) => {
    const api = toApiError(error)
    if (error instanceof ApiError && error.status === 422 && Object.keys(api.fieldErrors).length > 0) {
      setErrors(Object.fromEntries(Object.entries(api.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])))
    } else {
      notifications.show({ color: 'red', message: api.message })
    }
  }
  return { errors, setErrors, capture }
}

function DialogShell({
  title,
  onClose,
  onSubmit,
  pending,
  disabled,
  children,
}: {
  title: string
  onClose: () => void
  onSubmit: () => void
  pending: boolean
  disabled?: boolean
  children: ReactNode
}) {
  const { t } = useTranslation('common')
  return (
    <Modal opened onClose={onClose} title={title} centered>
      <Stack>
        {children}
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('actions.cancel')}
          </Button>
          <Button onClick={onSubmit} loading={pending} disabled={disabled}>
            {t('actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

interface Props {
  dialog: AssetDialog
  asset: Asset
  openRepair?: Maintenance
  onClose: () => void
}

/** All small forms of the asset card; each calls exactly one API action. */
export function AssetDialogs({ dialog, asset, openRepair, onClose }: Props) {
  const { t } = useTranslation(['assets', 'validation'])
  const { errors, setErrors, capture } = useErrors()
  const [employeeId, setEmployeeId] = useState<number | null>(null)
  const [note, setNote] = useState('')
  const [status, setStatus] = useState<AssetStatus>(
    MANUAL_STATUSES.find((s) => s !== asset.status) ?? 'reserve',
  )
  const [vendor, setVendor] = useState('')
  const [ticketId, setTicketId] = useState('')
  const [cost, setCost] = useState<number | string>('')
  const [act, setAct] = useState('')
  const [relationType, setRelationType] = useState<RelationType>('connected_to')
  const [relatedId, setRelatedId] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const candidates = useAssets(
    { search: debounced, per_page: 10 },
    dialog === 'relation' && debounced.length >= 2,
  )
  const done = () => {
    notifications.show({ color: 'green', message: t('settings.saved') })
    onClose()
  }
  const mutation = useAssetMutation(async (): Promise<unknown> => {
    switch (dialog) {
      case 'assign':
        return a.assignAsset(asset.id, employeeId ?? 0, note || undefined)
      case 'return':
        return a.returnAsset(asset.id, note || undefined)
      case 'status':
        return a.setAssetStatus(asset.id, status, note || undefined)
      case 'repair':
        return a.openMaintenance(asset.id, {
          description: note,
          vendor: vendor || undefined,
          ticket_id: ticketId ? Number(ticketId) : null,
        })
      case 'closeRepair':
        return a.closeMaintenance(asset.id, openRepair?.id ?? 0, {
          result: note,
          cost: cost === '' ? null : Number(cost),
        })
      case 'dispose':
        return a.requestDisposal(asset.id, { reason: note, act_number: act || undefined })
      case 'relation':
        return a.addRelation(asset.id, Number(relatedId), relationType, note || undefined)
    }
  })
  const submit = (required: [boolean, string][]) => {
    const local = Object.fromEntries(
      required.filter(([ok]) => !ok).map(([, key]) => [key, t('validation:required')]),
    )
    setErrors(local)
    if (Object.keys(local).length === 0) mutation.mutate(undefined, { onSuccess: done, onError: capture })
  }
  const noteField = (label: string, field = 'note', required = false) => (
    <Textarea
      label={label}
      required={required}
      autosize
      minRows={2}
      value={note}
      onChange={(e) => setNote(e.currentTarget.value)}
      error={errors[field]}
      data-autofocus
    />
  )
  const shell = (title: string, onSubmit: () => void, children: ReactNode) => (
    <DialogShell title={title} onClose={onClose} onSubmit={onSubmit} pending={mutation.isPending}>
      {children}
    </DialogShell>
  )

  switch (dialog) {
    case 'assign':
      return shell(
        t('actions.assign'),
        () => submit([[employeeId !== null, 'employee_id']]),
        <>
          <EmployeeSelect
            label={t('assign.employee')}
            required
            organizationId={asset.organization?.id ?? null}
            value={employeeId}
            onChange={setEmployeeId}
            error={errors.employee_id}
          />
          {noteField(t('assign.note'))}
        </>,
      )
    case 'return':
      return shell(t('actions.return'), () => submit([]), noteField(t('assign.returnNote')))
    case 'status':
      return shell(
        t('actions.status'),
        () => submit([]),
        <>
          <Select
            label={t('fields.status')}
            data={MANUAL_STATUSES.filter((s) => s !== asset.status).map((s) => ({
              value: s,
              label: t(`status.${s}`),
            }))}
            value={status}
            onChange={(v) => setStatus((v as AssetStatus) ?? 'reserve')}
            allowDeselect={false}
            error={errors.status}
          />
          {noteField(t('fields.note'))}
        </>,
      )
    case 'repair':
      return shell(
        t('actions.repair'),
        () => submit([[note.trim() !== '', 'description']]),
        <>
          {noteField(t('repair.description'), 'description', true)}
          <TextInput
            label={t('repair.vendor')}
            value={vendor}
            onChange={(e) => setVendor(e.currentTarget.value)}
          />
          <TextInput
            label={t('repair.ticket')}
            inputMode="numeric"
            value={ticketId}
            onChange={(e) => setTicketId(e.currentTarget.value.replace(/\D/g, ''))}
            error={errors.ticket_id}
          />
        </>,
      )
    case 'closeRepair':
      return shell(
        t('actions.closeRepair'),
        () => submit([[note.trim() !== '', 'result']]),
        <>
          {noteField(t('repair.result'), 'result', true)}
          <NumberInput
            label={t('repair.cost')}
            min={0}
            thousandSeparator=" "
            value={cost}
            onChange={setCost}
          />
        </>,
      )
    case 'dispose':
      return shell(
        t('actions.dispose'),
        () => submit([[note.trim() !== '', 'reason']]),
        <>
          {noteField(t('disposal.reason'), 'reason', true)}
          <TextInput label={t('disposal.act')} value={act} onChange={(e) => setAct(e.currentTarget.value)} />
        </>,
      )
    case 'relation':
      return shell(
        t('actions.addRelation'),
        () => submit([[relatedId !== null, 'related_asset_id']]),
        <>
          <Select
            label={t('relations.type')}
            data={RELATION_TYPES.map((r) => ({ value: r, label: t(`relations.types.${r}`) }))}
            value={relationType}
            onChange={(v) => setRelationType((v as RelationType) ?? 'connected_to')}
            allowDeselect={false}
          />
          <Select
            label={t('relations.asset')}
            required
            searchable
            searchValue={search}
            onSearchChange={setSearch}
            filter={({ options }) => options}
            data={(candidates.data?.data ?? [])
              .filter((c) => c.id !== asset.id)
              .map((c) => ({ value: String(c.id), label: `${c.inventory_number} — ${c.name}` }))}
            value={relatedId}
            onChange={setRelatedId}
            error={errors.related_asset_id}
          />
          <TextInput
            label={t('relations.note')}
            value={note}
            onChange={(e) => setNote(e.currentTarget.value)}
          />
        </>,
      )
  }
}
