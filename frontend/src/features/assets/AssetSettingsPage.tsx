import { Badge, Tabs } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { CatalogTable } from '@/components/CatalogTable'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import * as a from './api'
import { useAssetCategories, useAssetModels, useLocations, useManufacturers } from './queries'

const ASSET_KEYS = [['assets'], ['dashboard'], ['documents']]

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
            invalidate={ASSET_KEYS}
            deleteConfirm={(name) => t('settings.deleteConfirm', { name })}
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
            invalidate={ASSET_KEYS}
            deleteConfirm={(name) => t('settings.deleteConfirm', { name })}
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
            invalidate={ASSET_KEYS}
            deleteConfirm={(name) => t('settings.deleteConfirm', { name })}
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
            invalidate={ASSET_KEYS}
            deleteConfirm={(name) => t('settings.deleteConfirm', { name })}
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
