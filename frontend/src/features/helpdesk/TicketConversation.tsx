import {
  ActionIcon,
  Anchor,
  Avatar,
  Badge,
  Button,
  Card,
  FileButton,
  Group,
  Paper,
  Stack,
  Switch,
  Text,
  Textarea,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconLock, IconPaperclip, IconSend, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { usePublicSettings } from '@/features/auth/queries'
import { fileUrl } from '@/features/exports/api'
import { formatDateTime } from '@/lib/format'
import { checkFiles } from './files'
import { useTicketAction, useTicketComments } from './queries'
import type { Ticket } from './types'

function initials(name: string | undefined): string {
  return (name ?? '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part[0] ?? '')
    .join('')
    .toUpperCase()
}

export function TicketConversation({ ticket }: { ticket: Ticket }) {
  const { t } = useTranslation('helpdesk')
  const comments = useTicketComments(ticket.id)
  const { comment } = useTicketAction(ticket.id)
  const { data: settings } = usePublicSettings()
  const [body, setBody] = useState('')
  const [internal, setInternal] = useState(false)
  const [files, setFiles] = useState<File[]>([])
  const [error, setError] = useState<string | null>(null)
  const canComment = ticket.allowed_actions.includes('comment')
  const canInternal = ticket.allowed_actions.includes('comment_internal')

  const addFiles = (incoming: File[]) => {
    const result = checkFiles(files, incoming, settings?.max_upload_mb ?? 10)
    setFiles(result.files)
    const problem = result.problems[0]
    setError(
      problem
        ? problem.key === 'tooMany'
          ? t('dropzone.tooMany')
          : t(`dropzone.${problem.key}`, { name: problem.name, size: settings?.max_upload_mb ?? 10 })
        : null,
    )
  }

  const send = () => {
    if (body.trim() === '') return
    comment.mutate(
      { body: body.trim(), isInternal: internal, files },
      {
        onSuccess: () => {
          setBody('')
          setFiles([])
          setInternal(false)
          setError(null)
        },
        onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
      },
    )
  }

  return (
    <Stack gap="md">
      {comments.data?.length === 0 && (
        <Text size="sm" c="dimmed">
          {t('detail.noComments')}
        </Text>
      )}
      {comments.data?.map((c) => (
        <Paper
          key={c.id}
          withBorder
          p="sm"
          radius="md"
          bg={c.is_internal ? 'var(--mantine-color-yellow-light)' : undefined}
          aria-label={c.is_internal ? t('detail.internal') : undefined}
        >
          <Group justify="space-between" wrap="nowrap" mb={6}>
            <Group gap="xs" wrap="nowrap">
              <Avatar size="sm" radius="xl" color="cobalt">
                {initials(c.user?.name)}
              </Avatar>
              <Text size="sm" fw={600}>
                {c.user?.name ?? '—'}
              </Text>
              {c.is_internal && (
                <Badge
                  size="xs"
                  color="yellow"
                  variant="filled"
                  leftSection={<IconLock size={10} aria-hidden />}
                  tt="none"
                >
                  {t('detail.internal')}
                </Badge>
              )}
            </Group>
            <Text size="xs" c="dimmed" ff="monospace">
              {formatDateTime(c.created_at)}
            </Text>
          </Group>
          <Text size="sm" style={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>
            {c.body}
          </Text>
          {c.attachments.length > 0 && (
            <Group gap="xs" mt="xs">
              {c.attachments.map((a) => (
                <Anchor key={a.id} href={fileUrl(a.file.uuid)} size="xs">
                  <Group gap={4} wrap="nowrap">
                    <IconPaperclip size={12} aria-hidden />
                    {a.file.name}
                  </Group>
                </Anchor>
              ))}
            </Group>
          )}
        </Paper>
      ))}

      {canComment && (
        <Card withBorder radius="md" p="sm">
          <Stack gap="xs">
            <Textarea
              aria-label={t('detail.commentPlaceholder')}
              placeholder={t('detail.commentPlaceholder')}
              autosize
              minRows={2}
              maxRows={10}
              value={body}
              onChange={(e) => setBody(e.currentTarget.value)}
            />
            {files.map((file, index) => (
              <Group key={`${file.name}-${index}`} gap="xs" wrap="nowrap">
                <IconPaperclip size={14} aria-hidden />
                <Text size="xs" truncate>
                  {file.name}
                </Text>
                <ActionIcon
                  size="sm"
                  variant="subtle"
                  color="gray"
                  aria-label={t('dropzone.remove', { name: file.name })}
                  onClick={() => setFiles(files.filter((_, i) => i !== index))}
                >
                  <IconX size={14} />
                </ActionIcon>
              </Group>
            ))}
            {error && (
              <Text size="xs" c="red" role="alert">
                {error}
              </Text>
            )}
            <Group justify="space-between" wrap="wrap" gap="xs">
              <Group gap="sm">
                {ticket.allowed_actions.includes('attach') && (
                  <FileButton onChange={addFiles} multiple>
                    {(props) => (
                      <Button
                        {...props}
                        variant="subtle"
                        size="compact-sm"
                        leftSection={<IconPaperclip size={14} />}
                      >
                        {t('detail.attach')}
                      </Button>
                    )}
                  </FileButton>
                )}
                {canInternal && (
                  <Switch
                    label={t('detail.internal')}
                    description={t('detail.internalHint')}
                    checked={internal}
                    onChange={(e) => setInternal(e.currentTarget.checked)}
                    color="yellow"
                  />
                )}
              </Group>
              <Button
                leftSection={<IconSend size={16} />}
                onClick={send}
                loading={comment.isPending}
                disabled={body.trim() === ''}
              >
                {t('detail.send')}
              </Button>
            </Group>
          </Stack>
        </Card>
      )}
    </Stack>
  )
}
