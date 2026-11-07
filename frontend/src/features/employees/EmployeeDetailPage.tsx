import { Anchor, Box, Button, Group, Paper, SimpleGrid, Skeleton, Text } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import type { ReactNode } from 'react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime } from '@/lib/format'
import { EmployeeAssetsBlock } from '@/features/assets/EmployeeAssetsBlock'
import { EmployeeFormDrawer } from './EmployeeFormDrawer'
import { useDeleteEmployee, useEmployee } from './queries'

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

export function EmployeeDetailPage() {
  const { t } = useTranslation(['employees', 'common'])
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const query = useEmployee(id)
  const remove = useDeleteEmployee()
  const canViewUsers = useCan('users.view')
  const [editing, setEditing] = useState(false)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.isError) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  if (!query.data) return <Skeleton height={320} radius="md" />

  const employee = query.data

  const onDelete = async () => {
    const ok = await confirmAction({
      message: t('delete.message', { name: employee.full_name }),
      confirmLabel: t('actions.delete'),
      danger: true,
    })
    if (!ok) return
    try {
      await remove.mutateAsync(employee.id)
      notifications.show({ color: 'teal', message: t('delete.done') })
      navigate('/employees', { replace: true })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <>
      <PageHeader
        title={employee.full_name}
        description={[employee.position, employee.organization?.name].filter(Boolean).join(', ')}
        meta={
          <Group gap="xs">
            <StatusBadge status={employee.status} />
          </Group>
        }
        actions={
          <>
            {employee.can.update && (
              <Button variant="default" leftSection={<IconEdit size={16} />} onClick={() => setEditing(true)}>
                {t('actions.edit')}
              </Button>
            )}
            {employee.can.delete && (
              <Button
                variant="light"
                color="red"
                leftSection={<IconTrash size={16} />}
                onClick={() => void onDelete()}
                loading={remove.isPending}
              >
                {t('actions.delete')}
              </Button>
            )}
          </>
        }
      />
      <Paper withBorder p="lg">
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
          <Field label={t('fields.organization')}>
            {employee.organization && (
              <Anchor component={Link} to={`/organizations/${employee.organization.id}`} size="sm">
                {employee.organization.name}
              </Anchor>
            )}
          </Field>
          <Field label={t('fields.department')}>{employee.department?.name}</Field>
          <Field label={t('fields.position')}>{employee.position}</Field>
          <Field label={t('fields.personnel_number')}>
            {employee.personnel_number && (
              <Text span ff="monospace" size="sm">
                {employee.personnel_number}
              </Text>
            )}
          </Field>
          <Field label={t('fields.phone')}>{employee.phone}</Field>
          <Field label={t('fields.internal_phone')}>{employee.internal_phone}</Field>
          <Field label={t('fields.email')}>{employee.email}</Field>
          <Field label={t('fields.hired_at')}>
            {employee.hired_at ? formatDate(employee.hired_at) : null}
          </Field>
          {employee.status === 'dismissed' && (
            <Field label={t('fields.dismissed_at')}>{formatDate(employee.dismissed_at)}</Field>
          )}
          <Field label={t('fields.user')}>
            {employee.user ? (
              canViewUsers ? (
                <Anchor component={Link} to={`/users/${employee.user.id}`} size="sm">
                  {employee.user.username}
                </Anchor>
              ) : (
                employee.user.username
              )
            ) : (
              t('noUser')
            )}
          </Field>
          <Field label={t('fields.created_at')}>{formatDateTime(employee.created_at)}</Field>
          <Field label={t('fields.updated_at')}>{formatDateTime(employee.updated_at)}</Field>
        </SimpleGrid>
        {employee.notes && (
          <Field label={t('fields.notes')}>
            <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>
              {employee.notes}
            </Text>
          </Field>
        )}
      </Paper>
      <Box mt="md">
        <EmployeeAssetsBlock employeeId={employee.id} dismissed={employee.status === 'dismissed'} />
      </Box>
      <EmployeeFormDrawer opened={editing} employee={employee} onClose={() => setEditing(false)} />
    </>
  )
}
