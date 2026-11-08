import {
  ActionIcon,
  Button,
  Drawer,
  Group,
  NumberInput,
  Paper,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Textarea,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useAssetCategories } from '@/features/assets/queries'
import { useMe } from '@/features/auth/queries'
import { MaterialSelect } from '@/features/warehouse/Pickers'
import { useUnits } from '@/features/warehouse/queries'
import { createRequisition, updateRequisition } from './api'
import { useRequisitionMutation } from './queries'
import { ITEM_KINDS, type ItemKind, type Requisition } from './types'

interface ItemDraft {
  key: number
  kind: ItemKind
  material: { id: number; code: string; name: string } | null
  asset_category_id: number | null
  name: string
  quantity: string
  unit_id: number | null
  note: string
}

let nextKey = 1
const blank = (): ItemDraft => ({
  key: nextKey++,
  kind: 'consumable',
  material: null,
  asset_category_id: null,
  name: '',
  quantity: '1',
  unit_id: null,
  note: '',
})
const STOCK_KINDS: ItemKind[] = ['consumable', 'network']
const ASSET_KINDS: ItemKind[] = ['equipment', 'network']

/** Create a draft, or edit one (requester only, while it is a draft). */
export function RequisitionFormDrawer({
  requisition,
  onClose,
}: {
  requisition?: Requisition
  onClose: (saved?: Requisition) => void
}) {
  const { t } = useTranslation(['requisitions', 'common', 'validation'])
  const { data: me } = useMe()
  const units = useUnits()
  const categories = useAssetCategories()
  const [organizationId, setOrganizationId] = useState<number | null>(
    requisition?.organization.id ?? me?.organization?.id ?? null,
  )
  const [title, setTitle] = useState(requisition?.title ?? '')
  const [description, setDescription] = useState(requisition?.description ?? '')
  const [neededBy, setNeededBy] = useState<string | null>(requisition?.needed_by ?? null)
  const [recipientId, setRecipientId] = useState<number | null>(requisition?.recipient_employee?.id ?? null)
  const [items, setItems] = useState<ItemDraft[]>(
    requisition?.items.map((i) => ({
      key: nextKey++,
      kind: i.kind,
      material: i.material,
      asset_category_id: i.asset_category?.id ?? null,
      name: i.name,
      quantity: i.quantity,
      unit_id: i.unit?.id ?? null,
      note: i.note ?? '',
    })) ?? [blank()],
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useRequisitionMutation(() => {
    const body = {
      title,
      description: description || null,
      needed_by: neededBy,
      recipient_employee_id: recipientId,
      items: items.map((i) => ({
        kind: i.kind,
        material_id: STOCK_KINDS.includes(i.kind) ? (i.material?.id ?? null) : null,
        asset_category_id: ASSET_KINDS.includes(i.kind) ? i.asset_category_id : null,
        name: i.name || i.material?.name || '',
        quantity: i.quantity,
        unit_id: i.unit_id,
        note: i.note || null,
      })),
    }
    return requisition
      ? updateRequisition(requisition.id, body)
      : createRequisition({ ...body, organization_id: organizationId ?? 0 })
  })
  const setItem = (key: number, patch: Partial<ItemDraft>) =>
    setItems((list) => list.map((i) => (i.key === key ? { ...i, ...patch } : i)))

  const submit = () => {
    const local: Record<string, string> = {}
    if (!organizationId) local.organization_id = t('validation:required')
    if (!title.trim()) local.title = t('validation:required')
    items.forEach((i, n) => {
      if (!i.name.trim() && !i.material) local[`items.${n}.name`] = t('validation:required')
      if (!i.quantity || Number(i.quantity) <= 0) local[`items.${n}.quantity`] = t('validation:required')
    })
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => onClose(saved),
      onError: (e) => {
        const error = toApiError(e)
        setErrors(
          Object.keys(error.fieldErrors).length > 0
            ? Object.fromEntries(Object.entries(error.fieldErrors).map(([k, v]) => [k, v[0] ?? '']))
            : { form: error.message },
        )
      },
    })
  }

  return (
    <Drawer
      opened
      onClose={() => onClose()}
      position="right"
      size="xl"
      title={requisition ? t('actions.edit') : t('actions.create')}
    >
      <Stack>
        {!requisition && (
          <OrganizationSelect
            label={t('fields.organization')}
            required
            onlyActive
            value={organizationId}
            onChange={setOrganizationId}
            error={errors.organization_id}
          />
        )}
        <TextInput
          label={t('fields.title')}
          required
          value={title}
          onChange={(e) => setTitle(e.currentTarget.value)}
          error={errors.title}
        />
        <Textarea
          label={t('fields.description')}
          description={t('fields.descriptionHint')}
          autosize
          minRows={3}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
          error={errors.description}
        />
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <DateInput
            label={t('fields.neededBy')}
            value={neededBy}
            onChange={setNeededBy}
            valueFormat="DD.MM.YYYY"
            minDate={new Date()}
            clearable
            error={errors.needed_by}
          />
          <EmployeeSelect
            label={t('fields.recipient')}
            clearable
            organizationId={organizationId}
            value={recipientId}
            onChange={setRecipientId}
            error={errors.recipient_employee_id}
          />
        </SimpleGrid>

        <Text fw={600} size="sm">
          {t('items.title')}
        </Text>
        {errors.items && (
          <Text size="sm" c="red">
            {errors.items}
          </Text>
        )}
        {items.map((item, n) => (
          <Paper key={item.key} withBorder p="sm">
            <Group align="flex-start" wrap="nowrap" gap="sm">
              <Stack gap="xs" style={{ flex: 1 }}>
                <SimpleGrid cols={{ base: 1, sm: 2 }}>
                  <Select
                    label={t('items.kind')}
                    required
                    data={ITEM_KINDS.map((k) => ({ value: k, label: t(`kinds.${k}`) }))}
                    value={item.kind}
                    onChange={(v) => v && setItem(item.key, { kind: v as ItemKind })}
                    allowDeselect={false}
                  />
                  {STOCK_KINDS.includes(item.kind) && (
                    <MaterialSelect
                      label={t('items.material')}
                      description={t('items.materialHint')}
                      value={item.material?.id ?? null}
                      initial={item.material}
                      onChange={(m) =>
                        setItem(item.key, {
                          material: m,
                          name: item.name || m?.name || '',
                          unit_id: item.unit_id ?? m?.unit.id ?? null,
                        })
                      }
                    />
                  )}
                  {item.kind === 'equipment' && (
                    <Select
                      label={t('items.assetCategory')}
                      clearable
                      data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
                      value={item.asset_category_id ? String(item.asset_category_id) : null}
                      onChange={(v) => setItem(item.key, { asset_category_id: v ? Number(v) : null })}
                    />
                  )}
                </SimpleGrid>
                <TextInput
                  label={t('items.name')}
                  required={!item.material}
                  value={item.name}
                  onChange={(e) => setItem(item.key, { name: e.currentTarget.value })}
                  error={errors[`items.${n}.name`]}
                />
                <SimpleGrid cols={{ base: 1, sm: 3 }}>
                  <NumberInput
                    label={t('items.quantity')}
                    required
                    min={0}
                    decimalScale={3}
                    value={item.quantity}
                    onChange={(v) => setItem(item.key, { quantity: String(v) })}
                    error={errors[`items.${n}.quantity`]}
                  />
                  <Select
                    label={t('items.unit')}
                    clearable
                    data={(units.data ?? []).map((u) => ({ value: String(u.id), label: u.label }))}
                    value={item.unit_id ? String(item.unit_id) : null}
                    onChange={(v) => setItem(item.key, { unit_id: v ? Number(v) : null })}
                  />
                  <TextInput
                    label={t('items.note')}
                    value={item.note}
                    onChange={(e) => setItem(item.key, { note: e.currentTarget.value })}
                  />
                </SimpleGrid>
              </Stack>
              <ActionIcon
                variant="subtle"
                color="red"
                mt={26}
                aria-label={`${t('items.remove')} ${n + 1}`}
                disabled={items.length === 1}
                onClick={() => setItems((list) => list.filter((i) => i.key !== item.key))}
              >
                <IconTrash size={16} />
              </ActionIcon>
            </Group>
          </Paper>
        ))}
        <Group>
          <Button
            variant="subtle"
            leftSection={<IconPlus size={16} />}
            onClick={() => setItems((list) => [...list, blank()])}
          >
            {t('items.add')}
          </Button>
        </Group>
        {errors.form && (
          <Text size="sm" c="red">
            {errors.form}
          </Text>
        )}
        <Group justify="flex-end">
          <Button variant="default" onClick={() => onClose()}>
            {t('common:actions.cancel')}
          </Button>
          <Button loading={save.isPending} onClick={submit}>
            {requisition ? t('common:actions.save') : t('actions.saveDraft')}
          </Button>
        </Group>
      </Stack>
    </Drawer>
  )
}
