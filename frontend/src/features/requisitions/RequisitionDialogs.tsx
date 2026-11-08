import {
  Button,
  Group,
  Modal,
  NumberInput,
  SegmentedControl,
  Select,
  Stack,
  Table,
  Text,
  Textarea,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { useAssets } from '@/features/assets/queries'
import { MaterialSelect, WarehouseSelect } from '@/features/warehouse/Pickers'
import { qty } from '@/features/warehouse/WarehouseBadges'
import { allocate, centralDecision, orgDecision } from './api'
import { useRequisitionMutation } from './queries'
import type { Decision, FulfillmentType, Requisition, RequisitionItem } from './types'

const fieldErrors = (e: unknown) =>
  Object.fromEntries(Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']))

/** A step that needs (or accepts) a comment: information request/answer, finishing, receipt. */
export function CommentDialog({
  title,
  label,
  confirmLabel,
  required,
  run,
  onClose,
}: {
  title: string
  label: string
  confirmLabel: string
  required: boolean
  run: (comment: string) => Promise<unknown>
  onClose: () => void
}) {
  const { t } = useTranslation(['requisitions', 'validation'])
  const [comment, setComment] = useState('')
  const [error, setError] = useState<string | null>(null)
  const mutation = useRequisitionMutation(run)

  return (
    <Modal opened onClose={onClose} title={title} centered>
      <Stack>
        <Textarea
          label={label}
          required={required}
          autosize
          minRows={3}
          value={comment}
          onChange={(e) => setComment(e.currentTarget.value)}
          error={error}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button
            loading={mutation.isPending}
            onClick={() => {
              if (required && !comment.trim()) {
                setError(t('validation:required'))
                return
              }
              mutation.mutate(comment, {
                onSuccess: onClose,
                onError: (e) => setError(fieldErrors(e).comment ?? toApiError(e).message),
              })
            }}
          >
            {confirmLabel}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** Organization head (stage org) or central head (stage central) decision; the central head may lower quantities. */
export function DecisionDialog({
  requisition,
  stage,
  onClose,
}: {
  requisition: Requisition
  stage: 'org' | 'central'
  onClose: () => void
}) {
  const { t } = useTranslation(['requisitions', 'validation'])
  const [decision, setDecision] = useState<Decision>('approved')
  const [comment, setComment] = useState('')
  const [quantities, setQuantities] = useState<Record<number, string>>(
    Object.fromEntries(requisition.items.map((i) => [i.id, i.quantity])),
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const mutation = useRequisitionMutation(() =>
    stage === 'org'
      ? orgDecision(requisition.id, decision, comment || undefined)
      : centralDecision(
          requisition.id,
          decision,
          comment || undefined,
          decision === 'approved'
            ? requisition.items.map((i) => ({ id: i.id, approved_quantity: quantities[i.id] ?? i.quantity }))
            : [],
        ),
  )

  return (
    <Modal
      opened
      onClose={onClose}
      title={stage === 'org' ? t('decision.orgTitle') : t('decision.centralTitle')}
      centered
      size="lg"
    >
      <Stack>
        <SegmentedControl
          aria-label={t('decision.label')}
          value={decision}
          onChange={(v) => setDecision(v as Decision)}
          data={(['approved', 'returned', 'rejected'] as const).map((d) => ({
            value: d,
            label: t(`decision.${d}`),
          }))}
        />
        {stage === 'central' && decision === 'approved' && (
          <Table.ScrollContainer minWidth={420}>
            <Table verticalSpacing="xs">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('items.name')}</Table.Th>
                  <Table.Th ta="right">{t('items.requested')}</Table.Th>
                  <Table.Th w={140}>{t('items.approved')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {requisition.items.map((item, n) => (
                  <Table.Tr key={item.id}>
                    <Table.Td>{item.name}</Table.Td>
                    <Table.Td ta="right" ff="monospace">
                      {qty(item.quantity)}
                    </Table.Td>
                    <Table.Td>
                      <NumberInput
                        aria-label={`${t('items.approved')}: ${item.name}`}
                        min={0}
                        max={Number(item.quantity)}
                        decimalScale={3}
                        hideControls
                        value={quantities[item.id] ?? ''}
                        onChange={(v) => setQuantities((q) => ({ ...q, [item.id]: String(v) }))}
                        error={errors[`items.${n}.approved_quantity`]}
                      />
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
        <Textarea
          label={decision === 'approved' ? t('decision.comment') : t('decision.reason')}
          required={decision !== 'approved'}
          autosize
          minRows={2}
          value={comment}
          onChange={(e) => setComment(e.currentTarget.value)}
          error={errors.comment}
        />
        {errors.items && (
          <Text size="sm" c="red">
            {errors.items}
          </Text>
        )}
        <Group justify="flex-end">
          <Button
            color={decision === 'rejected' ? 'red' : undefined}
            loading={mutation.isPending}
            onClick={() => {
              if (decision !== 'approved' && !comment.trim()) {
                setErrors({ comment: t('validation:required') })
                return
              }
              mutation.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => {
                  const fe = fieldErrors(e)
                  if (Object.keys(fe).length > 0) setErrors(fe)
                  else notifications.show({ color: 'red', message: toApiError(e).message })
                },
              })
            }}
          >
            {t(`decision.confirm.${decision}`)}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function AssetPicker({
  value,
  onChange,
  error,
}: {
  value: number | null
  onChange: (id: number | null) => void
  error?: string
}) {
  const { t } = useTranslation('requisitions')
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const params: Record<string, string | number> = { per_page: 20, 'filter[status]': 'in_stock,reserve' }
  if (debounced.length >= 2) params.search = debounced
  const assets = useAssets(params)
  return (
    <Select
      label={t('allocate.asset')}
      description={t('allocate.assetHint')}
      required
      searchable
      filter={({ options }) => options}
      searchValue={search}
      onSearchChange={setSearch}
      data={(assets.data?.data ?? []).map((a) => ({
        value: String(a.id),
        label: `${a.inventory_number} — ${a.name} (${a.organization?.name ?? ''})`,
      }))}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      error={error}
    />
  )
}

/** Allocates one item from stock (issue or transfer into the requester's warehouse), an asset, or a note. */
export function AllocateDialog({
  requisition,
  item,
  onClose,
}: {
  requisition: Requisition
  item: RequisitionItem
  onClose: () => void
}) {
  const { t } = useTranslation(['requisitions', 'validation'])
  const initialType: FulfillmentType = item.material ? 'stock' : item.kind === 'equipment' ? 'asset' : 'note'
  const [type, setType] = useState<FulfillmentType>(initialType)
  const [warehouseId, setWarehouseId] = useState<number | null>(null)
  const [targetId, setTargetId] = useState<number | null>(null)
  const [materialId, setMaterialId] = useState<number | null>(item.material?.id ?? null)
  const [assetId, setAssetId] = useState<number | null>(null)
  const [quantity, setQuantity] = useState<string>(item.remaining)
  const [note, setNote] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const mutation = useRequisitionMutation(() =>
    allocate(requisition.id, {
      item_id: item.id,
      type,
      quantity: type === 'asset' ? undefined : quantity,
      warehouse_id: type === 'stock' ? warehouseId : undefined,
      target_warehouse_id: type === 'stock' ? targetId : undefined,
      material_id: type === 'stock' && !item.material ? materialId : undefined,
      asset_id: type === 'asset' ? assetId : undefined,
      note: note || undefined,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={`${t('allocate.title')}: ${item.name}`} centered size="lg">
      <Stack>
        <Text size="sm" c="dimmed">
          {t('allocate.remaining', { quantity: qty(item.remaining), unit: item.unit?.name ?? '' })}
        </Text>
        <SegmentedControl
          aria-label={t('allocate.type')}
          value={type}
          onChange={(v) => setType(v as FulfillmentType)}
          data={(['stock', 'asset', 'note'] as const).map((v) => ({
            value: v,
            label: t(`allocate.types.${v}`),
          }))}
        />
        {type === 'stock' && (
          <>
            <WarehouseSelect
              label={t('allocate.warehouse')}
              required
              value={warehouseId}
              onChange={setWarehouseId}
              error={errors.warehouse_id}
            />
            {!item.material && (
              <MaterialSelect
                label={t('items.material')}
                required
                value={materialId}
                onChange={(m) => setMaterialId(m?.id ?? null)}
                error={errors.material_id}
              />
            )}
            <WarehouseSelect
              label={t('allocate.target')}
              description={t('allocate.targetHint')}
              clearable
              organizationId={requisition.organization.id}
              excludeId={warehouseId}
              value={targetId}
              onChange={setTargetId}
              error={errors.target_warehouse_id}
            />
          </>
        )}
        {type === 'asset' && <AssetPicker value={assetId} onChange={setAssetId} error={errors.asset_id} />}
        {type !== 'asset' && (
          <NumberInput
            label={t('allocate.quantity')}
            required
            min={0}
            max={Number(item.remaining)}
            decimalScale={3}
            value={quantity}
            onChange={(v) => setQuantity(String(v))}
            error={errors.quantity}
          />
        )}
        <Textarea
          label={type === 'note' ? t('allocate.noteRequired') : t('allocate.note')}
          required={type === 'note'}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
          error={errors.note}
        />
        <Group justify="flex-end">
          <Button
            loading={mutation.isPending}
            onClick={() => {
              const local: Record<string, string> = {}
              if (type === 'stock' && !warehouseId) local.warehouse_id = t('validation:required')
              if (type === 'asset' && !assetId) local.asset_id = t('validation:required')
              if (type === 'note' && !note.trim()) local.note = t('validation:required')
              setErrors(local)
              if (Object.keys(local).length > 0) return
              mutation.mutate(undefined, {
                onSuccess: onClose,
                onError: (e) => {
                  const fe = fieldErrors(e)
                  if (Object.keys(fe).length > 0) setErrors(fe)
                  else notifications.show({ color: 'red', message: toApiError(e).message })
                },
              })
            }}
          >
            {t('allocate.confirm')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
