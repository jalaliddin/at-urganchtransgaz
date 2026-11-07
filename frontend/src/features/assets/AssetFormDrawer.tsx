import {
  Alert,
  Button,
  Drawer,
  Group,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  TextInput,
  Title,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { DepartmentSelect } from '@/components/DepartmentSelect'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useMe } from '@/features/auth/queries'
import { MONO_INPUT_STYLES } from '@/lib/ui'
import { createAsset, updateAsset, type AssetInput } from './api'
import {
  useAssetCategories,
  useAssetModels,
  useAssetMutation,
  useLocations,
  useManufacturers,
} from './queries'
import type { Asset } from './types'

const IP = /^((25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(25[0-5]|2[0-4]\d|1?\d?\d)$|^[0-9a-fA-F:]+$/
const MAC = /^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$/

type Form = Omit<AssetInput, 'cost' | 'organization_id' | 'category_id'> & {
  organization_id: number | null
  category_id: number | null
  cost: number | string
}

function initial(asset: Asset | undefined, organizationId: number | null): Form {
  return {
    organization_id: asset?.organization?.id ?? organizationId,
    inventory_number: asset?.inventory_number ?? '',
    name: asset?.name ?? '',
    category_id: asset?.category?.id ?? null,
    manufacturer_id: asset?.manufacturer?.id ?? null,
    model_id: asset?.model?.id ?? null,
    department_id: asset?.department?.id ?? null,
    location_id: asset?.location?.id ?? null,
    responsible_employee_id: asset?.responsible?.id ?? null,
    serial_number: asset?.serial_number ?? '',
    hostname: asset?.hostname ?? '',
    ip_address: asset?.ip_address ?? '',
    mac_address: asset?.mac_address ?? '',
    vlan: asset?.vlan ?? '',
    subnet: asset?.subnet ?? '',
    room: asset?.room ?? '',
    purchase_date: asset?.purchase_date ?? null,
    warranty_until: asset?.warranty_until ?? null,
    cost: asset?.cost ? Number(asset.cost) : '',
    supplier: asset?.supplier ?? '',
    specs: asset?.specs ?? '',
    notes: asset?.notes ?? '',
  }
}

interface Props {
  opened: boolean
  asset?: Asset
  onClose: (saved?: Asset) => void
}

/** Create / edit the asset card. The organization is fixed after creation (changes go through transfers). */
export function AssetFormDrawer({ opened, asset, onClose }: Props) {
  const { t } = useTranslation(['assets', 'common', 'validation'])
  const { data: me } = useMe()
  const [form, setForm] = useState<Form>(() => initial(asset, me?.organization?.id ?? null))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const categories = useAssetCategories()
  const manufacturers = useManufacturers()
  const models = useAssetModels(form.category_id ? { 'filter[category]': form.category_id } : {})
  const locations = useLocations(form.organization_id)
  const save = useAssetMutation((input: AssetInput) =>
    asset ? updateAsset(asset.id, input) : createAsset(input),
  )
  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => ({ ...f, [key]: value }))
  const text = (key: keyof Form, label: string, props: Record<string, unknown> = {}) => (
    <TextInput
      label={label}
      value={(form[key] as string | null) ?? ''}
      onChange={(e) => set(key, e.currentTarget.value as never)}
      error={errors[key]}
      {...props}
    />
  )

  const submit = async () => {
    const local: Record<string, string> = {}
    if (form.name.trim() === '') local.name = t('validation:required')
    if (form.category_id === null) local.category_id = t('validation:required')
    if (!asset && form.organization_id === null) local.organization_id = t('validation:required')
    if (form.ip_address && !IP.test(form.ip_address)) local.ip_address = t('validation:ip')
    if (form.mac_address && !MAC.test(form.mac_address)) local.mac_address = t('validation:mac')
    setErrors(local)
    if (Object.keys(local).length > 0) return

    const blank = (v: unknown) => (typeof v === 'string' && v.trim() === '' ? null : v)
    const payload = Object.fromEntries(
      Object.entries({ ...form, cost: form.cost === '' ? null : String(form.cost) }).map(([k, v]) => [
        k,
        blank(v),
      ]),
    ) as unknown as AssetInput
    if (asset) delete (payload as Partial<AssetInput>).organization_id
    try {
      const saved = await save.mutateAsync(payload)
      notifications.show({ color: 'green', message: t('common:saved') })
      onClose(saved)
    } catch (error) {
      const apiError = toApiError(error)
      setErrors(
        error instanceof ApiError && error.status === 422
          ? Object.fromEntries(Object.entries(apiError.fieldErrors).map(([k, v]) => [k, v[0] ?? '']))
          : { general: apiError.message },
      )
    }
  }

  return (
    <Drawer
      opened={opened}
      onClose={() => onClose()}
      position="right"
      size="xl"
      title={t(asset ? 'actions.edit' : 'actions.create')}
    >
      <Stack gap="md" pb="xl">
        {errors.general && <Alert color="red">{errors.general}</Alert>}
        <Title order={4} fz="sm">
          {t('sections.identity')}
        </Title>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {!asset && (
            <OrganizationSelect
              label={t('fields.organization')}
              required
              onlyActive
              value={form.organization_id}
              onChange={(v) =>
                setForm((f) => ({
                  ...f,
                  organization_id: v,
                  department_id: null,
                  location_id: null,
                  responsible_employee_id: null,
                }))
              }
              error={errors.organization_id}
            />
          )}
          {text('inventory_number', t('fields.inventory_number'), {
            description: t('fields.inventoryHint'),
            styles: MONO_INPUT_STYLES,
          })}
          {text('name', t('fields.name'), { required: true })}
          <Select
            label={t('fields.category')}
            required
            searchable
            data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
            value={form.category_id ? String(form.category_id) : null}
            onChange={(v) => setForm((f) => ({ ...f, category_id: v ? Number(v) : null, model_id: null }))}
            error={errors.category_id}
          />
          <Select
            label={t('fields.manufacturer')}
            searchable
            clearable
            data={(manufacturers.data ?? []).map((m) => ({ value: String(m.id), label: m.name }))}
            value={form.manufacturer_id ? String(form.manufacturer_id) : null}
            onChange={(v) =>
              setForm((f) => ({ ...f, manufacturer_id: v ? Number(v) : null, model_id: null }))
            }
          />
          <Select
            label={t('fields.model')}
            searchable
            clearable
            data={(models.data ?? [])
              .filter((m) => !form.manufacturer_id || m.manufacturer.id === form.manufacturer_id)
              .map((m) => ({ value: String(m.id), label: `${m.manufacturer.name} ${m.name}` }))}
            value={form.model_id ? String(form.model_id) : null}
            onChange={(v) => set('model_id', v ? Number(v) : null)}
            error={errors.model_id}
          />
          {text('serial_number', t('fields.serial_number'), { styles: MONO_INPUT_STYLES })}
        </SimpleGrid>

        <Title order={4} fz="sm">
          {t('sections.network')}
        </Title>
        <SimpleGrid cols={{ base: 1, sm: 3 }}>
          {text('hostname', t('fields.hostname'), { styles: MONO_INPUT_STYLES })}
          {text('ip_address', t('fields.ip_address'), { styles: MONO_INPUT_STYLES })}
          {text('mac_address', t('fields.mac_address'), {
            styles: MONO_INPUT_STYLES,
            placeholder: 'AA:BB:CC:DD:EE:FF',
          })}
          {text('vlan', t('fields.vlan'))}
          {text('subnet', t('fields.subnet'), { styles: MONO_INPUT_STYLES })}
        </SimpleGrid>

        <Title order={4} fz="sm">
          {t('sections.placement')}
        </Title>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <DepartmentSelect
            label={t('fields.department')}
            organizationId={form.organization_id}
            value={form.department_id ?? null}
            onChange={(v) => set('department_id', v)}
            disabled={form.organization_id === null}
            error={errors.department_id}
          />
          <Select
            label={t('fields.location')}
            searchable
            clearable
            data={(locations.data ?? []).map((l) => ({ value: String(l.id), label: l.full_name }))}
            value={form.location_id ? String(form.location_id) : null}
            onChange={(v) => set('location_id', v ? Number(v) : null)}
            disabled={form.organization_id === null}
            error={errors.location_id}
          />
          {text('room', t('fields.room'))}
          <EmployeeSelect
            label={t('fields.responsible')}
            organizationId={form.organization_id}
            value={form.responsible_employee_id ?? null}
            onChange={(v) => set('responsible_employee_id', v)}
            clearable
            disabled={form.organization_id === null}
            error={errors.responsible_employee_id}
          />
        </SimpleGrid>

        <Title order={4} fz="sm">
          {t('sections.finance')}
        </Title>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <DateInput
            label={t('fields.purchase_date')}
            valueFormat="DD.MM.YYYY"
            clearable
            value={form.purchase_date ?? null}
            onChange={(v) => set('purchase_date', v)}
          />
          <DateInput
            label={t('fields.warranty_until')}
            valueFormat="DD.MM.YYYY"
            clearable
            value={form.warranty_until ?? null}
            onChange={(v) => set('warranty_until', v)}
            error={errors.warranty_until}
          />
          <NumberInput
            label={t('fields.cost')}
            min={0}
            decimalScale={2}
            thousandSeparator=" "
            value={form.cost}
            onChange={(v) => set('cost', v)}
            error={errors.cost}
          />
          {text('supplier', t('fields.supplier'))}
        </SimpleGrid>
        <Textarea
          label={t('fields.specs')}
          autosize
          minRows={2}
          value={form.specs ?? ''}
          onChange={(e) => set('specs', e.currentTarget.value)}
        />
        <Textarea
          label={t('fields.notes')}
          autosize
          minRows={2}
          value={form.notes ?? ''}
          onChange={(e) => set('notes', e.currentTarget.value)}
        />
        {Object.keys(errors).length > 0 && !errors.general && (
          <Text size="sm" c="red" role="alert">
            {t('validation:fixErrors', { defaultValue: '' })}
          </Text>
        )}
        <Group justify="flex-end">
          <Button variant="default" onClick={() => onClose()}>
            {t('common:actions.cancel')}
          </Button>
          <Button onClick={() => void submit()} loading={save.isPending}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Drawer>
  )
}
