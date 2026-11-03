import { Button, Stack, Text, ThemeIcon } from '@mantine/core'
import { IconAlertTriangle, IconRefresh } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'

interface Props {
  error: unknown
  onRetry?: () => void
  title?: string
}

export function ErrorState({ error, onRetry, title }: Props) {
  const { t } = useTranslation()
  const apiError = toApiError(error)

  return (
    <Stack align="center" gap="xs" py={48} px="md" ta="center" role="alert">
      <ThemeIcon size={44} radius="xl" variant="light" color="red">
        <IconAlertTriangle size={24} stroke={1.5} />
      </ThemeIcon>
      <Text fw={600}>{title ?? t('errors.loadFailed')}</Text>
      <Text size="sm" c="dimmed" maw={480}>
        {apiError.message}
      </Text>
      {apiError.requestId && (
        <Text size="xs" c="dimmed" ff="monospace">
          {t('errors.requestId', { id: apiError.requestId })}
        </Text>
      )}
      {onRetry && (
        <Button variant="default" leftSection={<IconRefresh size={16} />} onClick={onRetry} mt="xs">
          {t('actions.retry')}
        </Button>
      )}
    </Stack>
  )
}
