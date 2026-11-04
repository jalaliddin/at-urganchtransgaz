import { ActionIcon, Anchor, Button, Card, Group, Modal, Select, Stack, Text, Title } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconFile, IconLink, IconTrash, IconUpload } from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { useMe } from '@/features/auth/queries'
import { fileUrl } from '@/features/exports/api'
import { useCan } from '@/hooks/usePermissions'
import { AttachmentDropzone } from './AttachmentDropzone'
import { formatSize } from './files'
import {
  useTicketAction,
  useTicketAttachments,
  useTicketDocuments,
  useTicketLinks,
  useTickets,
} from './queries'
import { TicketStatusBadge } from './TicketBadges'
import type { Ticket, TicketLink } from './types'

function Panel({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <Card withBorder radius="md" p="md">
      <Group justify="space-between" mb="xs">
        <Title order={4} fz="sm">
          {title}
        </Title>
        {action}
      </Group>
      {children}
    </Card>
  )
}

export function AttachmentsPanel({ ticket }: { ticket: Ticket }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const { data: me } = useMe()
  const attachments = useTicketAttachments(ticket.id)
  const { upload, deleteAttachment } = useTicketAction(ticket.id)
  const [files, setFiles] = useState<File[]>([])
  const [adding, setAdding] = useState(false)
  const handler = ticket.allowed_actions.includes('comment_internal')

  const remove = async (id: number, name: string) => {
    const confirmed = await confirmAction({
      title: t('detail.deleteFile'),
      message: name,
      confirmLabel: t('common:actions.delete'),
      danger: true,
    })
    if (confirmed) {
      deleteAttachment.mutate(id, {
        onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
      })
    }
  }

  return (
    <Panel
      title={t('detail.attachments')}
      action={
        ticket.allowed_actions.includes('attach') && (
          <Button
            size="compact-sm"
            variant="subtle"
            leftSection={<IconUpload size={14} />}
            onClick={() => setAdding(true)}
          >
            {t('detail.attach')}
          </Button>
        )
      }
    >
      <Stack gap={6}>
        {attachments.data?.length === 0 && (
          <Text size="sm" c="dimmed">
            {t('detail.noAttachments')}
          </Text>
        )}
        {attachments.data?.map((a) => (
          <Group key={a.id} gap="xs" wrap="nowrap" justify="space-between">
            <Anchor href={fileUrl(a.file.uuid)} size="sm" miw={0}>
              <Group gap={6} wrap="nowrap">
                <IconFile size={16} aria-hidden />
                <Text size="sm" truncate span>
                  {a.file.name}
                </Text>
              </Group>
            </Anchor>
            <Group gap={4} wrap="nowrap">
              <Text size="xs" c="dimmed" ff="monospace">
                {formatSize(a.file.size)}
              </Text>
              {(handler || a.user?.id === me?.id) && (
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  color="red"
                  aria-label={`${t('detail.deleteFile')}: ${a.file.name}`}
                  onClick={() => void remove(a.id, a.file.name)}
                >
                  <IconTrash size={14} />
                </ActionIcon>
              )}
            </Group>
          </Group>
        ))}
      </Stack>
      <Modal opened={adding} onClose={() => setAdding(false)} title={t('detail.attach')} centered>
        <Stack>
          <AttachmentDropzone value={files} onChange={setFiles} />
          <Group justify="flex-end">
            <Button
              disabled={files.length === 0}
              loading={upload.isPending}
              onClick={() =>
                upload.mutate(files, {
                  onSuccess: () => {
                    setFiles([])
                    setAdding(false)
                  },
                  onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                })
              }
            >
              {t('workflow.confirm')}
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Panel>
  )
}

export function LinksPanel({ ticket }: { ticket: Ticket }) {
  const { t } = useTranslation('helpdesk')
  const links = useTicketLinks(ticket.id)
  const { link, unlink } = useTicketAction(ticket.id)
  const [adding, setAdding] = useState(false)
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const [selected, setSelected] = useState<string | null>(null)
  const [type, setType] = useState<TicketLink['type']>('relates')
  const candidates = useTickets({ search: debounced, per_page: 10 }, adding && debounced.length >= 2)
  const canLink = ticket.allowed_actions.includes('link')

  return (
    <Panel
      title={t('detail.links')}
      action={
        canLink && (
          <Button
            size="compact-sm"
            variant="subtle"
            leftSection={<IconLink size={14} />}
            onClick={() => setAdding(true)}
          >
            {t('workflow.link')}
          </Button>
        )
      }
    >
      <Stack gap={6}>
        {links.data?.length === 0 && (
          <Text size="sm" c="dimmed">
            {t('detail.noLinks')}
          </Text>
        )}
        {links.data?.map((l) => (
          <Group key={l.id} gap="xs" wrap="nowrap" justify="space-between">
            <Stack gap={0} miw={0}>
              <Anchor component={Link} to={`/helpdesk/${l.ticket.id}`} size="sm" ff="monospace">
                {l.ticket.number}
              </Anchor>
              <Text size="xs" c="dimmed" truncate>
                {t(`workflow.linkTypes.${l.type}`)} · {l.ticket.title}
              </Text>
            </Stack>
            <Group gap={4} wrap="nowrap">
              <TicketStatusBadge status={l.ticket.status} />
              {canLink && (
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  color="red"
                  aria-label={`${t('workflow.link')}: ${l.ticket.number}`}
                  onClick={() => unlink.mutate(l.id)}
                >
                  <IconTrash size={14} />
                </ActionIcon>
              )}
            </Group>
          </Group>
        ))}
      </Stack>
      <Modal opened={adding} onClose={() => setAdding(false)} title={t('workflow.link')} centered>
        <Stack>
          <Select
            label={t('workflow.linkTicket')}
            searchable
            searchValue={search}
            onSearchChange={setSearch}
            filter={({ options }) => options}
            data={(candidates.data?.data ?? [])
              .filter((c) => c.id !== ticket.id)
              .map((c) => ({ value: String(c.id), label: `${c.number} — ${c.title}` }))}
            value={selected}
            onChange={setSelected}
            data-autofocus
          />
          <Select
            label={t('workflow.linkType')}
            data={(['relates', 'duplicates'] as const).map((v) => ({
              value: v,
              label: t(`workflow.linkTypes.${v}`),
            }))}
            value={type}
            onChange={(v) => setType((v as TicketLink['type']) ?? 'relates')}
            allowDeselect={false}
          />
          <Group justify="flex-end">
            <Button
              disabled={selected === null}
              loading={link.isPending}
              onClick={() =>
                selected !== null &&
                link.mutate(
                  { linkedId: Number(selected), type },
                  {
                    onSuccess: () => {
                      setAdding(false)
                      setSelected(null)
                      setSearch('')
                    },
                    onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                  },
                )
              }
            >
              {t('workflow.confirm')}
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Panel>
  )
}

export function DocumentsPanel({ ticket }: { ticket: Ticket }) {
  const { t } = useTranslation(['helpdesk', 'documents'])
  const canView = useCan('documents.view')
  const documents = useTicketDocuments(ticket.id, canView)
  if (!canView) return null

  return (
    <Panel title={t('detail.documents')}>
      <Stack gap={6}>
        {documents.data?.length === 0 && (
          <Text size="sm" c="dimmed">
            {t('detail.noDocuments')}
          </Text>
        )}
        {documents.data?.map((d) => (
          <Stack key={d.id} gap={0}>
            <Anchor component={Link} to={`/documents/${d.id}`} size="sm">
              {d.title}
            </Anchor>
            <Text size="xs" c="dimmed">
              {t(`documents:types.${d.type}`)}
              {d.number ? ` · № ${d.number}` : ''}
            </Text>
          </Stack>
        ))}
      </Stack>
    </Panel>
  )
}
