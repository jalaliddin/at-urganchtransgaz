import { Anchor, Button, Group, Paper, SimpleGrid, Skeleton, Tabs, Text } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconEdit, IconKey, IconPlayerPause, IconPlayerPlay } from '@tabler/icons-react'
import type { ReactNode } from 'react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { formatDateTime } from '@/lib/format'
import { useResetUserPassword, useSetUserActive, useUser } from './queries'
import { TemporaryPasswordModal } from './TemporaryPasswordModal'
import { UserAccessEditor } from './UserAccessEditor'
import { EditUserDrawer } from './UserFormDrawer'

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

export function UserDetailPage() {
  const { t } = useTranslation(['users', 'common'])
  const id = Number(useParams().id)
  const [params, setParams] = useSearchParams()
  const query = useUser(id)
  const setActive = useSetUserActive(id)
  const reset = useResetUserPassword(id)
  const [editing, setEditing] = useState(false)
  const [temporary, setTemporary] = useState<string | null>(null)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.isError) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  if (!query.data) return <Skeleton height={320} radius="md" />

  const user = query.data
  const tab = params.get('tab') === 'access' ? 'access' : 'info'

  const toggleActive = async () => {
    const activating = !user.is_active
    const ok = await confirmAction({
      message: t(activating ? 'confirm.activate' : 'confirm.deactivate', { name: user.name }),
      confirmLabel: t(activating ? 'actions.activate' : 'actions.deactivate'),
      danger: !activating,
    })
    if (!ok) return
    try {
      await setActive.mutateAsync(activating)
      notifications.show({
        color: 'teal',
        message: t(activating ? 'confirm.activated' : 'confirm.deactivated'),
      })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  const resetPassword = async () => {
    const ok = await confirmAction({
      message: t('confirm.resetPassword', { name: user.name }),
      confirmLabel: t('actions.resetPassword'),
      danger: true,
    })
    if (!ok) return
    try {
      setTemporary(await reset.mutateAsync())
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <>
      <PageHeader
        title={user.name}
        description={
          <Text span ff="monospace" size="sm">
            {user.username}
          </Text>
        }
        meta={
          <Group gap="xs">
            {!user.is_active ? (
              <StatusBadge status="inactive" label={t('status.inactive')} />
            ) : (
              <StatusBadge status="active" label={t('status.active')} />
            )}
            {user.must_change_password && (
              <StatusBadge status="must_change_password" label={t('status.mustChange')} />
            )}
          </Group>
        }
        actions={
          <>
            {user.can.update && (
              <Button variant="default" leftSection={<IconEdit size={16} />} onClick={() => setEditing(true)}>
                {t('actions.edit')}
              </Button>
            )}
            {user.can.reset_password && (
              <Button
                variant="default"
                leftSection={<IconKey size={16} />}
                onClick={() => void resetPassword()}
                loading={reset.isPending}
              >
                {t('actions.resetPassword')}
              </Button>
            )}
            {user.can.deactivate && (
              <Button
                variant="light"
                color={user.is_active ? 'red' : 'green'}
                leftSection={user.is_active ? <IconPlayerPause size={16} /> : <IconPlayerPlay size={16} />}
                onClick={() => void toggleActive()}
                loading={setActive.isPending}
              >
                {user.is_active ? t('actions.deactivate') : t('actions.activate')}
              </Button>
            )}
          </>
        }
      />
      <Tabs
        value={tab}
        onChange={(value) =>
          setParams(
            (prev) => {
              const next = new URLSearchParams(prev)
              if (value === 'access') next.set('tab', 'access')
              else next.delete('tab')
              return next
            },
            { replace: true },
          )
        }
        keepMounted={false}
      >
        <Tabs.List mb="md">
          <Tabs.Tab value="info">{t('tabs.info')}</Tabs.Tab>
          <Tabs.Tab value="access">{t('tabs.access')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="info">
          <Paper withBorder p="lg">
            <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
              <Field label={t('fields.organization')}>
                {user.organization ? (
                  <Anchor component={Link} to={`/organizations/${user.organization.id}`} size="sm">
                    {user.organization.name}
                  </Anchor>
                ) : (
                  t('system')
                )}
              </Field>
              <Field label={t('fields.employee')}>
                {user.employee && (
                  <Anchor component={Link} to={`/employees/${user.employee.id}`} size="sm">
                    {user.employee.full_name}
                  </Anchor>
                )}
              </Field>
              <Field label={t('fields.email')}>{user.email}</Field>
              <Field label={t('fields.roles')}>
                {user.roles.map((r) => r.display_name || r.name).join(', ')}
              </Field>
              <Field label={t('fields.locale')}>{t(`common:languages.${user.locale}`)}</Field>
              <Field label={t('fields.last_login_at')}>
                {user.last_login_at ? formatDateTime(user.last_login_at) : t('never')}
              </Field>
              <Field label={t('fields.created_at')}>{formatDateTime(user.created_at)}</Field>
            </SimpleGrid>
          </Paper>
        </Tabs.Panel>
        <Tabs.Panel value="access">
          <UserAccessEditor key={JSON.stringify([user.roles, user.access])} user={user} />
        </Tabs.Panel>
      </Tabs>
      <EditUserDrawer opened={editing} user={user} onClose={() => setEditing(false)} />
      <TemporaryPasswordModal
        password={temporary}
        username={user.username}
        onClose={() => setTemporary(null)}
      />
    </>
  )
}
