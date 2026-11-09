import { ActionIcon, Badge, Button, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconPlus, IconSearch, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatNumber } from '@/lib/format'
import * as p from './api'
import { CartridgeFormModal } from './CartridgeFormModal'
import { CartridgeColorLabel } from './PrinterBadges'
import { useCartridges, usePrinterMutation } from './queries'
import { CARTRIDGE_KINDS, type Cartridge } from './types'

/** Cartridge and toner models, the printer models they fit and their stock. */
export function CartridgesPage() {
  const { t } = useTranslation(['printers', 'common'])
  const canManage = useCan('printers.manage')
  const params = useTableParams({ defaultSort: 'name', filterKeys: ['kind', 'active'] })
  const list = useCartridges(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [editing, setEditing] = useState<Cartridge | 'new' | null>(null)
  const remove = usePrinterMutation((id: number) => p.deleteCartridge(id))

  return (
    <>
      <PageHeader
        title={t('catalog.title')}
        description={t('catalog.description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
              {t('catalog.add')}
            </Button>
          )
        }
      />
      <DataTable<Cartridge>
        storeKey="cartridge-models"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('catalog.empty')}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('catalog.search')}
              aria-label={t('catalog.search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 260 }}
            />
            <Select
              placeholder={t('fields.kind')}
              aria-label={t('fields.kind')}
              clearable
              data={CARTRIDGE_KINDS.map((v) => ({ value: v, label: t(`kind.${v}`) }))}
              value={params.filters.kind ?? null}
              onChange={(v) => params.setFilter('kind', v)}
              w={{ base: '100%', sm: 180 }}
            />
            <Select
              placeholder={t('fields.active')}
              aria-label={t('fields.active')}
              clearable
              data={[
                { value: '1', label: t('catalog.activeOnly') },
                { value: '0', label: t('catalog.inactiveOnly') },
              ]}
              value={params.filters.active ?? null}
              onChange={(v) => params.setFilter('active', v)}
              w={{ base: '100%', sm: 180 }}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'name',
            title: t('fields.cartridge'),
            sortable: false,
            render: (c) => (
              <Stack gap={0}>
                <Group gap={6}>
                  <Text size="sm" fw={500}>
                    {c.name}
                  </Text>
                  {!c.is_active && (
                    <Badge size="xs" color="gray" variant="outline" tt="none">
                      {t('catalog.inactive')}
                    </Badge>
                  )}
                </Group>
                <Text size="xs" c="dimmed" ff="monospace">
                  {[c.part_number, c.material?.code].filter(Boolean).join(', ')}
                </Text>
              </Stack>
            ),
          },
          { accessor: 'kind', title: t('fields.kind'), render: (c) => t(`kind.${c.kind}`) },
          {
            accessor: 'color',
            title: t('fields.color'),
            render: (c) => <CartridgeColorLabel color={c.color} />,
          },
          {
            accessor: 'page_yield',
            title: t('fields.pageYield'),
            textAlign: 'right',
            render: (c) => formatNumber(c.page_yield),
          },
          {
            accessor: 'printer_models',
            title: t('fields.printerModels'),
            render: (c) =>
              c.printer_models.length === 0 ? (
                <Text size="sm" c="dimmed">
                  {t('catalog.anyPrinter')}
                </Text>
              ) : (
                <Group gap={4}>
                  {c.printer_models.map((m) => (
                    <Badge key={m.id} size="sm" variant="light" tt="none">
                      {m.name}
                    </Badge>
                  ))}
                </Group>
              ),
          },
          {
            accessor: 'stock',
            title: t('fields.stock'),
            textAlign: 'right',
            render: (c) => (
              <Text size="sm" fw={500} c={Number(c.stock ?? 0) > 0 ? undefined : 'red.8'}>
                {formatNumber(Number(c.stock ?? 0))}
                {c.material?.unit ? ` ${c.material.unit}` : ''}
              </Text>
            ),
          },
          ...(canManage
            ? [
                {
                  accessor: 'actions',
                  title: '',
                  textAlign: 'right' as const,
                  render: (c: Cartridge) => (
                    <Group gap={4} justify="flex-end" wrap="nowrap">
                      <ActionIcon
                        variant="subtle"
                        aria-label={t('common:actions.edit')}
                        onClick={() => setEditing(c)}
                      >
                        <IconPencil size={16} />
                      </ActionIcon>
                      {c.can_delete && (
                        <ActionIcon
                          variant="subtle"
                          color="red"
                          aria-label={t('common:actions.delete')}
                          onClick={async () => {
                            if (
                              await confirmAction({
                                message: t('catalog.deleteConfirm', { name: c.name }),
                                confirmLabel: t('common:actions.delete'),
                                danger: true,
                              })
                            )
                              remove.mutate(c.id, {
                                onError: (e) =>
                                  notifications.show({ color: 'red', message: toApiError(e).message }),
                              })
                          }}
                        >
                          <IconTrash size={16} />
                        </ActionIcon>
                      )}
                    </Group>
                  ),
                },
              ]
            : []),
        ]}
      />
      {editing && (
        <CartridgeFormModal
          cartridge={editing === 'new' ? undefined : editing}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  )
}
