import {
  ActionIcon,
  Anchor,
  Divider,
  Group,
  Indicator,
  Menu,
  ScrollArea,
  Stack,
  Text,
  UnstyledButton,
} from '@mantine/core'
import { IconBell } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
  useUnreadCount,
} from '@/features/notifications/queries'
import type { AppNotification } from '@/features/notifications/types'
import { formatDateTime } from '@/lib/format'

export function NotificationsMenu() {
  const { t } = useTranslation(['nav', 'common'])
  const navigate = useNavigate()
  const unread = useUnreadCount()
  const recent = useNotifications({ per_page: 8 })
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()
  const count = unread.data ?? 0

  const open = (notification: AppNotification) => {
    if (!notification.read_at) markRead.mutate(notification.id)
    if (notification.url) navigate(notification.url)
  }

  return (
    <Menu width={360} position="bottom-end" shadow="md">
      <Menu.Target>
        <Indicator label={count > 99 ? '99+' : count} size={16} disabled={count === 0} color="red" offset={4}>
          <ActionIcon variant="subtle" color="gray" size="lg" aria-label={t('nav:notifications.open')}>
            <IconBell size={20} stroke={1.6} />
          </ActionIcon>
        </Indicator>
      </Menu.Target>
      <Menu.Dropdown p={0}>
        <Group justify="space-between" px="md" py="sm">
          <Text fw={600} size="sm">
            {t('nav:items.notifications')}
          </Text>
          {count > 0 && (
            <Anchor component="button" type="button" size="xs" onClick={() => markAll.mutate()}>
              {t('nav:notifications.markAll')}
            </Anchor>
          )}
        </Group>
        <Divider />
        <ScrollArea.Autosize mah={360}>
          {recent.data?.data.length ? (
            <Stack gap={0}>
              {recent.data.data.map((n) => (
                <UnstyledButton
                  key={n.id}
                  onClick={() => open(n)}
                  px="md"
                  py="sm"
                  style={{ borderBottom: '1px solid var(--mantine-color-default-border)' }}
                >
                  <Text size="sm" fw={n.read_at ? 400 : 600}>
                    {n.title_key ? t(`common:${n.title_key}`, n.params) : n.type}
                  </Text>
                  <Text size="xs" c="dimmed">
                    {formatDateTime(n.created_at)}
                  </Text>
                </UnstyledButton>
              ))}
            </Stack>
          ) : (
            <Text size="sm" c="dimmed" px="md" py="lg" ta="center">
              {t('nav:notifications.empty')}
            </Text>
          )}
        </ScrollArea.Autosize>
        <Divider />
        <Anchor component={Link} to="/notifications" size="sm" display="block" ta="center" py="sm">
          {t('nav:notifications.all')}
        </Anchor>
      </Menu.Dropdown>
    </Menu>
  )
}
