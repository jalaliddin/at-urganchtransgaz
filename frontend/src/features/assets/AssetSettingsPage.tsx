import {
  ActionIcon,
  Badge,
  Button,
  Card,
  Checkbox,
  Group,
  Modal,
  Select,
  Stack,
  Table,
  Tabs,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import * as a from './api'
import {
  useAssetCategories,
  useAssetModels,
  useAssetMutation,
  useLocations,
  useManufacturers,
} from './queries'

type Field =
  | { key: string; label: string; kind: 'text'; required?: boolean }
  | { key: string; label: string; kind: 'check' }
  | {
      key: string
      label: string
      kind: 'select'
      data: { value: string; label: string }[]
      required?: boolean
    }
  | { key: string; label: string; kind: 'organization'; required?: boolean }

type Values = Record<string, string | boolean | number | null>

function EditModal({
  title,
  fields,
  initial,
  save,
  onClose,
}: {
  title: string
  fields: Field[]
  initial: Values
  save: (values: Values) => Promise<unknown>
  onClose: () => void
}) {
  const { t } = useTranslation(['common', 'validation'])
  const [values, setValues] = useState<Values>(initial)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const mutation = useAssetMutation(save)
  const set = (key: string, value: Values[string]) => setValues((v) => ({ ...v, [key]: value }))

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

interface Row {
  id: number
  name: string
  cells: ReactNode[]
  values: Values
}

function CatalogTable({
  headers,
  rows,
  fields,
  addLabel,
  save,
  remove,
}: {
  headers: string[]
  rows: Row[]
  fields: Field[]
  addLabel: string
  save: (values: Values, id?: number) => Promise<unknown>
  remove: (id: number) => Promise<unknown>
}) {
  const { t } = useTranslation(['assets', 'common'])
  const [editing, setEditing] = useState<Row | null | undefined>(undefined)
  const del = useAssetMutation(remove)

  return (
    <Stack>
      <Group justify="flex-end">
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>
          {addLabel}
        </Button>
      </Group>
      <Card withBorder p={0}>
        <Table.ScrollContainer minWidth={480}>
          <Table verticalSpacing="xs" highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                {headers.map((h) => (
                  <Table.Th key={h}>{h}</Table.Th>
                ))}
                <Table.Th w={88} />
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((r) => (
                <Table.Tr key={r.id}>
                  {r.cells.map((c, i) => (
                    <Table.Td key={i}>{c}</Table.Td>
                  ))}
                  <Table.Td>
                    <Group gap={4} wrap="nowrap">
                      <ActionIcon
                        variant="subtle"
                        aria-label={`${t('common:actions.edit')}: ${r.name}`}
                        onClick={() => setEditing(r)}
                      >
                        <IconPencil size={16} />
                      </ActionIcon>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={`${t('common:actions.delete')}: ${r.name}`}
                        onClick={async () => {
                          const ok = await confirmAction({
                            title: t('common:actions.delete'),
                            message: t('settings.deleteConfirm', { name: r.name }),
                            confirmLabel: t('common:actions.delete'),
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
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      </Card>
      {editing !== undefined && (
        <EditModal
          title={editing ? `${t('common:actions.edit')}: ${editing.name}` : addLabel}
          fields={fields}
          initial={
            editing?.values ??
            Object.fromEntries(fields.map((f) => [f.key, f.kind === 'check' ? true : null]))
          }
          save={(v) => save(v, editing?.id)}
          onClose={() => setEditing(undefined)}
        />
      )}
    </Stack>
  )
}

export function AssetSettingsPage() {
  const { t } = useTranslation('assets')
  const { data: me } = useMe()
  const canCatalog = useCan('assets.manage_catalog')
  const categories = useAssetCategories()
  const manufacturers = useManufacturers()
  const models = useAssetModels()
  const locations = useLocations(null)
  const yes = (v: boolean) => (
    <Badge variant="light" color={v ? 'green' : 'gray'} tt="none">
      {v ? '✓' : '—'}
    </Badge>
  )
  const categoryOptions = (categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))
  const manufacturerOptions = (manufacturers.data ?? []).map((m) => ({ value: String(m.id), label: m.name }))

  return (
    <>
      <PageHeader title={t('settings.title')} description={t('settings.description')} />
      <Tabs defaultValue={canCatalog ? 'categories' : 'locations'} keepMounted={false}>
        <Tabs.List mb="md">
          {canCatalog && <Tabs.Tab value="categories">{t('settings.tabs.categories')}</Tabs.Tab>}
          {canCatalog && <Tabs.Tab value="manufacturers">{t('settings.tabs.manufacturers')}</Tabs.Tab>}
          {canCatalog && <Tabs.Tab value="models">{t('settings.tabs.models')}</Tabs.Tab>}
          <Tabs.Tab value="locations">{t('settings.tabs.locations')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="categories">
          <CatalogTable
            headers={[
              t('settings.name'),
              t('settings.nameRu'),
              t('settings.networked'),
              t('settings.active'),
            ]}
            addLabel={t('settings.add')}
            rows={(categories.data ?? []).map((c) => ({
              id: c.id,
              name: c.name,
              cells: [c.name, c.name_ru ?? '—', yes(c.is_networked), yes(c.is_active)],
              values: {
                name: c.name,
                name_ru: c.name_ru,
                is_networked: c.is_networked,
                is_active: c.is_active,
              },
            }))}
            fields={[
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              { key: 'name_ru', label: t('settings.nameRu'), kind: 'text' },
              { key: 'is_networked', label: t('settings.networked'), kind: 'check' },
              { key: 'is_active', label: t('settings.active'), kind: 'check' },
            ]}
            save={(v, id) => a.saveCategory(v as never, id)}
            remove={a.deleteCategory}
          />
        </Tabs.Panel>
        <Tabs.Panel value="manufacturers">
          <CatalogTable
            headers={[t('settings.name'), t('settings.website')]}
            addLabel={t('settings.add')}
            rows={(manufacturers.data ?? []).map((m) => ({
              id: m.id,
              name: m.name,
              cells: [m.name, m.website ?? '—'],
              values: { name: m.name, website: m.website },
            }))}
            fields={[
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              { key: 'website', label: t('settings.website'), kind: 'text' },
            ]}
            save={(v, id) =>
              a.saveManufacturer({ name: String(v.name), website: (v.website as string) || null }, id)
            }
            remove={a.deleteManufacturer}
          />
        </Tabs.Panel>
        <Tabs.Panel value="models">
          <CatalogTable
            headers={[t('settings.name'), t('fields.manufacturer'), t('fields.category')]}
            addLabel={t('settings.add')}
            rows={(models.data ?? []).map((m) => ({
              id: m.id,
              name: m.name,
              cells: [m.name, m.manufacturer.name, m.category.name],
              values: {
                name: m.name,
                manufacturer_id: m.manufacturer.id,
                category_id: m.category.id,
                specs: m.specs,
              },
            }))}
            fields={[
              {
                key: 'manufacturer_id',
                label: t('fields.manufacturer'),
                kind: 'select',
                data: manufacturerOptions,
                required: true,
              },
              {
                key: 'category_id',
                label: t('fields.category'),
                kind: 'select',
                data: categoryOptions,
                required: true,
              },
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              { key: 'specs', label: t('fields.specs'), kind: 'text' },
            ]}
            save={(v, id) =>
              a.saveModel(
                {
                  manufacturer_id: Number(v.manufacturer_id),
                  category_id: Number(v.category_id),
                  name: String(v.name),
                  specs: (v.specs as string) || null,
                },
                id,
              )
            }
            remove={a.deleteModel}
          />
        </Tabs.Panel>
        <Tabs.Panel value="locations">
          <CatalogTable
            headers={[t('settings.name'), t('fields.organization'), t('settings.code')]}
            addLabel={t('settings.add')}
            rows={(locations.data ?? []).map((l) => ({
              id: l.id,
              name: l.full_name,
              cells: [l.full_name, l.organization?.name ?? '—', l.code ?? '—'],
              values: {
                organization_id: l.organization?.id ?? null,
                name: l.name,
                parent_id: l.parent?.id ?? null,
                code: l.code,
              },
            }))}
            fields={[
              {
                key: 'organization_id',
                label: t('fields.organization'),
                kind: 'organization',
                required: true,
              },
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              {
                key: 'parent_id',
                label: t('settings.parent'),
                kind: 'select',
                data: (locations.data ?? []).map((l) => ({
                  value: String(l.id),
                  label: `${l.full_name} (${l.organization?.name ?? ''})`,
                })),
              },
              { key: 'code', label: t('settings.code'), kind: 'text' },
            ]}
            save={(v, id) =>
              a.saveLocation(
                {
                  ...(id ? {} : { organization_id: Number(v.organization_id ?? me?.organization?.id) }),
                  name: String(v.name),
                  parent_id: (v.parent_id as number | null) ?? null,
                  code: (v.code as string) || null,
                },
                id,
              )
            }
            remove={a.deleteLocation}
          />
        </Tabs.Panel>
      </Tabs>
    </>
  )
}
