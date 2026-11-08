import { Badge, Group, Pagination, Stack, Tabs, TextInput } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { CatalogTable } from '@/components/CatalogTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import * as w from './api'
import { useMaterialCategories, useMaterials, useUnits, useWarehouses } from './queries'
import { qty } from './WarehouseBadges'

const KEYS = [['warehouse'], ['dashboard']]

export function WarehouseSettingsPage() {
  const { t } = useTranslation('warehouse')
  const canManage = useCan('warehouse.manage')
  const units = useUnits()
  const categories = useMaterialCategories()
  const warehouses = useWarehouses()
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const [page, setPage] = useState(1)
  const materials = useMaterials({ page, per_page: 50, ...(debounced ? { search: debounced } : {}) })
  const yes = (v: boolean) => (
    <Badge variant="light" color={v ? 'green' : 'gray'} tt="none">
      {v ? '✓' : '—'}
    </Badge>
  )
  const deleteConfirm = (name: string) => t('settings.deleteConfirm', { name })
  const unitOptions = (units.data ?? []).map((u) => ({ value: String(u.id), label: u.label }))
  const categoryOptions = (categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))

  return (
    <>
      <PageHeader title={t('settings.title')} description={t('settings.description')} />
      <Tabs defaultValue="materials" keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="materials">{t('settings.tabs.materials')}</Tabs.Tab>
          <Tabs.Tab value="warehouses">{t('settings.tabs.warehouses')}</Tabs.Tab>
          <Tabs.Tab value="categories">{t('settings.tabs.categories')}</Tabs.Tab>
          <Tabs.Tab value="units">{t('settings.tabs.units')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="materials">
          <Stack>
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('filters.search')}
              aria-label={t('filters.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                setPage(1)
              }}
              w={{ base: '100%', sm: 300 }}
            />
            <CatalogTable
              canEdit={canManage}
              invalidate={KEYS}
              deleteConfirm={deleteConfirm}
              headers={[
                t('fields.code'),
                t('fields.material'),
                t('fields.category'),
                t('fields.unit'),
                t('fields.minQuantity'),
                t('settings.batches'),
                t('settings.expiry'),
                t('settings.active'),
              ]}
              addLabel={t('settings.addMaterial')}
              rows={(materials.data?.data ?? []).map((m) => ({
                id: m.id,
                name: m.name,
                cells: [
                  m.code,
                  m.name,
                  m.category.name,
                  m.unit.name,
                  qty(m.min_quantity),
                  yes(m.track_batches),
                  yes(m.track_expiry),
                  yes(m.is_active),
                ],
                values: {
                  code: m.code,
                  name: m.name,
                  category_id: m.category.id,
                  unit_id: m.unit.id,
                  min_quantity: m.min_quantity,
                  track_batches: m.track_batches,
                  track_expiry: m.track_expiry,
                  is_active: m.is_active,
                },
              }))}
              fields={[
                { key: 'code', label: t('fields.codeHint'), kind: 'text' },
                { key: 'name', label: t('fields.material'), kind: 'text', required: true },
                {
                  key: 'category_id',
                  label: t('fields.category'),
                  kind: 'select',
                  data: categoryOptions,
                  required: true,
                },
                {
                  key: 'unit_id',
                  label: t('fields.unit'),
                  kind: 'select',
                  data: unitOptions,
                  required: true,
                },
                { key: 'min_quantity', label: t('fields.minQuantity'), kind: 'number', decimals: 3 },
                { key: 'track_batches', label: t('settings.batches'), kind: 'check' },
                { key: 'track_expiry', label: t('settings.expiry'), kind: 'check' },
                { key: 'is_active', label: t('settings.active'), kind: 'check' },
              ]}
              save={(v, id) =>
                w.saveMaterial({ ...v, code: v.code || null, min_quantity: v.min_quantity ?? 0 }, id)
              }
              remove={w.deleteMaterial}
            />
            {(materials.data?.meta.last_page ?? 1) > 1 && (
              <Group justify="flex-end">
                <Pagination total={materials.data?.meta.last_page ?? 1} value={page} onChange={setPage} />
              </Group>
            )}
          </Stack>
        </Tabs.Panel>
        <Tabs.Panel value="warehouses">
          <CatalogTable
            canEdit={canManage}
            invalidate={KEYS}
            deleteConfirm={deleteConfirm}
            headers={[
              t('fields.warehouse'),
              t('fields.organization'),
              t('fields.responsible'),
              t('settings.active'),
            ]}
            addLabel={t('settings.addWarehouse')}
            rows={(warehouses.data ?? []).map((wh) => ({
              id: wh.id,
              name: wh.name,
              cells: [wh.name, wh.organization.name, wh.responsible?.name ?? '—', yes(wh.is_active)],
              values: {
                name: wh.name,
                code: wh.code,
                organization_id: wh.organization.id,
                description: wh.description,
                is_active: wh.is_active,
              },
            }))}
            fields={[
              {
                key: 'organization_id',
                label: t('fields.organization'),
                kind: 'organization',
                required: true,
              },
              { key: 'name', label: t('fields.warehouse'), kind: 'text', required: true },
              { key: 'code', label: t('fields.code'), kind: 'text' },
              { key: 'description', label: t('fields.description'), kind: 'text' },
              { key: 'is_active', label: t('settings.active'), kind: 'check' },
            ]}
            save={(v, id) => {
              const { organization_id: organizationId, ...rest } = v
              return w.saveWarehouse(id ? rest : { ...rest, organization_id: organizationId }, id)
            }}
            remove={w.deleteWarehouse}
          />
        </Tabs.Panel>
        <Tabs.Panel value="categories">
          <CatalogTable
            canEdit={canManage}
            invalidate={KEYS}
            deleteConfirm={deleteConfirm}
            headers={[t('settings.name'), t('settings.nameRu'), t('settings.active')]}
            addLabel={t('settings.addCategory')}
            rows={(categories.data ?? []).map((c) => ({
              id: c.id,
              name: c.name,
              cells: [c.name, c.name_ru ?? '—', yes(c.is_active)],
              values: { name: c.name, name_ru: c.name_ru, is_active: c.is_active },
            }))}
            fields={[
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              { key: 'name_ru', label: t('settings.nameRu'), kind: 'text' },
              { key: 'is_active', label: t('settings.active'), kind: 'check' },
            ]}
            save={(v, id) => w.saveMaterialCategory(v, id)}
            remove={w.deleteMaterialCategory}
          />
        </Tabs.Panel>
        <Tabs.Panel value="units">
          <CatalogTable
            canEdit={canManage}
            invalidate={KEYS}
            deleteConfirm={deleteConfirm}
            headers={[t('settings.unitCode'), t('settings.name'), t('settings.nameRu'), t('settings.active')]}
            addLabel={t('settings.addUnit')}
            rows={(units.data ?? []).map((u) => ({
              id: u.id,
              name: u.name,
              cells: [u.code, u.name, u.name_ru ?? '—', yes(u.is_active)],
              values: { code: u.code, name: u.name, name_ru: u.name_ru, is_active: u.is_active },
            }))}
            fields={[
              { key: 'code', label: t('settings.unitCode'), kind: 'text', required: true },
              { key: 'name', label: t('settings.name'), kind: 'text', required: true },
              { key: 'name_ru', label: t('settings.nameRu'), kind: 'text' },
              { key: 'is_active', label: t('settings.active'), kind: 'check' },
            ]}
            save={(v, id) => w.saveUnit(v, id)}
            remove={w.deleteUnit}
          />
        </Tabs.Panel>
      </Tabs>
    </>
  )
}
