import { Alert, Button, Code, CopyButton, Group, Modal, Stack, Text } from '@mantine/core'
import { IconCheck, IconCopy } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'

interface Props {
  /** null = closed. The caller drops the value on close, so it is shown exactly once. */
  password: string | null
  username: string
  onClose: () => void
}

export function TemporaryPasswordModal({ password, username, onClose }: Props) {
  const { t } = useTranslation(['users', 'common'])

  return (
    <Modal
      opened={password !== null}
      onClose={onClose}
      title={<Text fw={600}>{t('temporary.title')}</Text>}
      closeOnClickOutside={false}
    >
      {password !== null && (
        <Stack gap="md">
          <Text size="sm">{t('temporary.intro', { username })}</Text>
          <Group gap="sm" wrap="nowrap">
            <Code fz="lg" px="md" py="xs" style={{ letterSpacing: '0.04em', flex: 1, textAlign: 'center' }}>
              {password}
            </Code>
            <CopyButton value={password}>
              {({ copied, copy }) => (
                <Button
                  variant="default"
                  onClick={copy}
                  leftSection={copied ? <IconCheck size={16} /> : <IconCopy size={16} />}
                  aria-label={t('common:actions.copy')}
                >
                  {copied ? t('common:actions.copied') : t('common:actions.copy')}
                </Button>
              )}
            </CopyButton>
          </Group>
          <Alert color="orange" variant="light">
            {t('temporary.warning')}
          </Alert>
          <Group justify="flex-end">
            <Button onClick={onClose}>{t('temporary.done')}</Button>
          </Group>
        </Stack>
      )}
    </Modal>
  )
}
