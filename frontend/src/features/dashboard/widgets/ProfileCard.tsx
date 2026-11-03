import { Anchor, Group, Paper, Stack, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import type { DashboardData } from '../api'

export function ProfileCard({ profile }: { profile: DashboardData['profile'] }) {
  const { t } = useTranslation('dashboard')

  return (
    <Paper withBorder p="md">
      <Title order={2} size="h4" mb="sm">
        {t('profile.title')}
      </Title>
      <Stack gap="xs">
        <div>
          <Text size="xs" c="dimmed">
            {t('profile.organization')}
          </Text>
          <Text size="sm">{profile.organization?.name ?? '—'}</Text>
        </div>
        <div>
          <Text size="xs" c="dimmed">
            {t('profile.roles')}
          </Text>
          <Text size="sm">{profile.roles.join(', ') || '—'}</Text>
        </div>
        <Group gap="md" mt={4}>
          <Anchor component={Link} to="/profile" size="sm">
            {t('profile.open')}
          </Anchor>
          <Anchor component={Link} to="/change-password" size="sm">
            {t('profile.changePassword')}
          </Anchor>
        </Group>
      </Stack>
    </Paper>
  )
}
