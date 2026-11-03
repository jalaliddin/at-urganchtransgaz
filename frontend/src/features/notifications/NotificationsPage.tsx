import {
  Badge,
  Button,
  Group,
  Paper,
  SegmentedControl,
  Skeleton,
  Stack,
  Text,
  UnstyledButton,
} from '@mantine/core'
import { IconChecks } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { formatDateTime } from '@/lib/format'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
  useUnreadCount,
} from './queries'
import type { AppNotification } from './types'

export function NotificationsPage() {
  const { t } = useTranslation(['notifications', 'nav', 'common'])
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const onlyUnread = params.get('unread') === '1'
  const list = useNotifications(onlyUnread ? { per_page: 50, 'filter[unread]': 1 } : { per_page: 50 })
  const unread = useUnreadCount()
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()

  const open = (n: AppNotification) => {
    if (!n.read_at) markRead.mutate(n.id)
    if (n.url) navigate(n.url)
  }

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          (unread.data ?? 0) > 0 && (
            <Button
              variant="default"
              leftSection={<IconChecks size={16} />}
              onClick={() => markAll.mutate()}
              loading={markAll.isPending}
            >
              {t('nav:notifications.markAll')}
            </Button>
          )
        }
      />
      <Stack gap="sm">
        <SegmentedControl
          w="fit-content"
          value={onlyUnread ? 'unread' : 'all'}
          onChange={(v) => setParams(v === 'unread' ? { unread: '1' } : {}, { replace: true })}
          data={[
            { value: 'all', label: t('filters.all') },
            { value: 'unread', label: t('filters.unread') },
          ]}
        />
        {list.isError ? (
          <ErrorState error={list.error} onRetry={() => void list.refetch()} />
        ) : !list.data ? (
          <Skeleton height={240} radius="md" />
        ) : list.data.data.length === 0 ? (
          <Paper withBorder>
            <EmptyState title={t('empty.title')} description={t('empty.description')} />
          </Paper>
        ) : (
          <Paper withBorder>
            {list.data.data.map((n) => (
              <UnstyledButton
                key={n.id}
                onClick={() => open(n)}
                w="100%"
                px="md"
                py="sm"
                style={{ borderBottom: '1px solid var(--mantine-color-default-border)', display: 'block' }}
              >
                <Group justify="space-between" wrap="nowrap" gap="md">
                  <div>
                    <Text size="sm" fw={n.read_at ? 400 : 600}>
                      {n.title_key ? t(`common:${n.title_key}`, n.params) : n.type}
                    </Text>
                    <Text size="xs" c="dimmed">
                      {formatDateTime(n.created_at)}
                    </Text>
                  </div>
                  {!n.read_at && (
                    <Badge color="cobalt" variant="light" tt="none">
                      {t('unread')}
                    </Badge>
                  )}
                </Group>
              </UnstyledButton>
            ))}
          </Paper>
        )}
      </Stack>
    </>
  )
}
