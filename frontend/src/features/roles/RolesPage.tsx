import { Anchor, Badge, Button, Paper, Skeleton, Table, Text } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useRoles } from './queries'

export function RolesPage() {
  const { t } = useTranslation('roles')
  const roles = useRoles()
  const canManage = useCan('roles.manage')

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          canManage && (
            <Button component={Link} to="/roles/new" leftSection={<IconPlus size={16} />}>
              {t('actions.create')}
            </Button>
          )
        }
      />
      {roles.isError ? (
        <ErrorState error={roles.error} onRetry={() => void roles.refetch()} />
      ) : !roles.data ? (
        <Skeleton height={320} radius="md" />
      ) : roles.data.length === 0 ? (
        <EmptyState title={t('empty.title')} />
      ) : (
        <Paper withBorder>
          <Table.ScrollContainer minWidth={640}>
            <Table verticalSpacing="sm" horizontalSpacing="md" highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.display_name')}</Table.Th>
                  <Table.Th>{t('fields.type')}</Table.Th>
                  <Table.Th ta="right">{t('fields.permissions')}</Table.Th>
                  <Table.Th ta="right">{t('fields.users')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {roles.data.map((role) => (
                  <Table.Tr key={role.id}>
                    <Table.Td>
                      <Anchor component={Link} to={`/roles/${role.id}`} size="sm" fw={500}>
                        {role.display_name || role.name}
                      </Anchor>
                      <Text size="xs" c="dimmed" ff="monospace">
                        {role.name}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Badge variant="light" color={role.is_system ? 'cobalt' : 'gray'} tt="none">
                        {role.is_system ? t('system') : t('custom')}
                      </Badge>
                    </Table.Td>
                    <Table.Td ta="right">{role.permissions.length}</Table.Td>
                    <Table.Td ta="right">{role.users_count ?? '—'}</Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Paper>
      )}
    </>
  )
}
