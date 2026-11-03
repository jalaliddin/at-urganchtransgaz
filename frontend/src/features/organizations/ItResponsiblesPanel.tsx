import { ActionIcon, Anchor, Badge, Button, Checkbox, Group, Modal, Paper, Stack, Table, Text, Textarea, Tooltip } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { IconTrash, IconUserPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import dayjs, { APP_TIMEZONE } from '@/lib/dayjs'
import { formatDate } from '@/lib/format'
import { useAssignItResponsible, useItResponsibles, useRemoveItResponsible } from './queries'
import type { Organization } from './types'

function AssignModal({ organization, opened, onClose }: { organization: Organization; opened: boolean; onClose: () => void }) {
  const { t } = useTranslation(['organizations', 'common'])
  const assign = useAssignItResponsible(organization.id)
  const [employeeId, setEmployeeId] = useState<number | null>(null)
  const [isPrimary, setIsPrimary] = useState(true)
  const [assignedAt, setAssignedAt] = useState<string | null>(dayjs().tz(APP_TIMEZONE).format('YYYY-MM-DD'))
  const [notes, setNotes] = useState('')
  const fieldErrors = assign.error instanceof ApiError ? assign.error.fieldErrors : {}

  const submit = async () => {
    if (!employeeId || !assignedAt) return
    try {
      await assign.mutateAsync({ employee_id: employeeId, is_primary: isPrimary, assigned_at: assignedAt, notes: notes || null })
      notifications.show({ color: 'teal', message: t('it.assigned') })
      setEmployeeId(null)
      setNotes('')
      onClose()
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 422)) notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <Modal opened={opened} onClose={onClose} title={<Text fw={600}>{t('it.assign')}</Text>}>
      <Stack gap="md">
        <EmployeeSelect label={t('it.employee')} value={employeeId} onChange={setEmployeeId} withAsterisk error={fieldErrors.employee_id?.[0]} />
        <DateInput label={t('it.assigned_at')} value={assignedAt} onChange={setAssignedAt} valueFormat="DD.MM.YYYY" withAsterisk error={fieldErrors.assigned_at?.[0]} />
        <Checkbox label={t('it.primary')} checked={isPrimary} onChange={(e) => setIsPrimary(e.currentTarget.checked)} />
        <Textarea label={t('it.notes')} value={notes} onChange={(e) => setNotes(e.currentTarget.value)} maxLength={500} autosize minRows={2} />
        <Group justify="flex-end">
          <Button variant="default" onClick={onClose}>
            {t('common:actions.cancel')}
          </Button>
          <Button onClick={() => void submit()} loading={assign.isPending} disabled={!employeeId || !assignedAt}>
            {t('it.assign')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function ItResponsiblesPanel({ organization }: { organization: Organization }) {
  const { t } = useTranslation(['organizations', 'common'])
  const list = useItResponsibles(organization.id)
  const remove = useRemoveItResponsible(organization.id)
  const [assigning, setAssigning] = useState(false)
  const canManage = organization.can.manage_it_staff

  const onRemove = async (employeeId: number, name: string) => {
    const ok = await confirmAction({ message: t('it.removeConfirm', { name }), confirmLabel: t('it.remove'), danger: true })
    if (!ok) return
    try {
      notifications.show({ color: 'teal', message: await remove.mutateAsync(employeeId) })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  if (list.isError) return <ErrorState error={list.error} onRetry={() => void list.refetch()} />

  const rows = list.data ?? []
  const assignButton = canManage && (
    <Button leftSection={<IconUserPlus size={16} />} variant="light" onClick={() => setAssigning(true)}>
      {t('it.assign')}
    </Button>
  )

  return (
    <Stack gap="sm">
      {rows.length > 0 && <Group justify="flex-end">{assignButton}</Group>}
      <Paper withBorder>
        {list.isPending ? (
          <Text p="md" c="dimmed" size="sm">
            {t('common:states.loading')}
          </Text>
        ) : rows.length === 0 ? (
          <EmptyState title={t('it.empty')} description={canManage ? t('it.emptyHint') : undefined} action={assignButton || undefined} />
        ) : (
          <Table.ScrollContainer minWidth={640}>
            <Table verticalSpacing="sm" horizontalSpacing="md">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('it.employee')}</Table.Th>
                  <Table.Th>{t('fields.phone')}</Table.Th>
                  <Table.Th>{t('it.assigned_at')}</Table.Th>
                  <Table.Th />
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {rows.map((row) => (
                  <Table.Tr key={row.id}>
                    <Table.Td>
                      <Group gap="xs" wrap="nowrap">
                        <Anchor component={Link} to={`/employees/${row.employee?.id ?? ''}`} size="sm" fw={500}>
                          {row.employee?.full_name}
                        </Anchor>
                        {row.is_primary && (
                          <Badge size="sm" variant="light" color="turquoise" tt="none">
                            {t('it.primary')}
                          </Badge>
                        )}
                      </Group>
                      <Text size="xs" c="dimmed">
                        {[row.employee?.position, row.employee?.organization?.name].filter(Boolean).join(', ')}
                      </Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{row.employee?.phone ?? '—'}</Text>
                    </Table.Td>
                    <Table.Td>
                      <Text size="sm">{formatDate(row.assigned_at)}</Text>
                    </Table.Td>
                    <Table.Td w={48}>
                      {canManage && row.employee && (
                        <Tooltip label={t('it.remove')}>
                          <ActionIcon
                            variant="subtle"
                            color="red"
                            aria-label={t('it.remove')}
                            onClick={() => void onRemove(row.employee?.id ?? 0, row.employee?.full_name ?? '')}
                          >
                            <IconTrash size={16} />
                          </ActionIcon>
                        </Tooltip>
                      )}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </Paper>
      <AssignModal organization={organization} opened={assigning} onClose={() => setAssigning(false)} />
    </Stack>
  )
}
