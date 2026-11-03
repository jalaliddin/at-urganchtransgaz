import {
  Avatar,
  Group,
  Menu,
  Text,
  UnstyledButton,
  useComputedColorScheme,
  useMantineColorScheme,
} from '@mantine/core'
import { IconKey, IconLanguage, IconLogout, IconMoon, IconSun, IconUser } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser, useLogout, useUpdateMyLocale } from '@/features/auth/queries'
import { SUPPORTED_LANGUAGES } from '@/i18n'
import { useUiStore } from '@/stores/ui'

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export function UserMenu() {
  const { t } = useTranslation(['nav', 'common'])
  const me = useCurrentUser()
  const logout = useLogout()
  const updateLocale = useUpdateMyLocale()
  const setOrgContext = useUiStore((s) => s.setOrgContext)
  const { setColorScheme } = useMantineColorScheme()
  const scheme = useComputedColorScheme('light')

  const signOut = () => {
    setOrgContext(null)
    logout.mutate()
  }

  return (
    <Menu width={260} position="bottom-end" shadow="md">
      <Menu.Target>
        <UnstyledButton aria-label={t('nav:user.menu')}>
          <Group gap="xs" wrap="nowrap">
            <Avatar color="cobalt" radius="xl" size={34}>
              {initials(me.name)}
            </Avatar>
            <Text size="sm" fw={500} visibleFrom="md" maw={160} truncate>
              {me.name}
            </Text>
          </Group>
        </UnstyledButton>
      </Menu.Target>
      <Menu.Dropdown>
        <Menu.Label>
          <Text size="sm" fw={600} c="var(--mantine-color-text)">
            {me.name}
          </Text>
          <Text size="xs" c="dimmed">
            {me.username} · {me.roles.map((r) => r.display_name).join(', ')}
          </Text>
        </Menu.Label>
        <Menu.Divider />
        <Menu.Item component={Link} to="/profile" leftSection={<IconUser size={16} />}>
          {t('nav:items.profile')}
        </Menu.Item>
        <Menu.Item component={Link} to="/change-password" leftSection={<IconKey size={16} />}>
          {t('nav:items.changePassword')}
        </Menu.Item>
        <Menu.Item
          leftSection={scheme === 'dark' ? <IconSun size={16} /> : <IconMoon size={16} />}
          onClick={() => setColorScheme(scheme === 'dark' ? 'light' : 'dark')}
        >
          {scheme === 'dark' ? t('nav:user.lightTheme') : t('nav:user.darkTheme')}
        </Menu.Item>
        <Menu.Divider />
        <Menu.Label>
          <Group gap={6}>
            <IconLanguage size={14} />
            {t('nav:user.language')}
          </Group>
        </Menu.Label>
        {SUPPORTED_LANGUAGES.map((language) => (
          <Menu.Item
            key={language}
            onClick={() => updateLocale.mutate(language)}
            fw={me.locale === language ? 600 : undefined}
            aria-current={me.locale === language ? 'true' : undefined}
          >
            {t(`common:languages.${language}`)}
          </Menu.Item>
        ))}
        <Menu.Divider />
        <Menu.Item color="red" leftSection={<IconLogout size={16} />} onClick={signOut}>
          {t('common:actions.logout')}
        </Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )
}
