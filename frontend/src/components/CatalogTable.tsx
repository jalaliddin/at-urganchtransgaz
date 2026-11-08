import {
  ActionIcon,
  Button,
  Card,
  Checkbox,
  Group,
  Modal,
  NumberInput,
  Select,
  Stack,
  Table,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { OrganizationSelect } from '@/components/OrganizationSelect'

export type CatalogField =
  | { key: string; label: string; kind: 'text'; required?: boolean }
  | { key: string; label: string; kind: 'number'; required?: boolean; decimals?: number }
  | { key: string; label: string; kind: 'check' }
  | {
      key: string
      label: string
      kind: 'select'
      data: { value: string; label: string }[]
      required?: boolean
    }
  | { key: string; label: string; kind: 'organization'; required?: boolean }

export type CatalogValues = Record<string, string | boolean | number | null>

export interface CatalogRow {
  id: number
  name: string
  cells: ReactNode[]
  values: CatalogValues
}

function useCatalogMutation<TArgs>(fn: (args: TArgs) => Promise<unknown>, invalidate: QueryKey[]) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: fn,
    onSuccess: () => Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey }))),
  })
}

function EditModal({
  title,
  fields,
  initial,
  save,
  invalidate,
  onClose,
}: {
  title: string
  fields: CatalogField[]
  initial: CatalogValues
  save: (values: CatalogValues) => Promise<unknown>
  invalidate: QueryKey[]
  onClose: () => void
}) {
  const { t } = useTranslation(['common', 'validation'])
  const [values, setValues] = useState<CatalogValues>(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const mutation = useCatalogMutation(save, invalidate)
  const set = (key: string, value: CatalogValues[string]) => setValues((v) => ({ ...v, [key]: value }))

  return (
    <Modal opened onClose={onClose} title={title} centered>
      <Stack>
        {fields.map((f) =>
          f.kind === 'check' ? (
            <Checkbox
              key={f.key}
              label={f.label}
              checked={Boolean(values[f.key])}
              onChange={(e) => set(f.key, e.currentTarget.checked)}
              error={errors[f.key]}
            />
          ) : f.kind === 'select' ? (
            <Select
              key={f.key}
              label={f.label}
              required={f.required}
              searchable
              clearable={!f.required}
              data={f.data}
              value={values[f.key] ? String(values[f.key]) : null}
              onChange={(v) => set(f.key, v ? Number(v) : null)}
              error={errors[f.key]}
            />
          ) : f.kind === 'organization' ? (
            <OrganizationSelect
              key={f.key}
              label={f.label}
              required={f.required}
              onlyActive
              value={(values[f.key] as number | null) ?? null}
              onChange={(v) => set(f.key, v)}
              error={errors[f.key]}
            />
          ) : f.kind === 'number' ? (
            <NumberInput
              key={f.key}
              label={f.label}
              required={f.required}
              min={0}
              decimalScale={f.decimals ?? 0}
              value={values[f.key] === null || values[f.key] === undefined ? '' : Number(values[f.key])}
              onChange={(v) => set(f.key, v === '' ? null : String(v))}
              error={errors[f.key]}
            />
          ) : (
            <TextInput
              key={f.key}
              label={f.label}
              required={f.required}
              value={(values[f.key] as string | null) ?? ''}
              onChange={(e) => set(f.key, e.currentTarget.value)}
              error={errors[f.key]}
            />
          ),
        )}
        <Group justify="flex-end">
          <Button
            loading={mutation.isPending}
            onClick={() => {
              const local = Object.fromEntries(
                fields
                  .filter(
                    (f) => 'required' in f && f.required && (values[f.key] === null || values[f.key] === ''),
                  )
                  .map((f) => [f.key, t('validation:required')]),
              )
              setErrors(local)
              if (Object.keys(local).length > 0) return
              mutation.mutate(values, {
                onSuccess: onClose,
                onError: (e) => {
                  const api = toApiError(e)
                  if (e instanceof ApiError && e.status === 422)
                    setErrors(
                      Object.fromEntries(Object.entries(api.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])),
                    )
                  else notifications.show({ color: 'red', message: api.message })
                },
              })
            }}
          >
            {t('actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

/** Reference-data table with add / edit (modal) / delete (confirmed); used by catalog settings screens. */
export function CatalogTable({
  headers,
  rows,
  fields,
  addLabel,
  save,
  remove,
  invalidate,
  deleteConfirm,
  canEdit = true,
}: {
  headers: string[]
  rows: CatalogRow[]
  fields: CatalogField[]
  addLabel: string
  save: (values: CatalogValues, id?: number) => Promise<unknown>
  remove: (id: number) => Promise<unknown>
  /** Query keys refreshed after a change. */
  invalidate: QueryKey[]
  deleteConfirm: (name: string) => string
  canEdit?: boolean
}) {
  const { t } = useTranslation('common')
  const [editing, setEditing] = useState<CatalogRow | null | undefined>(undefined)
  const del = useCatalogMutation(remove, invalidate)

  return (
    <Stack>
      {canEdit && (
        <Group justify="flex-end">
          <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>
            {addLabel}
          </Button>
        </Group>
      )}
      <Card withBorder p={0}>
        <Table.ScrollContainer minWidth={480}>
          <Table verticalSpacing="xs" highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                {headers.map((h) => (
                  <Table.Th key={h}>{h}</Table.Th>
                ))}
                {canEdit && <Table.Th w={88} />}
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((r) => (
                <Table.Tr key={r.id}>
                  {r.cells.map((c, i) => (
                    <Table.Td key={i}>{c}</Table.Td>
                  ))}
                  {canEdit && (
                    <Table.Td>
                      <Group gap={4} wrap="nowrap">
                        <ActionIcon
                          variant="subtle"
                          aria-label={`${t('actions.edit')}: ${r.name}`}
                          onClick={() => setEditing(r)}
                        >
                          <IconPencil size={16} />
                        </ActionIcon>
                        <ActionIcon
                          variant="subtle"
                          color="red"
                          aria-label={`${t('actions.delete')}: ${r.name}`}
                          onClick={async () => {
                            const ok = await confirmAction({
                              title: t('actions.delete'),
                              message: deleteConfirm(r.name),
                              confirmLabel: t('actions.delete'),
                              danger: true,
                            })
                            if (ok)
                              del.mutate(r.id, {
                                onError: (e) =>
                                  notifications.show({ color: 'red', message: toApiError(e).message }),
                              })
                          }}
                        >
                          <IconTrash size={16} />
                        </ActionIcon>
                      </Group>
                    </Table.Td>
                  )}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      </Card>
      {editing !== undefined && (
        <EditModal
          title={editing ? `${t('actions.edit')}: ${editing.name}` : addLabel}
          fields={fields}
          initial={
            editing?.values ??
            Object.fromEntries(fields.map((f) => [f.key, f.kind === 'check' ? true : null]))
          }
          save={(v) => save(v, editing?.id)}
          invalidate={invalidate}
          onClose={() => setEditing(undefined)}
        />
      )}
    </Stack>
  )
}
