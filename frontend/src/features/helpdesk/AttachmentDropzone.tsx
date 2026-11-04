import { ActionIcon, Group, Stack, Text } from '@mantine/core'
import { Dropzone } from '@mantine/dropzone'
import { IconFile, IconPaperclip, IconX } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { usePublicSettings } from '@/features/auth/queries'
import { checkFiles, formatSize, MAX_FILES, type FileProblem } from './files'

interface Props {
  value: File[]
  onChange: (files: File[]) => void
  /** Server-side error for the files field. */
  error?: string
}

export function AttachmentDropzone({ value, onChange, error }: Props) {
  const { t } = useTranslation('helpdesk')
  const { data: settings } = usePublicSettings()
  const maxMb = settings?.max_upload_mb ?? 10
  const [problems, setProblems] = useState<FileProblem[]>([])

  const add = (incoming: File[]) => {
    const result = checkFiles(value, incoming, maxMb)
    setProblems(result.problems)
    onChange(result.files)
  }

  return (
    <Stack gap="xs">
      <Dropzone
        onDrop={add}
        onReject={(rejections) => add(rejections.map((r) => r.file))}
        multiple
        disabled={value.length >= MAX_FILES}
        aria-label={t('detail.attach')}
        p="md"
      >
        <Group justify="center" gap="sm" mih={56} style={{ pointerEvents: 'none' }}>
          <IconPaperclip size={22} stroke={1.5} aria-hidden />
          <Stack gap={0}>
            <Text size="sm" fw={500}>
              {t('dropzone.idle')}
            </Text>
            <Text size="xs" c="dimmed">
              {t('new.attachmentsHint', { size: maxMb })}
            </Text>
          </Stack>
        </Group>
      </Dropzone>
      {problems.map((p) => (
        <Text key={`${p.key}-${p.name}`} size="xs" c="red" role="alert">
          {p.key === 'tooMany'
            ? t('dropzone.tooMany')
            : t(`dropzone.${p.key}`, { name: p.name, size: maxMb })}
        </Text>
      ))}
      {error && (
        <Text size="xs" c="red" role="alert">
          {error}
        </Text>
      )}
      {value.map((file, index) => (
        <Group key={`${file.name}-${index}`} gap="xs" wrap="nowrap" justify="space-between">
          <Group gap={6} wrap="nowrap" miw={0}>
            <IconFile size={16} aria-hidden />
            <Text size="sm" truncate>
              {file.name}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              {formatSize(file.size)}
            </Text>
          </Group>
          <ActionIcon
            variant="subtle"
            color="gray"
            aria-label={t('dropzone.remove', { name: file.name })}
            onClick={() => onChange(value.filter((_, i) => i !== index))}
          >
            <IconX size={16} />
          </ActionIcon>
        </Group>
      ))}
    </Stack>
  )
}
