import {
  ActionIcon,
  Alert,
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
import { useDebouncedValue } from '@mantine/hooks'
import { IconAlertTriangle, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useAssets } from '@/features/assets/queries'
import { MaterialSelect, WarehouseSelect } from './Pickers'
import { postDocument } from './api'
import { useAvailability, useDocuments, useWarehouseMutation } from './queries'
import { qty } from './WarehouseBadges'
import type { Material, Shortage, StockDocumentDetail, StockDocumentType } from './types'

interface Line {
  key: number
  material:
    (Pick<Material, 'id' | 'code' | 'name' | 'track_batches' | 'track_expiry'> & { unit: string }) | null
  quantity: string
  batch: string | null
  expires_at: string | null
  unit_cost: string
}

const DECREASES: StockDocumentType[] = ['issue', 'transfer']

let nextKey = 1
const emptyLine = (): Line => ({
  key: nextKey++,
  material: null,
  quantity: '',
  batch: null,
  expires_at: null,
  unit_cost: '',
})

/** Batch picker for a decreasing line: a lot with stock, or automatic FIFO. */
function LotField({
  warehouseId,
  line,
  onChange,
  error,
}: {
  warehouseId: number | null
  line: Line
  onChange: (batch: string | null) => void
  error?: string
}) {
  const { t } = useTranslation('warehouse')
  const availability = useAvailability(warehouseId, line.material?.id ?? null)
  const lots = availability.data?.lots ?? []
  const lotLabel = (l: (typeof lots)[number]) =>
    `${l.batch ?? t('lines.noBatch')} — ${qty(l.quantity)}${l.expires_at ? ` (${l.expires_at})` : ''}`

  return (
    <Stack gap={2}>
      {line.material?.track_batches && (
        <Select
          label={t('lines.batch')}
          placeholder={t('lines.fifo')}
          clearable
          data={lots.map((l) => ({ value: l.batch ?? '', label: lotLabel(l) }))}
          value={line.batch}
          onChange={onChange}
          error={error}
        />
      )}
      {availability.data && (
        <Text size="xs" c={Number(availability.data.total) < Number(line.quantity || 0) ? 'red' : 'dimmed'}>
          {t('lines.available', { quantity: qty(availability.data.total), unit: line.material?.unit ?? '' })}
        </Text>
      )}
    </Stack>
  )
}

function IssuePicker({
  value,
  onChange,
  error,
}: {
  value: number | null
  onChange: (id: number | null) => void
  error?: string
}) {
  const { t } = useTranslation('warehouse')
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const list = useDocuments({
    'filter[type]': 'issue',
    per_page: 20,
    ...(debounced ? { search: debounced } : {}),
  })
  return (
    <Select
      label={t('fields.relatedIssue')}
      searchable
      clearable
      filter={({ options }) => options}
      searchValue={search}
      onSearchChange={setSearch}
      data={(list.data?.data ?? []).map((d) => ({
        value: String(d.id),
        label: `${d.number} — ${d.warehouse.name}`,
      }))}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      error={error}
    />
  )
}

function DeviceSelect({
  organizationId,
  value,
  onChange,
  error,
}: {
  organizationId: number | null
  value: number | null
  onChange: (id: number | null) => void
  error?: string
}) {
  const { t } = useTranslation('warehouse')
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const params: Record<string, string | number> = { per_page: 20 }
  if (debounced.length >= 2) params.search = debounced
  if (organizationId) params['filter[organization]'] = organizationId
  const assets = useAssets(params, debounced.length >= 2 || value !== null)
  return (
    <Select
      label={t('fields.device')}
      description={t('fields.deviceHint')}
      searchable
      clearable
      filter={({ options }) => options}
      searchValue={search}
      onSearchChange={setSearch}
      data={(assets.data?.data ?? []).map((a) => ({
        value: String(a.id),
        label: `${a.inventory_number} — ${a.name}`,
      }))}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      error={error}
    />
  )
}

export function StockDocumentDrawer({
  type,
  defaults,
  onClose,
}: {
  type: StockDocumentType
  defaults?: { warehouse_id?: number; related_document_id?: number }
  onClose: (document?: StockDocumentDetail) => void
}) {
  const { t } = useTranslation(['warehouse', 'common', 'validation'])
  const [warehouseId, setWarehouseId] = useState<number | null>(defaults?.warehouse_id ?? null)
  const [targetId, setTargetId] = useState<number | null>(null)
  const [date, setDate] = useState<string | null>(null)
  const [supplier, setSupplier] = useState('')
  const [supplierDocument, setSupplierDocument] = useState('')
  const [recipientOrg, setRecipientOrg] = useState<number | null>(null)
  const [employeeId, setEmployeeId] = useState<number | null>(null)
  const [assetId, setAssetId] = useState<number | null>(null)
  const [relatedId, setRelatedId] = useState<number | null>(defaults?.related_document_id ?? null)
  const [reason, setReason] = useState('')
  const [lines, setLines] = useState<Line[]>([emptyLine()])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [shortages, setShortages] = useState<Shortage[]>([])
  const decreasing = DECREASES.includes(type)
  const increasing = type === 'receipt' || type === 'return'
  const mutation = useWarehouseMutation(postDocument)

  const setLine = (key: number, patch: Partial<Line>) =>
    setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  const submit = () => {
    const local: Record<string, string> = {}
    if (!warehouseId) local.warehouse_id = t('validation:required')
    if (type === 'transfer' && !targetId) local.target_warehouse_id = t('validation:required')
    if ((type === 'issue' || type === 'adjustment') && !reason.trim()) local.reason = t('validation:required')
    // Every line is sent, so server errors (lines.N.*) point at the line the user sees.
    lines.forEach((l, i) => {
      if (!l.material) local[`lines.${i}.material_id`] = t('validation:required')
      if (!l.quantity || Number(l.quantity) === 0) local[`lines.${i}.quantity`] = t('validation:required')
    })
    setErrors(local)
    setShortages([])
    if (Object.keys(local).length > 0 || !warehouseId) return

    mutation.mutate(
      {
        type,
        warehouse_id: warehouseId,
        target_warehouse_id: type === 'transfer' ? targetId : null,
        document_date: date,
        supplier: type === 'receipt' ? supplier || null : null,
        supplier_document: type === 'receipt' ? supplierDocument || null : null,
        recipient_organization_id: type === 'issue' ? recipientOrg : null,
        recipient_employee_id: type === 'issue' || type === 'return' ? employeeId : null,
        asset_id: type === 'issue' ? assetId : null,
        related_document_id: type === 'return' ? relatedId : null,
        reason: reason || null,
        lines: lines.map((l) => ({
          material_id: l.material?.id ?? 0,
          quantity: l.quantity,
          batch: l.batch || null,
          expires_at: l.expires_at,
          unit_cost: increasing && l.unit_cost !== '' ? l.unit_cost : null,
        })),
      },
      {
        onSuccess: (doc) => onClose(doc),
        onError: (e) => {
          const error = toApiError(e)
          if (error.code === 'insufficient_stock') {
            setShortages((error.details.shortages as Shortage[] | undefined) ?? [])
            return
          }
          if (error.status === 422) {
            setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])))
          } else {
            setErrors({ form: error.message })
          }
        },
      },
    )
  }

  return (
    <Drawer opened onClose={() => onClose()} position="right" size="xl" title={t(`new.${type}`)}>
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <WarehouseSelect
            label={
              type === 'receipt' || type === 'return' ? t('fields.toWarehouse') : t('fields.fromWarehouse')
            }
            required
            value={warehouseId}
            onChange={setWarehouseId}
            error={errors.warehouse_id}
          />
          {type === 'transfer' ? (
            <WarehouseSelect
              label={t('fields.targetWarehouse')}
              required
              excludeId={warehouseId}
              value={targetId}
              onChange={setTargetId}
              error={errors.target_warehouse_id}
            />
          ) : (
            <DateInput
              label={t('fields.date')}
              placeholder={t('fields.today')}
              value={date}
              onChange={setDate}
              valueFormat="DD.MM.YYYY"
              maxDate={new Date()}
              clearable
              error={errors.document_date}
            />
          )}
        </SimpleGrid>
        {type === 'receipt' && (
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput
              label={t('fields.supplier')}
              value={supplier}
              onChange={(e) => setSupplier(e.currentTarget.value)}
              error={errors.supplier}
            />
            <TextInput
              label={t('fields.supplierDocument')}
              value={supplierDocument}
              onChange={(e) => setSupplierDocument(e.currentTarget.value)}
              error={errors.supplier_document}
            />
          </SimpleGrid>
        )}
        {type === 'issue' && (
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <OrganizationSelect
              label={t('fields.recipientOrganization')}
              description={t('fields.recipientOrganizationHint')}
              onlyActive
              clearable
              value={recipientOrg}
              onChange={(v) => {
                setRecipientOrg(v)
                setEmployeeId(null)
                setAssetId(null)
              }}
              error={errors.recipient_organization_id}
            />
            <EmployeeSelect
              label={t('fields.recipientEmployee')}
              clearable
              organizationId={recipientOrg}
              value={employeeId}
              onChange={setEmployeeId}
              error={errors.recipient_employee_id}
            />
            <DeviceSelect
              organizationId={recipientOrg}
              value={assetId}
              onChange={setAssetId}
              error={errors.asset_id}
            />
          </SimpleGrid>
        )}
        {type === 'return' && (
          <IssuePicker value={relatedId} onChange={setRelatedId} error={errors.related_document_id} />
        )}
        <Textarea
          label={t('fields.reason')}
          required={type === 'issue' || type === 'adjustment'}
          autosize
          minRows={2}
          value={reason}
          onChange={(e) => setReason(e.currentTarget.value)}
          error={errors.reason}
        />

        <Text fw={600} size="sm">
          {t('lines.title')}
        </Text>
        {errors.lines && (
          <Text size="sm" c="red">
            {errors.lines}
          </Text>
        )}
        {lines.map((line, i) => (
          <Paper key={line.key} withBorder p="sm">
            <Group align="flex-start" wrap="nowrap" gap="sm">
              <Stack gap="xs" style={{ flex: 1 }}>
                <MaterialSelect
                  label={t('lines.material')}
                  required
                  value={line.material?.id ?? null}
                  initial={line.material}
                  onChange={(m) =>
                    setLine(line.key, {
                      material: m
                        ? {
                            id: m.id,
                            code: m.code,
                            name: m.name,
                            unit: m.unit.name,
                            track_batches: m.track_batches,
                            track_expiry: m.track_expiry,
                          }
                        : null,
                      batch: null,
                      expires_at: null,
                    })
                  }
                  error={errors[`lines.${i}.material_id`]}
                />
                <SimpleGrid cols={{ base: 1, sm: 3 }}>
                  <NumberInput
                    label={`${t('lines.quantity')}${line.material ? `, ${line.material.unit}` : ''}`}
                    required
                    decimalScale={3}
                    allowNegative={type === 'adjustment'}
                    description={type === 'adjustment' ? t('lines.signedHint') : undefined}
                    value={line.quantity}
                    onChange={(v) => setLine(line.key, { quantity: String(v) })}
                    error={errors[`lines.${i}.quantity`]}
                  />
                  {increasing && (
                    <NumberInput
                      label={t('lines.unitCost')}
                      min={0}
                      decimalScale={2}
                      thousandSeparator=" "
                      value={line.unit_cost}
                      onChange={(v) => setLine(line.key, { unit_cost: String(v) })}
                      error={errors[`lines.${i}.unit_cost`]}
                    />
                  )}
                  {(increasing || type === 'adjustment') && line.material?.track_batches && (
                    <TextInput
                      label={t('lines.batch')}
                      required={increasing || Number(line.quantity) > 0}
                      value={line.batch ?? ''}
                      onChange={(e) => setLine(line.key, { batch: e.currentTarget.value })}
                      error={errors[`lines.${i}.batch`]}
                    />
                  )}
                  {(increasing || type === 'adjustment') && line.material?.track_batches && (
                    <DateInput
                      label={t('lines.expiresAt')}
                      required={type === 'receipt' && line.material.track_expiry}
                      value={line.expires_at}
                      onChange={(v) => setLine(line.key, { expires_at: v })}
                      valueFormat="DD.MM.YYYY"
                      clearable
                      error={errors[`lines.${i}.expires_at`]}
                    />
                  )}
                  {(decreasing || (type === 'adjustment' && Number(line.quantity) < 0)) && line.material && (
                    <LotField
                      warehouseId={warehouseId}
                      line={line}
                      onChange={(batch) => setLine(line.key, { batch })}
                      error={errors[`lines.${i}.batch`]}
                    />
                  )}
                </SimpleGrid>
              </Stack>
              <ActionIcon
                variant="subtle"
                color="red"
                mt={26}
                aria-label={`${t('lines.remove')} ${i + 1}`}
                disabled={lines.length === 1}
                onClick={() => setLines((ls) => ls.filter((l) => l.key !== line.key))}
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
            onClick={() => setLines((ls) => [...ls, emptyLine()])}
          >
            {t('lines.add')}
          </Button>
        </Group>

        {shortages.length > 0 && (
          <Alert color="red" icon={<IconAlertTriangle size={18} />} title={t('errors.insufficient')}>
            {shortages.map((s) => (
              <Text key={`${s.material_id}-${s.batch ?? ''}`} size="sm">
                {t('errors.shortage', {
                  material: s.material,
                  batch: s.batch ?? '—',
                  available: qty(s.available),
                  requested: qty(s.requested),
                })}
              </Text>
            ))}
          </Alert>
        )}
        {errors.form && (
          <Text size="sm" c="red">
            {errors.form}
          </Text>
        )}
        <Group justify="flex-end">
          <Button variant="default" onClick={() => onClose()}>
            {t('common:actions.cancel')}
          </Button>
          <Button loading={mutation.isPending} onClick={submit}>
            {t('actions.post')}
          </Button>
        </Group>
      </Stack>
    </Drawer>
  )
}
