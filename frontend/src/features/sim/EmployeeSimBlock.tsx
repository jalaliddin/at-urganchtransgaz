import { Anchor, Group, Paper, Stack, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCan } from '@/hooks/usePermissions'
import { useEmployeeSims } from './queries'
import { SimStatusBadge } from './SimBadges'
import { formatPhone } from './types'

/** SIM cards the employee holds now (on the employee card). */
export function EmployeeSimBlock({ employeeId }: { employeeId: number }) {
  const { t } = useTranslation('sim')
  const canSee = useCan(['sim.view', 'sim.assign', 'sim.manage'], 'any')
  const cards = useEmployeeSims(employeeId, canSee)
  if (!canSee) return null
  return (
    <Paper withBorder p="md" mt="md">
      <Stack gap="xs">
        <Text fw={600}>{t('employee.title')}</Text>
        {cards.data?.length === 0 && (
          <Text size="sm" c="dimmed">
            {t('employee.empty')}
          </Text>
        )}
        {cards.data?.map((c) => (
          <Group key={c.id} gap="sm" justify="space-between" wrap="nowrap">
            <Anchor component={Link} to={`/sim/${c.id}`} size="sm">
              {formatPhone(c.phone_number)}
            </Anchor>
            <Group gap="xs" wrap="nowrap">
              <Text size="sm" c="dimmed">
                {c.provider.name}
              </Text>
              <SimStatusBadge status={c.status} />
            </Group>
          </Group>
        ))}
      </Stack>
    </Paper>
  )
}
