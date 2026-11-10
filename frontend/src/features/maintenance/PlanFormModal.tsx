import {
  ActionIcon,
  Button,
  Group,
  Modal,
  MultiSelect,
  NumberInput,
  Select,
  Stack,
  Switch,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useAssets } from '@/features/assets/queries'
import { usePeople } from '@/features/itsm/people'
import * as m from './api'
import { useMaintenanceMutation } from './queries'
import { MAINTENANCE_UNITS, type MaintenanceUnit, type Plan } from './types'

/** Plan of scheduled service: devices, interval, checklist, responsible person (design M1). */
export function PlanFormModal({ plan, onClose }: { plan?: Plan; onClose: () => void }) {
  const { t } = useTranslation(['maintenance', 'common', 'validation'])
  const [organizationId, setOrganizationId] = useState<number | null>(plan?.organization.id ?? null)
  const [title, setTitle] = useState(plan?.title ?? '')
  const [description, setDescription] = useState(plan?.description ?? '')
  const [steps, setSteps] = useState<string[]>(plan?.checklist.length ? plan.checklist : [''])
  const [count, setCount] = useState<number | string>(plan?.interval_count ?? 1)
  const [unit, setUnit] = useState<MaintenanceUnit>(plan?.interval_unit ?? 'month')
  const [start, setStart] = useState<string | null>(plan?.start_on ?? null)
  const [lead, setLead] = useState<number | string>(plan?.lead_days ?? 7)
  const [active, setActive] = useState(plan?.is_active ?? true)
  const [assignee, setAssignee] = useState<string | null>(plan?.assignee ? String(plan.assignee.id) : null)
  const [assets, setAssets] = useState<string[]>(plan?.assets.map((a) => String(a.id)) ?? [])
  const [assetSearch, setAssetSearch] = useState('')
  const [debounced] = useDebouncedValue(assetSearch, 300)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const people = usePeople('maintenance', organizationId)
  const assetList = useAssets(
    {
      per_page: 30,
      ...(organizationId ? { 'filter[organization]': organizationId } : {}),
      ...(debounced ? { search: debounced } : {}),
    },
    organizationId !== null,
  )
  const known = new Map((plan?.assets ?? []).map((a) => [String(a.id), `${a.inventory_number} — ${a.name}`]))
  ;(assetList.data?.data ?? []).forEach((a) => known.set(String(a.id), `${a.inventory_number} — ${a.name}`))
  const save = useMaintenanceMutation(() =>
    m.savePlan(
      {
        ...(plan ? {} : { organization_id: organizationId }),
        title: title.trim(),
        description: description.trim() || null,
        checklist: steps.map((s) => s.trim()).filter(Boolean),
        interval_count: Number(count),
        interval_unit: unit,
        start_on: start,
        lead_days: Number(lead),
        is_active: active,
        assignee_id: assignee ? Number(assignee) : null,
        asset_ids: assets.map(Number),
      },
      plan?.id,
    ),
  )

  const submit = () => {
    const local: Record<string, string> = {}
    if (!title.trim()) local.title = t('validation:required')
    if (!plan && !organizationId) local.organization_id = t('validation:required')
    if (!start) local.start_on = t('validation:required')
    if (assets.length === 0) local.asset_ids = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: () => {
        notifications.show({ color: 'green', message: t('common:saved') })
        onClose()
      },
      onError: (e) => {
        const error = toApiError(e)
        const fields = Object.fromEntries(
          Object.entries(error.fieldErrors).map(([k, v]) => [k.split('.')[0] ?? k, v[0] ?? '']),
        )
        if (Object.keys(fields).length > 0) setErrors(fields)
        else notifications.show({ color: 'red', message: error.message })
      },
    })
  }

  return (
    <Modal opened onClose={onClose} title={plan ? t('plans.edit') : t('plans.new')} centered size="lg">
      <Stack>
        {!plan && (
          <OrganizationSelect
            label={t('fields.organization')}
            value={organizationId}
            onChange={(v) => {
              setOrganizationId(v)
              setAssignee(null)
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
        <Textarea
          label={t('fields.description')}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
          autosize
          minRows={2}
        />
        <MultiSelect
          label={t('fields.assets')}
          searchable
          required
          disabled={!organizationId}
          data={[...known.entries()].map(([value, label]) => ({ value, label }))}
          value={assets}
          onChange={setAssets}
          searchValue={assetSearch}
          onSearchChange={setAssetSearch}
          filter={({ options }) => options}
          error={errors.asset_ids}
        />
        <Group grow align="flex-start">
          <NumberInput
            label={t('fields.every')}
            min={1}
            max={120}
            allowDecimal={false}
            value={count}
            onChange={setCount}
            error={errors.interval_count}
          />
          <Select
            label={t('fields.unit')}
            data={MAINTENANCE_UNITS.map((v) => ({ value: v, label: t(`unit.${v}`) }))}
            value={unit}
            onChange={(v) => v && setUnit(v as MaintenanceUnit)}
            allowDeselect={false}
          />
          <DateInput
            label={t('fields.startOn')}
            value={start}
            onChange={setStart}
            valueFormat="DD.MM.YYYY"
            error={errors.start_on}
            required
          />
        </Group>
        <Group grow align="flex-start">
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
          <NumberInput
            label={t('fields.leadDays')}
            min={0}
            max={90}
            allowDecimal={false}
            value={lead}
            onChange={setLead}
          />
        </Group>
        <Stack gap={6}>
          <Text size="sm" fw={500}>
            {t('fields.checklist')}
          </Text>
          {steps.map((step, i) => (
            <Group key={i} gap="xs" wrap="nowrap">
              <TextInput
                aria-label={t('plans.step', { n: i + 1 })}
                placeholder={t('plans.stepPlaceholder')}
                value={step}
                onChange={(e) => setSteps(steps.map((s, n) => (n === i ? e.currentTarget.value : s)))}
                style={{ flex: 1 }}
                maxLength={255}
              />
              <ActionIcon
                variant="subtle"
                color="red"
                aria-label={t('plans.removeStep')}
                onClick={() => setSteps(steps.filter((_, n) => n !== i))}
              >
                <IconX size={14} />
              </ActionIcon>
            </Group>
          ))}
          <Button
            variant="subtle"
            size="xs"
            leftSection={<IconPlus size={14} />}
            onClick={() => setSteps([...steps, ''])}
            w="fit-content"
          >
            {t('plans.addStep')}
          </Button>
        </Stack>
        {plan && (
          <Switch
            label={t('fields.active')}
            checked={active}
            onChange={(e) => setActive(e.currentTarget.checked)}
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
