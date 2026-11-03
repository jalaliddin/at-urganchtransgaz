import {
  Anchor,
  Button,
  Paper,
  SegmentedControl,
  SimpleGrid,
  Stack,
  Text,
  Title,
  useComputedColorScheme,
  useMantineColorScheme,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconKey } from '@tabler/icons-react'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { PageHeader } from '@/components/PageHeader'
import { useCurrentUser, useUpdateMyLocale } from '@/features/auth/queries'
import { isLanguage } from '@/i18n'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" component="div">
        {children || '—'}
      </Text>
    </div>
  )
}

export function ProfilePage() {
  const { t } = useTranslation(['profile', 'common'])
  const me = useCurrentUser()
  const updateLocale = useUpdateMyLocale()
  const { setColorScheme } = useMantineColorScheme()
  const scheme = useComputedColorScheme('light')

  return (
    <>
      <PageHeader title={me.name} description={t('description')} />
      <Stack gap="lg">
        <Paper withBorder p="lg">
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
            <Field label={t('fields.username')}>
              <Text span ff="monospace" size="sm">
                {me.username}
              </Text>
            </Field>
            <Field label={t('fields.email')}>{me.email}</Field>
            <Field label={t('fields.organization')}>{me.organization?.name}</Field>
            <Field label={t('fields.employee')}>
              {me.employee && (
                <Anchor component={Link} to={`/employees/${me.employee.id}`} size="sm">
                  {me.employee.full_name}
                </Anchor>
              )}
            </Field>
            <Field label={t('fields.roles')}>
              {me.roles.map((r) => r.display_name || r.name).join(', ')}
            </Field>
            <Field label={t('fields.access')}>
              {me.access.map((a) => (
                <div key={a.organization_id}>
                  {a.organization_name}
                  {a.include_descendants ? ` (${t('fields.withDescendants')})` : ''}
                </div>
              ))}
            </Field>
          </SimpleGrid>
        </Paper>

        <SimpleGrid cols={{ base: 1, md: 2 }} spacing="lg">
          <Paper withBorder p="lg">
            <Title order={2} size="h4" mb="md">
              {t('preferences.title')}
            </Title>
            <Stack gap="md">
              <div>
                <Text size="sm" fw={500} mb={6} id="language-label">
                  {t('preferences.language')}
                </Text>
                <SegmentedControl
                  aria-labelledby="language-label"
                  value={me.locale}
                  onChange={(value) =>
                    isLanguage(value) &&
                    updateLocale.mutate(value, {
                      onSuccess: () => notifications.show({ color: 'teal', message: t('preferences.saved') }),
                    })
                  }
                  data={[
                    { value: 'uz', label: t('common:languages.uz') },
                    { value: 'ru', label: t('common:languages.ru') },
                  ]}
                  disabled={updateLocale.isPending}
                />
              </div>
              <div>
                <Text size="sm" fw={500} mb={6} id="theme-label">
                  {t('preferences.theme')}
                </Text>
                <SegmentedControl
                  aria-labelledby="theme-label"
                  value={scheme}
                  onChange={(value) => setColorScheme(value === 'dark' ? 'dark' : 'light')}
                  data={[
                    { value: 'light', label: t('preferences.light') },
                    { value: 'dark', label: t('preferences.dark') },
                  ]}
                />
              </div>
            </Stack>
          </Paper>
          <Paper withBorder p="lg">
            <Title order={2} size="h4" mb="xs">
              {t('security.title')}
            </Title>
            <Text size="sm" c="dimmed" mb="md">
              {t('security.hint')}
            </Text>
            <Button
              component={Link}
              to="/change-password"
              variant="default"
              leftSection={<IconKey size={16} />}
            >
              {t('security.changePassword')}
            </Button>
          </Paper>
        </SimpleGrid>
      </Stack>
    </>
  )
}
