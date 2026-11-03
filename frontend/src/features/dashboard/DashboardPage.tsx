import { Button, Stack, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { useCurrentUser, useLogout } from '@/features/auth/queries'

export function DashboardPage() {
  const { t } = useTranslation()
  const me = useCurrentUser()
  const logout = useLogout()

  return (
    <Stack p="xl" align="flex-start">
      <Title order={1}>{me.name}</Title>
      <Text c="dimmed">{me.organization?.name}</Text>
      <Button variant="default" onClick={() => logout.mutate()} loading={logout.isPending}>
        {t('actions.logout')}
      </Button>
    </Stack>
  )
}
