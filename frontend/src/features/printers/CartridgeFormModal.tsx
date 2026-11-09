import {
  Button,
  Group,
  Modal,
  MultiSelect,
  NumberInput,
  Select,
  Stack,
  Switch,
  TextInput,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { useMaterials } from '@/features/warehouse/queries'
import { useCan } from '@/hooks/usePermissions'
import * as p from './api'
import { usePrinterModels, usePrinterMutation } from './queries'
import {
  CARTRIDGE_COLORS,
  CARTRIDGE_KINDS,
  type Cartridge,
  type CartridgeColor,
  type CartridgeKind,
} from './types'

/**
 * Cartridge model with its printer models. Without a chosen material the server creates one in the warehouse
 * catalog, so stock and issues of the cartridge are ordinary warehouse operations.
 */
export function CartridgeFormModal({ cartridge, onClose }: { cartridge?: Cartridge; onClose: () => void }) {
  const { t } = useTranslation(['printers', 'common', 'validation'])
  const canPickMaterial = useCan('warehouse.view')
  const [name, setName] = useState(cartridge?.name ?? '')
  const [partNumber, setPartNumber] = useState(cartridge?.part_number ?? '')
  const [kind, setKind] = useState<CartridgeKind>(cartridge?.kind ?? 'toner')
  const [color, setColor] = useState<CartridgeColor>(cartridge?.color ?? 'black')
  const [pageYield, setPageYield] = useState<number | string>(cartridge?.page_yield ?? '')
  const [active, setActive] = useState(cartridge?.is_active ?? true)
  const [materialId, setMaterialId] = useState<string | null>(
    cartridge?.material ? String(cartridge.material.id) : null,
  )
  const [models, setModels] = useState<string[]>(cartridge?.printer_models.map((m) => String(m.id)) ?? [])
  const [materialSearch, setMaterialSearch] = useState('')
  const [debounced] = useDebouncedValue(materialSearch, 300)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const printerModels = usePrinterModels()
  const materials = useMaterials(
    { per_page: 50, 'filter[active]': 1, ...(debounced ? { search: debounced } : {}) },
    canPickMaterial,
  )
  const materialOptions = [
    ...(cartridge?.material && !materials.data?.data.some((m) => m.id === cartridge.material?.id)
      ? [
          {
            value: String(cartridge.material.id),
            label: `${cartridge.material.code} — ${cartridge.material.name}`,
          },
        ]
      : []),
    ...(materials.data?.data ?? []).map((m) => ({ value: String(m.id), label: `${m.code} — ${m.name}` })),
  ]
  const save = usePrinterMutation(() =>
    p.saveCartridge(
      {
        name: name.trim(),
        part_number: partNumber.trim() || null,
        kind,
        color,
        page_yield: pageYield === '' ? null : Number(pageYield),
        is_active: active,
        material_id: materialId ? Number(materialId) : null,
        printer_model_ids: models.map(Number),
      },
      cartridge?.id,
    ),
  )

  const submit = () => {
    if (!name.trim()) {
      setErrors({ name: t('validation:required') })
      return
    }
    save.mutate(undefined, {
      onSuccess: () => {
        notifications.show({ color: 'green', message: t('common:saved') })
        onClose()
      },
      onError: (e) => {
        const error = toApiError(e)
        const fields = Object.fromEntries(
          Object.entries(error.fieldErrors).map(([k, v]) => [
            k.startsWith('printer_model_ids') ? 'printer_model_ids' : k,
            v[0] ?? '',
          ]),
        )
        if (Object.keys(fields).length > 0) setErrors(fields)
        else notifications.show({ color: 'red', message: error.message })
      },
    })
  }

  return (
    <Modal
      opened
      onClose={onClose}
      title={cartridge ? t('catalog.edit') : t('catalog.add')}
      centered
      size="lg"
    >
      <Stack>
        <Group grow align="flex-start">
          <TextInput
            label={t('fields.name')}
            value={name}
            onChange={(e) => setName(e.currentTarget.value)}
            error={errors.name}
            required
            data-autofocus
            maxLength={150}
          />
          <TextInput
            label={t('fields.partNumber')}
            value={partNumber}
            onChange={(e) => setPartNumber(e.currentTarget.value)}
            error={errors.part_number}
            maxLength={100}
          />
        </Group>
        <Group grow align="flex-start">
          <Select
            label={t('fields.kind')}
            data={CARTRIDGE_KINDS.map((v) => ({ value: v, label: t(`kind.${v}`) }))}
            value={kind}
            onChange={(v) => v && setKind(v as CartridgeKind)}
            allowDeselect={false}
            error={errors.kind}
          />
          <Select
            label={t('fields.color')}
            data={CARTRIDGE_COLORS.map((v) => ({ value: v, label: t(`color.${v}`) }))}
            value={color}
            onChange={(v) => v && setColor(v as CartridgeColor)}
            allowDeselect={false}
            error={errors.color}
          />
          <NumberInput
            label={t('fields.pageYield')}
            min={1}
            allowDecimal={false}
            thousandSeparator=" "
            value={pageYield}
            onChange={setPageYield}
            error={errors.page_yield}
          />
        </Group>
        <MultiSelect
          label={t('fields.printerModels')}
          description={t('catalog.modelsHint')}
          searchable
          data={(printerModels.data ?? []).map((m) => ({
            value: String(m.id),
            label: [m.manufacturer, m.name].filter(Boolean).join(' '),
          }))}
          value={models}
          onChange={setModels}
          error={errors.printer_model_ids}
          nothingFoundMessage={t('catalog.noModels')}
        />
        {canPickMaterial && (
          <Select
            label={t('fields.material')}
            description={t('catalog.materialHint')}
            placeholder={t('catalog.newMaterial')}
            searchable
            clearable
            data={materialOptions}
            value={materialId}
            onChange={setMaterialId}
            searchValue={materialSearch}
            onSearchChange={setMaterialSearch}
            error={errors.material_id}
            filter={({ options }) => options}
          />
        )}
        {cartridge && (
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
