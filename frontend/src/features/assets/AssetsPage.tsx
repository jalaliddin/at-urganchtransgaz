import { Anchor, Button, Checkbox, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconChartBar, IconPlus, IconQrcode, IconSearch, IconSettings } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { AssetStatusBadge, WarrantyBadge } from './AssetBadges'
import { AssetFormDrawer } from './AssetFormDrawer'
import { useAssetCategories, useAssets } from './queries'
import { ASSET_STATUSES, type Asset } from './types'

const FILTER_KEYS = ['status', 'category', 'organization', 'location', 'holder', 'warranty']

export function AssetsPage() {
  const { t } = useTranslation(['assets', 'common'])
  const navigate = useNavigate()
  const canCreate = useCan('assets.create')
  const canExport = useCan('assets.export')
  const canCatalog = useCan('assets.manage_catalog')
  const params = useTableParams({
    defaultSort: 'inventory_number',
    filterKeys: FILTER_KEYS,
    organizationContext: true,
  })
  const list = useAssets(params.apiParams)
  const categories = useAssetCategories()
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState<Set<number>>(new Set())

  const toggle = (id: number) =>
    setSelected((s) => {
      const next = new Set(s)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  const pageIds = (list.data?.data ?? []).map((a) => a.id)
  const allSelected = pageIds.length > 0 && pageIds.every((id) => selected.has(id))

  const exportFilters: Record<string, string> = { ...params.filters }
  if (params.search) exportFilters.search = params.search
  const orgParam = params.apiParams['filter[organization]']
  if (orgParam !== undefined) exportFilters.organization = String(orgParam)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            <Button
              component={Link}
              to="/assets/report"
              variant="default"
              leftSection={<IconChartBar size={16} />}
            >
              {t('actions.report')}
            </Button>
            {canCatalog && (
              <Button
                component={Link}
                to="/assets/settings"
                variant="default"
                leftSection={<IconSettings size={16} />}
              >
                {t('actions.settings')}
              </Button>
            )}
            {canCreate && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
                {t('actions.create')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<Asset>
        storeKey="assets"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty.title')}
        emptyDescription={t('empty.description')}
        onRowClick={(a) => navigate(`/assets/${a.id}`)}
        toolbar={
          <Stack gap="sm" w="100%">
            <Group gap="sm" wrap="wrap">
              <TextInput
                leftSection={<IconSearch size={16} />}
                placeholder={t('filters.search')}
                aria-label={t('filters.search')}
                value={search}
                onChange={(e) => {
                  setSearch(e.currentTarget.value)
                  pushSearch(e.currentTarget.value)
                }}
                w={{ base: '100%', sm: 300 }}
              />
              <Select
                aria-label={t('filters.status')}
                placeholder={t('filters.allStatuses')}
                data={ASSET_STATUSES.map((s) => ({ value: s, label: t(`status.${s}`) }))}
                value={params.filters.status ?? null}
                onChange={(v) => params.setFilter('status', v)}
                clearable
                w={{ base: '100%', sm: 180 }}
              />
              <Select
                aria-label={t('filters.category')}
                placeholder={t('filters.category')}
                data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
                value={params.filters.category ?? null}
                onChange={(v) => params.setFilter('category', v)}
                clearable
                searchable
                w={{ base: '100%', sm: 220 }}
              />
              <OrganizationSelect
                aria-label={t('filters.organization')}
                placeholder={t('filters.organization')}
                value={params.filters.organization ? Number(params.filters.organization) : null}
                onChange={(v) => params.setFilter('organization', v === null ? null : String(v))}
                w={{ base: '100%', sm: 220 }}
              />
              <Select
                aria-label={t('filters.warranty')}
                placeholder={t('filters.warranty')}
                data={[
                  { value: 'expiring', label: t('filters.warrantyExpiring') },
                  { value: 'expired', label: t('filters.warrantyExpired') },
                ]}
                value={params.filters.warranty ?? null}
                onChange={(v) => params.setFilter('warranty', v)}
                clearable
                w={{ base: '100%', sm: 190 }}
              />
              <Checkbox
                label={t('filters.unassigned')}
                checked={params.filters.holder === 'none'}
                onChange={(e) => params.setFilter('holder', e.currentTarget.checked ? 'none' : null)}
              />
            </Group>
            <Group gap="sm">
              <Button
                variant="default"
                leftSection={<IconQrcode size={16} />}
                disabled={selected.size === 0}
                onClick={() => navigate(`/assets/labels?ids=${[...selected].join(',')}`)}
              >
                {t('actions.labels')} {selected.size > 0 ? `(${selected.size})` : ''}
              </Button>
              {canExport && <ExportMenu type="assets" filters={exportFilters} />}
            </Group>
          </Stack>
        }
        columns={[
          {
            accessor: 'select',
            title: (
              <Checkbox
                aria-label={t('actions.selectToLabel')}
                checked={allSelected}
                onChange={() =>
                  setSelected((s) => {
                    const next = new Set(s)
                    pageIds.forEach((id) => (allSelected ? next.delete(id) : next.add(id)))
                    return next
                  })
                }
              />
            ),
            width: 44,
            render: (a) => (
              <Checkbox
                aria-label={`${t('actions.selectToLabel')}: ${a.inventory_number}`}
                checked={selected.has(a.id)}
                onClick={(e) => e.stopPropagation()}
                onChange={() => toggle(a.id)}
              />
            ),
          },
          {
            accessor: 'inventory_number',
            title: t('fields.inventory_number'),
            sortable: true,
            noWrap: true,
            render: (a) => (
              <Anchor
                component={Link}
                to={`/assets/${a.id}`}
                onClick={(e) => e.stopPropagation()}
                size="sm"
                ff="monospace"
                fw={500}
              >
                {a.inventory_number}
              </Anchor>
            ),
          },
          {
            accessor: 'name',
            title: t('fields.name'),
            sortable: true,
            width: 280,
            render: (a) => (
              <Stack gap={0}>
                <Text size="sm" fw={500} lineClamp={1}>
                  {a.name}
                </Text>
                <Text size="xs" c="dimmed" lineClamp={1}>
                  {[a.category?.name, a.serial_number].filter(Boolean).join(' · ')}
                </Text>
                <Group gap={6} mt={4} hiddenFrom="sm">
                  <AssetStatusBadge status={a.status} />
                </Group>
              </Stack>
            ),
          },
          {
            accessor: 'network',
            title: `${t('fields.hostname')} / IP`,
            noWrap: true,
            render: (a) =>
              a.hostname || a.ip_address ? (
                <Stack gap={0}>
                  <Text size="sm" ff="monospace">
                    {a.hostname ?? '—'}
                  </Text>
                  <Text size="xs" c="dimmed" ff="monospace">
                    {a.ip_address ?? ''}
                  </Text>
                </Stack>
              ) : (
                '—'
              ),
          },
          {
            accessor: 'organization',
            title: t('fields.organization'),
            render: (a) => a.organization?.name ?? '—',
          },
          { accessor: 'holder', title: t('fields.holder'), render: (a) => a.holder?.name ?? '—' },
          {
            accessor: 'status',
            title: t('fields.status'),
            sortable: true,
            noWrap: true,
            render: (a) => (
              <Group gap={4} wrap="nowrap">
                <AssetStatusBadge status={a.status} />
                <WarrantyBadge asset={a} />
              </Group>
            ),
          },
        ]}
      />
      {creating && (
        <AssetFormDrawer
          opened
          onClose={(saved) => {
            setCreating(false)
            if (saved) navigate(`/assets/${saved.id}`)
          }}
        />
      )}
    </>
  )
}
