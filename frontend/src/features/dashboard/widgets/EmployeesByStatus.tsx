import { Group, Paper, Progress, Stack, Text, Title, Tooltip } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { formatNumber } from '@/lib/format'
import type { DashboardData } from '../api'

const ORDER = ['active', 'on_leave', 'business_trip', 'dismissed'] as const

/**
 * Magnitude per status as a bar list in one hue: the label carries identity,
 * the bar only length, so colour never has to distinguish categories.
 */
export function EmployeesByStatus({ data }: { data: NonNullable<DashboardData['employees']> }) {
  const { t } = useTranslation(['dashboard', 'common'])
  const max = Math.max(1, ...ORDER.map((s) => data.by_status[s]))

  return (
    <Paper withBorder p="md">
      <Title order={2} size="h4" mb="md">
        {t('dashboard:employees.byStatus')}
      </Title>
      <Stack gap="sm">
        {ORDER.map((status) => {
          const count = data.by_status[status]
          const percent = data.total ? Math.round((count / data.total) * 100) : 0
          return (
            <div key={status}>
              <Group justify="space-between" mb={4}>
                <Text size="sm">{t(`common:status.${status}`)}</Text>
                <Text size="sm" fw={500} style={{ fontVariantNumeric: 'tabular-nums' }}>
                  {formatNumber(count)}
                </Text>
              </Group>
              <Tooltip label={t('dashboard:employees.share', { count, percent })} position="top-start">
                <Progress
                  value={(count / max) * 100}
                  color="cobalt"
                  size="md"
                  radius="sm"
                  aria-label={`${t(`common:status.${status}`)}: ${t('dashboard:employees.share', { count, percent })}`}
                />
              </Tooltip>
            </div>
          )
        })}
      </Stack>
    </Paper>
  )
}
