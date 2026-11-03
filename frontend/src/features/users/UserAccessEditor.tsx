import {
  ActionIcon,
  Alert,
  Button,
  Group,
  MultiSelect,
  Paper,
  Stack,
  Switch,
  Text,
  Title,
  Tooltip,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCurrentUser } from '@/features/auth/queries'
import { useRoles } from '@/features/roles/queries'
import { useCan } from '@/hooks/usePermissions'
import { useSyncUserAccess, useSyncUserRoles } from './queries'
import type { AccessRow, User } from './types'

type DraftRow = { key: number; organization_id: number | null; include_descendants: boolean }

/** Roles (what the user may do) and organization access (whose data they see), saved separately. */
export function UserAccessEditor({ user }: { user: User }) {
  const { t } = useTranslation(['users', 'common'])
  const me = useCurrentUser()
  const canViewRoles = useCan('roles.view')
  const roles = useRoles(canViewRoles)
  const syncRoles = useSyncUserRoles(user.id)
  const syncAccess = useSyncUserAccess(user.id)
  const editable = user.can.manage_access && me.id !== user.id

  // Initialised from the server state; the parent remounts this editor (key) when it changes.
  const [selectedRoles, setSelectedRoles] = useState(() => user.roles.map((r) => r.name))
  const [rows, setRows] = useState<DraftRow[]>(() =>
    user.access.map((a, i) => ({
      key: i,
      organization_id: a.organization_id,
      include_descendants: a.include_descendants,
    })),
  )

  const accessErrors = syncAccess.error instanceof ApiError ? syncAccess.error.fieldErrors : {}

  const saveRoles = async () => {
    try {
      await syncRoles.mutateAsync(selectedRoles)
      notifications.show({ color: 'teal', message: t('form.rolesSaved') })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  const saveAccess = async () => {
    const access: AccessRow[] = rows
      .filter((r): r is DraftRow & { organization_id: number } => r.organization_id !== null)
      .map((r) => ({ organization_id: r.organization_id, include_descendants: r.include_descendants }))
    try {
      await syncAccess.mutateAsync(access)
      notifications.show({ color: 'teal', message: t('form.accessSaved') })
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 422))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <Stack gap="lg">
      {me.id === user.id && (
        <Alert color="cobalt" variant="light">
          {t('hints.selfManage')}
        </Alert>
      )}

      <Paper withBorder p="lg">
        <Title order={2} size="h4" mb="sm">
          {t('fields.roles')}
        </Title>
        <Stack gap="sm" maw={640}>
          <MultiSelect
            aria-label={t('fields.roles')}
            data={(roles.data ?? user.roles.map((r) => ({ name: r.name, display_name: r.display_name }))).map(
              (r) => ({
                value: r.name,
                label: r.display_name || r.name,
              }),
            )}
            value={selectedRoles}
            onChange={setSelectedRoles}
            disabled={!editable || !canViewRoles}
            searchable
          />
          {editable && (
            <Group>
              <Button
                onClick={() => void saveRoles()}
                loading={syncRoles.isPending}
                disabled={selectedRoles.length === 0}
              >
                {t('actions.saveRoles')}
              </Button>
            </Group>
          )}
        </Stack>
      </Paper>

      <Paper withBorder p="lg">
        <Title order={2} size="h4" mb={4}>
          {t('fields.access')}
        </Title>
        <Text size="sm" c="dimmed" mb="md" maw={720}>
          {t('hints.access')}
        </Text>
        <Stack gap="sm">
          {rows.map((row, index) => (
            <Group key={row.key} gap="sm" wrap="wrap" align="flex-start">
              <OrganizationSelect
                aria-label={t('fields.organization')}
                value={row.organization_id}
                onChange={(value) =>
                  setRows((prev) =>
                    prev.map((r) => (r.key === row.key ? { ...r, organization_id: value } : r)),
                  )
                }
                disabled={!editable}
                w={{ base: '100%', sm: 360 }}
                error={accessErrors[`access.${index}.organization_id`]?.[0]}
              />
              <Switch
                mt={8}
                label={t('fields.include_descendants')}
                checked={row.include_descendants}
                onChange={(e) => {
                  const checked = e.currentTarget.checked
                  setRows((prev) =>
                    prev.map((r) => (r.key === row.key ? { ...r, include_descendants: checked } : r)),
                  )
                }}
                disabled={!editable}
                error={accessErrors[`access.${index}.include_descendants`]?.[0]}
              />
              {editable && (
                <Tooltip label={t('common:actions.remove')}>
                  <ActionIcon
                    mt={4}
                    variant="subtle"
                    color="red"
                    aria-label={t('common:actions.remove')}
                    onClick={() => setRows((prev) => prev.filter((r) => r.key !== row.key))}
                  >
                    <IconTrash size={16} />
                  </ActionIcon>
                </Tooltip>
              )}
            </Group>
          ))}
          {editable && (
            <Group>
              <Button
                variant="default"
                leftSection={<IconPlus size={16} />}
                onClick={() =>
                  setRows((prev) => [
                    ...prev,
                    { key: Date.now(), organization_id: null, include_descendants: false },
                  ])
                }
              >
                {t('actions.addAccess')}
              </Button>
              <Button onClick={() => void saveAccess()} loading={syncAccess.isPending}>
                {t('actions.saveAccess')}
              </Button>
            </Group>
          )}
        </Stack>
      </Paper>
    </Stack>
  )
}
