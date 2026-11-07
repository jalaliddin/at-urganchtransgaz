import {
  ActionIcon,
  Anchor,
  Badge,
  Button,
  Card,
  FileButton,
  Grid,
  Group,
  Menu,
  SimpleGrid,
  Stack,
  Table,
  Tabs,
  Text,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import {
  IconChevronDown,
  IconDownload,
  IconFile,
  IconPencil,
  IconTrash,
  IconUpload,
} from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { formatSize } from '@/features/helpdesk/files'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import * as a from './api'
import { AssetStatusBadge, WarrantyBadge } from './AssetBadges'
import { AssetDialogs, type AssetDialog } from './AssetDialogs'
import { AssetFormDrawer } from './AssetFormDrawer'
import { useAsset, useAssetMutation, useAssetPart } from './queries'
import type { Asset, AssetEvent } from './types'

function Fact({ label, children, mono = false }: { label: string; children: ReactNode; mono?: boolean }) {
  return (
    <Stack gap={0}>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text
        size="sm"
        component="div"
        ff={mono ? 'monospace' : undefined}
        style={{ overflowWrap: 'anywhere' }}
      >
        {children ?? '—'}
      </Text>
    </Stack>
  )
}

function describeEvent(e: AssetEvent, t: (k: string, o?: Record<string, unknown>) => string): string {
  const n = e.new_values ?? {}
  const o = e.old_values ?? {}
  if (e.action === 'assigned') return t('events.assigned', { name: String(n.holder ?? '') })
  if (e.action === 'transferred')
    return t('events.transferred', { from: String(o.organization ?? ''), to: String(n.organization ?? '') })
  if (e.action === 'status_changed')
    return `${t('events.status_changed')}: ${t(`status.${String(o.status)}`)} → ${t(`status.${String(n.status)}`)}`
  return t(`events.${e.action}`, { defaultValue: e.action })
}

function DetailsTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  return (
    <Stack gap="md">
      <Card withBorder p="md">
        <Title order={4} fz="sm" mb="sm">
          {t('sections.identity')}
        </Title>
        <SimpleGrid cols={{ base: 2, md: 3 }} spacing="sm">
          <Fact label={t('fields.category')}>{asset.category?.name}</Fact>
          <Fact label={t('fields.manufacturer')}>{asset.manufacturer?.name}</Fact>
          <Fact label={t('fields.model')}>{asset.model?.name}</Fact>
          <Fact label={t('fields.serial_number')} mono>
            {asset.serial_number}
          </Fact>
          <Fact label={t('fields.organization')}>{asset.organization?.name}</Fact>
          <Fact label={t('fields.department')}>{asset.department?.name}</Fact>
        </SimpleGrid>
      </Card>
      <Card withBorder p="md">
        <Title order={4} fz="sm" mb="sm">
          {t('sections.network')}
        </Title>
        <SimpleGrid cols={{ base: 2, md: 3 }} spacing="sm">
          <Fact label={t('fields.hostname')} mono>
            {asset.hostname}
          </Fact>
          <Fact label={t('fields.ip_address')} mono>
            {asset.ip_address}
          </Fact>
          <Fact label={t('fields.mac_address')} mono>
            {asset.mac_address}
          </Fact>
          <Fact label={t('fields.vlan')}>{asset.vlan}</Fact>
          <Fact label={t('fields.subnet')} mono>
            {asset.subnet}
          </Fact>
        </SimpleGrid>
      </Card>
      <Card withBorder p="md">
        <Title order={4} fz="sm" mb="sm">
          {t('sections.finance')}
        </Title>
        <SimpleGrid cols={{ base: 2, md: 3 }} spacing="sm">
          <Fact label={t('fields.purchase_date')}>{formatDate(asset.purchase_date)}</Fact>
          <Fact label={t('fields.warranty_until')}>
            <Group gap={6}>
              {formatDate(asset.warranty_until)}
              <WarrantyBadge asset={asset} />
            </Group>
          </Fact>
          <Fact label={t('fields.cost')}>{asset.cost ? formatMoney(asset.cost) : null}</Fact>
          <Fact label={t('fields.supplier')}>{asset.supplier}</Fact>
        </SimpleGrid>
        {(asset.specs || asset.notes) && (
          <SimpleGrid cols={{ base: 1, md: 2 }} mt="sm">
            {asset.specs && <Fact label={t('fields.specs')}>{asset.specs}</Fact>}
            {asset.notes && <Fact label={t('fields.notes')}>{asset.notes}</Fact>}
          </SimpleGrid>
        )}
      </Card>
    </Stack>
  )
}

function HistoryTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  const events = useAssetPart(asset.id, 'events', a.assetEvents)
  if (events.data?.length === 0) return <Text c="dimmed">{t('empty.noHistory')}</Text>
  return (
    <Timeline bulletSize={14} lineWidth={2} active={(events.data?.length ?? 1) - 1}>
      {events.data?.map((e) => (
        <Timeline.Item key={e.id} title={describeEvent(e, t)}>
          {e.note && (
            <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
              {e.note}
            </Text>
          )}
          <Text size="xs" c="dimmed">
            {e.user?.name ?? t('events.system')} · {formatDateTime(e.created_at)}
          </Text>
        </Timeline.Item>
      ))}
    </Timeline>
  )
}

function AssignmentsTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  const rows = useAssetPart(asset.id, 'assignments', a.assetAssignments)
  if (rows.data?.length === 0) return <Text c="dimmed">{t('empty.noAssignments')}</Text>
  return (
    <Table.ScrollContainer minWidth={520}>
      <Table verticalSpacing="xs">
        <Table.Thead>
          <Table.Tr>
            <Table.Th>{t('fields.employee')}</Table.Th>
            <Table.Th>{t('actions.assign')}</Table.Th>
            <Table.Th>{t('actions.return')}</Table.Th>
          </Table.Tr>
        </Table.Thead>
        <Table.Tbody>
          {rows.data?.map((r) => (
            <Table.Tr key={r.id}>
              <Table.Td>{r.employee?.name ?? '—'}</Table.Td>
              <Table.Td>
                {formatDateTime(r.assigned_at)}
                {r.note && (
                  <Text size="xs" c="dimmed">
                    {r.note}
                  </Text>
                )}
              </Table.Td>
              <Table.Td>
                {r.returned_at ? (
                  formatDateTime(r.returned_at)
                ) : (
                  <Badge variant="light" color="green" tt="none">
                    {t('status.in_use')}
                  </Badge>
                )}
                {r.return_note && (
                  <Text size="xs" c="dimmed">
                    {r.return_note}
                  </Text>
                )}
              </Table.Td>
            </Table.Tr>
          ))}
        </Table.Tbody>
      </Table>
    </Table.ScrollContainer>
  )
}

function RepairsTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  const rows = useAssetPart(asset.id, 'maintenances', a.assetMaintenances)
  if (rows.data?.length === 0) return <Text c="dimmed">{t('empty.noRepairs')}</Text>
  return (
    <Stack gap="sm">
      {rows.data?.map((m) => (
        <Card key={m.id} withBorder p="sm">
          <Group justify="space-between">
            <Badge color={m.status === 'open' ? 'orange' : 'gray'} variant="light" tt="none">
              {t(`repair.${m.status}`)}
            </Badge>
            <Text size="xs" c="dimmed">
              {formatDateTime(m.started_at)} {m.finished_at ? `— ${formatDateTime(m.finished_at)}` : ''}
            </Text>
          </Group>
          <Text size="sm" mt={6}>
            {m.description}
          </Text>
          {m.result && (
            <Text size="sm" c="green.8">
              {m.result}
            </Text>
          )}
          <Text size="xs" c="dimmed">
            {[m.vendor, m.cost ? formatMoney(m.cost) : null, m.ticket?.number].filter(Boolean).join(' · ')}
          </Text>
        </Card>
      ))}
    </Stack>
  )
}

function RelationsTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  const rows = useAssetPart(asset.id, 'relations', a.assetRelations)
  const remove = useAssetMutation((id: number) => a.deleteRelation(asset.id, id))
  if (rows.data?.length === 0) return <Text c="dimmed">{t('empty.noRelations')}</Text>
  return (
    <Stack gap={6}>
      {rows.data?.map((r) => (
        <Group key={r.id} justify="space-between" wrap="nowrap">
          <Group gap={6} wrap="nowrap" miw={0}>
            <Text size="sm" c="dimmed">
              {t(`relations.${r.direction}`)} {t(`relations.types.${r.type}`)}
            </Text>
            <Anchor component={Link} to={`/assets/${r.asset.id}`} size="sm" ff="monospace">
              {r.asset.inventory_number}
            </Anchor>
            <Text size="sm" truncate>
              {r.asset.name}
            </Text>
            {r.note && (
              <Badge variant="outline" tt="none" color="gray">
                {r.note}
              </Badge>
            )}
          </Group>
          {asset.can?.update && (
            <ActionIcon
              variant="subtle"
              color="red"
              aria-label={`${t('actions.delete')}: ${r.asset.inventory_number}`}
              onClick={() => remove.mutate(r.id)}
            >
              <IconTrash size={16} />
            </ActionIcon>
          )}
        </Group>
      ))}
    </Stack>
  )
}

function FilesTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation('assets')
  const rows = useAssetPart(asset.id, 'files', a.assetFiles)
  const upload = useAssetMutation((files: File[]) => a.uploadAssetFiles(asset.id, files))
  const remove = useAssetMutation((id: number) => a.deleteAssetFile(asset.id, id))
  return (
    <Stack gap="sm">
      {asset.can?.update && (
        <FileButton
          multiple
          onChange={(files) =>
            files.length > 0 &&
            upload.mutate(files, {
              onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
            })
          }
        >
          {(props) => (
            <Button
              {...props}
              variant="light"
              leftSection={<IconUpload size={16} />}
              loading={upload.isPending}
              w="fit-content"
            >
              {t('actions.upload')}
            </Button>
          )}
        </FileButton>
      )}
      {rows.data?.length === 0 && <Text c="dimmed">{t('empty.noFiles')}</Text>}
      {rows.data?.map((f) => (
        <Group key={f.id} justify="space-between" wrap="nowrap">
          <Anchor href={fileUrl(f.file.uuid)} size="sm">
            <Group gap={6} wrap="nowrap">
              <IconFile size={16} aria-hidden />
              {f.file.name}
            </Group>
          </Anchor>
          <Group gap={6} wrap="nowrap">
            <Text size="xs" c="dimmed" ff="monospace">
              {formatSize(f.file.size)}
            </Text>
            {asset.can?.update && (
              <ActionIcon
                variant="subtle"
                color="red"
                aria-label={`${t('actions.delete')}: ${f.file.name}`}
                onClick={() => remove.mutate(f.id)}
              >
                <IconTrash size={16} />
              </ActionIcon>
            )}
          </Group>
        </Group>
      ))}
    </Stack>
  )
}

function DocumentsTab({ asset }: { asset: Asset }) {
  const { t } = useTranslation(['assets', 'documents'])
  const canDocs = useCan('documents.view')
  const rows = useAssetPart(asset.id, 'documents', a.assetDocuments, canDocs)
  if (!canDocs || rows.data?.length === 0) return <Text c="dimmed">{t('empty.noDocuments')}</Text>
  return (
    <Stack gap={6}>
      {rows.data?.map((d) => (
        <Anchor key={d.id} component={Link} to={`/documents/${d.id}`} size="sm">
          {t(`documents:types.${d.type}`)} {d.number ? `№ ${d.number}` : ''} — {d.title}
        </Anchor>
      ))}
    </Stack>
  )
}

export function AssetDetailPage() {
  const { t } = useTranslation(['assets', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useAsset(id)
  const repairs = useAssetPart(id, 'maintenances', a.assetMaintenances)
  const [dialog, setDialog] = useState<AssetDialog | null>(null)
  const [editing, setEditing] = useState(false)
  const remove = useAssetMutation(() => a.deleteAsset(id))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const asset = query.data
  if (!asset) return null

  const can = asset.can
  const active = asset.status !== 'written_off' && !asset.in_transfer
  const openRepair = repairs.data?.find((m) => m.status === 'open')
  const onDelete = async () => {
    const ok = await confirmAction({
      title: t('actions.delete'),
      message: `${asset.inventory_number} — ${asset.name}`,
      confirmLabel: t('common:actions.delete'),
      danger: true,
    })
    if (ok)
      remove.mutate(undefined, {
        onSuccess: () => navigate('/assets'),
        onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
      })
  }

  return (
    <>
      <PageHeader
        title={asset.name}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {asset.inventory_number}
            </Text>
            <AssetStatusBadge status={asset.status} />
            <WarrantyBadge asset={asset} />
            {asset.in_transfer && (
              <Badge color="orange" variant="outline" tt="none">
                {t('transfers.status.sent')}
              </Badge>
            )}
          </Group>
        }
        actions={
          <Group gap="sm">
            {can?.assign && active && asset.status !== 'in_repair' && asset.status !== 'lost' && (
              <Button onClick={() => setDialog('assign')}>{t('actions.assign')}</Button>
            )}
            {can?.assign && active && asset.holder && (
              <Button variant="default" onClick={() => setDialog('return')}>
                {t('actions.return')}
              </Button>
            )}
            {can?.update && active && (
              <Menu position="bottom-end">
                <Menu.Target>
                  <Button variant="default" rightSection={<IconChevronDown size={14} />}>
                    {t('actions.more')}
                  </Button>
                </Menu.Target>
                <Menu.Dropdown>
                  <Menu.Item leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>
                    {t('actions.edit')}
                  </Menu.Item>
                  {!asset.holder && asset.status !== 'in_repair' && (
                    <Menu.Item onClick={() => setDialog('status')}>{t('actions.status')}</Menu.Item>
                  )}
                  {openRepair ? (
                    <Menu.Item onClick={() => setDialog('closeRepair')}>{t('actions.closeRepair')}</Menu.Item>
                  ) : (
                    <Menu.Item onClick={() => setDialog('repair')}>{t('actions.repair')}</Menu.Item>
                  )}
                  <Menu.Item onClick={() => setDialog('relation')}>{t('actions.addRelation')}</Menu.Item>
                  <Menu.Item color="red" onClick={() => setDialog('dispose')}>
                    {t('actions.dispose')}
                  </Menu.Item>
                  {can.delete && (
                    <Menu.Item
                      color="red"
                      leftSection={<IconTrash size={16} />}
                      onClick={() => void onDelete()}
                    >
                      {t('actions.delete')}
                    </Menu.Item>
                  )}
                </Menu.Dropdown>
              </Menu>
            )}
          </Group>
        }
      />
      <Grid gap="md">
        <Grid.Col span={{ base: 12, md: 8 }}>
          <Tabs defaultValue="details" keepMounted={false}>
            <Tabs.List mb="md" style={{ flexWrap: 'wrap' }}>
              <Tabs.Tab value="details">{t('sections.details')}</Tabs.Tab>
              <Tabs.Tab value="history">{t('sections.history')}</Tabs.Tab>
              <Tabs.Tab value="assignments">{t('sections.assignments')}</Tabs.Tab>
              <Tabs.Tab value="repairs">{t('sections.repairs')}</Tabs.Tab>
              <Tabs.Tab value="relations">{t('sections.relations')}</Tabs.Tab>
              <Tabs.Tab value="files">{t('sections.files')}</Tabs.Tab>
              <Tabs.Tab value="documents">{t('sections.documents')}</Tabs.Tab>
            </Tabs.List>
            <Tabs.Panel value="details">
              <DetailsTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="history">
              <HistoryTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="assignments">
              <AssignmentsTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="repairs">
              <RepairsTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="relations">
              <RelationsTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="files">
              <FilesTab asset={asset} />
            </Tabs.Panel>
            <Tabs.Panel value="documents">
              <DocumentsTab asset={asset} />
            </Tabs.Panel>
          </Tabs>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 4 }}>
          <Card withBorder p="md">
            <Title order={4} fz="sm" mb="sm">
              {t('sections.placement')}
            </Title>
            <Stack gap="sm">
              <Fact label={t('fields.holder')}>
                {asset.holder ? (
                  <Anchor component={Link} to={`/employees/${asset.holder.id}`} size="sm">
                    {asset.holder.name}
                  </Anchor>
                ) : null}
              </Fact>
              <Fact label={t('fields.responsible')}>{asset.responsible?.name}</Fact>
              <Fact label={t('fields.location')}>
                {[asset.location?.name, asset.room].filter(Boolean).join(', ') || null}
              </Fact>
              <Button
                component="a"
                href={`/assets/labels?ids=${asset.id}`}
                variant="subtle"
                leftSection={<IconDownload size={16} />}
                w="fit-content"
              >
                {t('actions.labels')}
              </Button>
            </Stack>
          </Card>
        </Grid.Col>
      </Grid>
      {dialog && (
        <AssetDialogs dialog={dialog} asset={asset} openRepair={openRepair} onClose={() => setDialog(null)} />
      )}
      {editing && <AssetFormDrawer opened asset={asset} onClose={() => setEditing(false)} />}
    </>
  )
}
