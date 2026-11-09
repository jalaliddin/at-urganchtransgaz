import {
  ActionIcon,
  Anchor,
  Button,
  Card,
  FileInput,
  Group,
  Modal,
  SegmentedControl,
  Select,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconDownload, IconLink, IconPencil, IconTrash, IconUpload } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { formatSize } from '@/features/helpdesk/files'
import { useAssets } from '@/features/assets/queries'
import { useTickets } from '@/features/helpdesk/queries'
import { useContracts } from '@/features/services/queries'
import { useSims } from '@/features/sim/queries'
import { formatPhone } from '@/features/sim/types'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime } from '@/lib/format'
import { DocumentFormModal } from './DocumentFormModal'
import { useDocument, useDocumentMutations } from './queries'

type LinkType = 'ticket' | 'asset' | 'sim_card' | 'service_contract'

const LINK_PATHS: Record<string, string> = {
  ticket: '/helpdesk',
  asset: '/assets',
  sim_card: '/sim',
  service_contract: '/services/contracts',
}

const fail = (error: unknown) => notifications.show({ color: 'red', message: toApiError(error).message })

function VersionModal({ documentId, onClose }: { documentId: number; onClose: () => void }) {
  const { t } = useTranslation(['documents', 'common'])
  const { addVersion } = useDocumentMutations(documentId)
  const [file, setFile] = useState<File | null>(null)
  const [note, setNote] = useState('')
  const error = addVersion.error
    ? (toApiError(addVersion.error).fieldErrors.file?.[0] ?? toApiError(addVersion.error).message)
    : null

  return (
    <Modal opened onClose={onClose} title={t('actions.newVersion')} centered>
      <Stack>
        <FileInput
          label={t('fields.file')}
          required
          value={file}
          onChange={setFile}
          error={error}
          data-autofocus
        />
        <TextInput label={t('fields.note')} value={note} onChange={(e) => setNote(e.currentTarget.value)} />
        <Group justify="flex-end">
          <Button
            disabled={file === null}
            loading={addVersion.isPending}
            onClick={() =>
              file &&
              addVersion.mutate(
                { file, note },
                {
                  onSuccess: (v) => {
                    notifications.show({ color: 'green', message: t('versionAdded', { version: v.version }) })
                    onClose()
                  },
                },
              )
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function LinkModal({
  documentId,
  types,
  onClose,
}: {
  documentId: number
  types: LinkType[]
  onClose: () => void
}) {
  const { t } = useTranslation(['documents', 'common'])
  const { link } = useDocumentMutations(documentId)
  const [type, setType] = useState<LinkType>(types[0] ?? 'ticket')
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const [selected, setSelected] = useState<string | null>(null)
  const searching = debounced.length >= 2
  const tickets = useTickets({ search: debounced, per_page: 10 }, type === 'ticket' && searching)
  const assets = useAssets({ search: debounced, per_page: 10 }, type === 'asset' && searching)
  const sims = useSims({ search: debounced, per_page: 10 }, type === 'sim_card' && searching)
  const contracts = useContracts(
    { search: debounced, per_page: 10 },
    type === 'service_contract' && searching,
  )
  const options = {
    ticket: () =>
      (tickets.data?.data ?? []).map((tk) => ({ value: String(tk.id), label: `${tk.number} — ${tk.title}` })),
    asset: () =>
      (assets.data?.data ?? []).map((a) => ({
        value: String(a.id),
        label: `${a.inventory_number} — ${a.name}`,
      })),
    sim_card: () =>
      (sims.data?.data ?? []).map((c) => ({
        value: String(c.id),
        label: `${formatPhone(c.phone_number)} — ${c.provider.name}`,
      })),
    service_contract: () =>
      (contracts.data?.data ?? []).map((c) => ({
        value: String(c.id),
        label: `${c.number} — ${c.provider.name}`,
      })),
  }[type]()

  return (
    <Modal opened onClose={onClose} title={t('actions.link')} centered>
      <Stack>
        {types.length > 2 && (
          <Select
            label={t('linkType')}
            data={types.map((v) => ({ value: v, label: t(`linkTypes.${v}`) }))}
            value={type}
            allowDeselect={false}
            onChange={(v) => {
              if (!v) return
              setType(v as LinkType)
              setSelected(null)
              setSearch('')
            }}
          />
        )}
        {types.length === 2 && (
          <SegmentedControl
            aria-label={t('linkType')}
            value={type}
            onChange={(v) => {
              setType(v as LinkType)
              setSelected(null)
              setSearch('')
            }}
            data={types.map((v) => ({ value: v, label: t(`linkTypes.${v}`) }))}
          />
        )}
        <Select
          label={t('linkTarget')}
          searchable
          searchValue={search}
          onSearchChange={setSearch}
          filter={({ options: o }) => o}
          data={options}
          value={selected}
          onChange={setSelected}
          data-autofocus
        />
        <Group justify="flex-end">
          <Button
            disabled={selected === null}
            loading={link.isPending}
            onClick={() =>
              selected &&
              link.mutate({ type, targetId: Number(selected) }, { onSuccess: onClose, onError: fail })
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function DocumentDetailPage() {
  const { t } = useTranslation(['documents', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useDocument(id)
  const { remove, unlink } = useDocumentMutations(id)
  const canSeeTickets = useCan(['helpdesk.view', 'helpdesk.create'], 'any')
  const canSeeAssets = useCan('assets.view')
  const canSeeSims = useCan(['sim.view', 'sim.assign', 'sim.manage'], 'any')
  const canSeeServices = useCan(
    ['services.view', 'services.manage', 'services.invoice', 'services.approve', 'services.pay'],
    'any',
  )
  const linkTypes: LinkType[] = [
    ...(canSeeTickets ? (['ticket'] as const) : []),
    ...(canSeeAssets ? (['asset'] as const) : []),
    ...(canSeeSims ? (['sim_card'] as const) : []),
    ...(canSeeServices ? (['service_contract'] as const) : []),
  ]
  const [dialog, setDialog] = useState<'edit' | 'version' | 'link' | null>(null)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const doc = query.data
  if (!doc) return null

  const onDelete = async () => {
    const ok = await confirmAction({
      title: t('actions.delete'),
      message: t('deleteConfirm', { title: doc.title }),
      confirmLabel: t('common:actions.delete'),
      danger: true,
    })
    if (ok) remove.mutate(undefined, { onSuccess: () => navigate('/documents'), onError: fail })
  }

  return (
    <>
      <PageHeader
        title={doc.title}
        description={`${t(`types.${doc.type}`)}${doc.number ? ` № ${doc.number}` : ''}${doc.document_date ? ` · ${formatDate(doc.document_date)}` : ''}`}
        actions={
          doc.can_manage && (
            <>
              <Button
                variant="default"
                leftSection={<IconPencil size={16} />}
                onClick={() => setDialog('edit')}
              >
                {t('actions.edit')}
              </Button>
              <Button leftSection={<IconUpload size={16} />} onClick={() => setDialog('version')}>
                {t('actions.newVersion')}
              </Button>
              <Button
                variant="subtle"
                color="red"
                leftSection={<IconTrash size={16} />}
                onClick={() => void onDelete()}
              >
                {t('actions.delete')}
              </Button>
            </>
          )
        }
      />
      <SimpleGrid cols={{ base: 1, md: 3 }} spacing="md">
        <Card withBorder p={0} style={{ gridColumn: 'span 2' }}>
          <Title order={3} fz="sm" p="md" pb={0}>
            {t('fields.versions')}
          </Title>
          <Table.ScrollContainer minWidth={520}>
            <Table verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.version')}</Table.Th>
                  <Table.Th>{t('fields.file')}</Table.Th>
                  <Table.Th>{t('fields.note')}</Table.Th>
                  <Table.Th>{t('fields.uploadedBy')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {(doc.versions ?? []).map((v) => (
                  <Table.Tr key={v.id}>
                    <Table.Td ff="monospace">v{v.version}</Table.Td>
                    <Table.Td>
                      <Anchor href={fileUrl(v.file.uuid)} size="sm">
                        <Group gap={4} wrap="nowrap">
                          <IconDownload size={14} aria-hidden />
                          {v.file.name}
                        </Group>
                      </Anchor>
                      <Text size="xs" c="dimmed" ff="monospace">
                        {formatSize(v.file.size)}
                      </Text>
                    </Table.Td>
                    <Table.Td>{v.note ?? '—'}</Table.Td>
                    <Table.Td>
                      <Text size="sm">{v.uploaded_by?.name ?? '—'}</Text>
                      <Text size="xs" c="dimmed">
                        {formatDateTime(v.created_at)}
                      </Text>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
        <Stack gap="md">
          <Card withBorder p="md">
            <Stack gap="xs">
              <Text size="xs" c="dimmed">
                {t('fields.organization')}
              </Text>
              <Text size="sm">{doc.organization?.name ?? '—'}</Text>
              {doc.description && (
                <>
                  <Text size="xs" c="dimmed">
                    {t('fields.description')}
                  </Text>
                  <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                    {doc.description}
                  </Text>
                </>
              )}
            </Stack>
          </Card>
          <Card withBorder p="md">
            <Group justify="space-between" mb="xs">
              <Title order={3} fz="sm">
                {t('fields.links')}
              </Title>
              {doc.can_manage && linkTypes.length > 0 && (
                <Button
                  size="compact-sm"
                  variant="subtle"
                  leftSection={<IconLink size={14} />}
                  onClick={() => setDialog('link')}
                >
                  {t('actions.link')}
                </Button>
              )}
            </Group>
            {(doc.links ?? []).length === 0 && (
              <Text size="sm" c="dimmed">
                {t('empty.noLinks')}
              </Text>
            )}
            {(doc.links ?? []).map((l) => (
              <Group key={l.id} justify="space-between" wrap="nowrap">
                <Anchor component={Link} to={`${LINK_PATHS[l.type] ?? '/helpdesk'}/${l.target.id}`} size="sm">
                  {l.target.number} {l.target.title ? `— ${l.target.title}` : ''}
                </Anchor>
                {doc.can_manage && (
                  <ActionIcon
                    size="sm"
                    variant="subtle"
                    color="red"
                    aria-label={`${t('actions.unlink')}: ${l.target.number ?? l.target.id}`}
                    onClick={() => unlink.mutate(l.id, { onError: fail })}
                  >
                    <IconTrash size={14} />
                  </ActionIcon>
                )}
              </Group>
            ))}
          </Card>
        </Stack>
      </SimpleGrid>
      {dialog === 'edit' && <DocumentFormModal document={doc} onClose={() => setDialog(null)} />}
      {dialog === 'version' && <VersionModal documentId={doc.id} onClose={() => setDialog(null)} />}
      {dialog === 'link' && (
        <LinkModal documentId={doc.id} types={linkTypes} onClose={() => setDialog(null)} />
      )}
    </>
  )
}
