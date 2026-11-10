import { Button, Group, Modal, MultiSelect, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useAssets } from '@/features/assets/queries'
import { usePeople } from '@/features/itsm/people'
import dayjs, { APP_TIMEZONE } from '@/lib/dayjs'
import * as c from './api'
import { useChangeMutation } from './queries'
import {
  CHANGE_CATEGORIES,
  CHANGE_RISKS,
  CHANGE_TYPES,
  type Change,
  type ChangeCategory,
  type ChangeRisk,
  type ChangeType,
} from './types'

/** UTC timestamp from the API → "YYYY-MM-DD HH:mm:ss" in Tashkent for the picker (the API reads it back as Tashkent). */
const local = (value: string | null) =>
  value ? dayjs.utc(value).tz(APP_TIMEZONE).format('YYYY-MM-DD HH:mm:ss') : null

export function ChangeFormModal({
  change,
  problem,
  onClose,
}: {
  change?: Change
  problem?: { id: number; organizationId: number; title: string }
  onClose: () => void
}) {
  const { t } = useTranslation(['changes', 'common', 'validation'])
  const navigate = useNavigate()
  const [organizationId, setOrganizationId] = useState<number | null>(
    change?.organization.id ?? problem?.organizationId ?? null,
  )
  const [title, setTitle] = useState(change?.title ?? problem?.title ?? '')
  const [type, setType] = useState<ChangeType>(change?.type ?? 'normal')
  const [category, setCategory] = useState<ChangeCategory>(change?.category ?? 'network')
  const [risk, setRisk] = useState<ChangeRisk>(change?.risk ?? 'medium')
  const [start, setStart] = useState<string | null>(local(change?.planned_start ?? null))
  const [end, setEnd] = useState<string | null>(local(change?.planned_end ?? null))
  const [implementer, setImplementer] = useState<string | null>(
    change?.implementer ? String(change.implementer.id) : null,
  )
  const [assets, setAssets] = useState<string[]>(change?.assets.map((a) => String(a.id)) ?? [])
  const [assetSearch, setAssetSearch] = useState('')
  const [debounced] = useDebouncedValue(assetSearch, 300)
  const [text, setText] = useState({
    description: change?.description ?? '',
    impact: change?.impact ?? '',
    plan: change?.plan ?? '',
    backout_plan: change?.backout_plan ?? '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const people = usePeople('change', organizationId)
  const assetList = useAssets(
    {
      per_page: 30,
      ...(organizationId ? { 'filter[organization]': organizationId } : {}),
      ...(debounced ? { search: debounced } : {}),
    },
    organizationId !== null,
  )
  const known = new Map(
    (change?.assets ?? []).map((a) => [String(a.id), `${a.inventory_number} — ${a.name}`]),
  )
  ;(assetList.data?.data ?? []).forEach((a) => known.set(String(a.id), `${a.inventory_number} — ${a.name}`))
  const save = useChangeMutation(() =>
    c.saveChange(
      {
        ...(change ? {} : { organization_id: organizationId, problem_id: problem?.id ?? null }),
        title: title.trim(),
        type,
        category,
        risk,
        planned_start: start,
        planned_end: end,
        implementer_id: implementer ? Number(implementer) : null,
        asset_ids: assets.map(Number),
        ...Object.fromEntries(Object.entries(text).map(([k, v]) => [k, v.trim() || null])),
      },
      change?.id,
    ),
  )

  const submit = () => {
    const localErrors: Record<string, string> = {}
    if (!title.trim()) localErrors.title = t('validation:required')
    if (!change && !organizationId) localErrors.organization_id = t('validation:required')
    setErrors(localErrors)
    if (Object.keys(localErrors).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => {
        notifications.show({ color: 'green', message: t('common:saved') })
        onClose()
        if (!change) navigate(`/changes/${saved.id}`)
      },
      onError: (e) => {
        const error = toApiError(e)
        const fields = Object.fromEntries(
          Object.entries(error.fieldErrors).map(([k, v]) => [
            k.startsWith('asset_ids') ? 'asset_ids' : k,
            v[0] ?? '',
          ]),
        )
        if (Object.keys(fields).length > 0) setErrors(fields)
        else notifications.show({ color: 'red', message: error.message })
      },
    })
  }
  const area = (key: keyof typeof text, rows = 3) => (
    <Textarea
      label={t(`fields.${key}`)}
      value={text[key]}
      onChange={(e) => setText({ ...text, [key]: e.currentTarget.value })}
      error={errors[key]}
      autosize
      minRows={rows}
    />
  )

  return (
    <Modal opened onClose={onClose} title={change ? t('form.edit') : t('form.new')} centered size="xl">
      <Stack>
        {!change && (
          <OrganizationSelect
            label={t('fields.organization')}
            value={organizationId}
            onChange={(v) => {
              setOrganizationId(v)
              setImplementer(null)
              setAssets([])
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
        <Group grow align="flex-start">
          <Select
            label={t('fields.type')}
            data={CHANGE_TYPES.map((v) => ({ value: v, label: t(`type.${v}`) }))}
            value={type}
            onChange={(v) => v && setType(v as ChangeType)}
            allowDeselect={false}
            description={t(`typeHint.${type}`)}
          />
          <Select
            label={t('fields.category')}
            data={CHANGE_CATEGORIES.map((v) => ({ value: v, label: t(`category.${v}`) }))}
            value={category}
            onChange={(v) => v && setCategory(v as ChangeCategory)}
            allowDeselect={false}
          />
          <Select
            label={t('fields.risk')}
            data={CHANGE_RISKS.map((v) => ({ value: v, label: t(`risk.${v}`) }))}
            value={risk}
            onChange={(v) => v && setRisk(v as ChangeRisk)}
            allowDeselect={false}
          />
        </Group>
        <Group grow align="flex-start">
          <DateTimePicker
            label={t('fields.plannedStart')}
            value={start}
            onChange={setStart}
            valueFormat="DD.MM.YYYY HH:mm"
            clearable
            error={errors.planned_start}
          />
          <DateTimePicker
            label={t('fields.plannedEnd')}
            value={end}
            onChange={setEnd}
            valueFormat="DD.MM.YYYY HH:mm"
            clearable
            error={errors.planned_end}
          />
        </Group>
        <Select
          label={t('fields.implementer')}
          clearable
          searchable
          disabled={!organizationId}
          data={(people.data ?? []).map((u) => ({ value: String(u.id), label: u.name }))}
          value={implementer}
          onChange={setImplementer}
          error={errors.implementer_id}
        />
        <MultiSelect
          label={t('fields.assets')}
          description={t('form.assetsHint')}
          searchable
          disabled={!organizationId}
          data={[...known.entries()].map(([value, label]) => ({ value, label }))}
          value={assets}
          onChange={setAssets}
          searchValue={assetSearch}
          onSearchChange={setAssetSearch}
          filter={({ options }) => options}
          error={errors.asset_ids}
        />
        {area('description')}
        {area('impact', 2)}
        {area('plan', 4)}
        {area('backout_plan', 3)}
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
