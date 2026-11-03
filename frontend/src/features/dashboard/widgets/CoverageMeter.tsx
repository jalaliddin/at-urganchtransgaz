import { Anchor, Group, Paper, Progress, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconCircleCheck } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import type { DashboardData } from '../api'

/** Share of active organizations that have an IT responsible assigned. */
export function CoverageMeter({ data }: { data: NonNullable<DashboardData['it_coverage']> }) {
  const { t } = useTranslation('dashboard')
  const total = data.with_responsible + data.without_responsible
  const percent = total ? (data.with_responsible / total) * 100 : 100
  const missing = data.without_responsible

  return (
    <Paper withBorder p="md">
      <Stack gap={6}>
        <Text size="sm" c="dimmed" fw={500}>
          {t('coverage.title')}
        </Text>
        <Text size="sm">{t('coverage.assigned', { with: data.with_responsible, total })}</Text>
        <Progress value={percent} color="turquoise" size="md" radius="sm" aria-label={t('coverage.title')} />
        <Group gap={6} wrap="nowrap" mt={2}>
          {missing > 0 ? (
            <IconAlertTriangle size={16} color="var(--mantine-color-orange-7)" aria-hidden />
          ) : (
            <IconCircleCheck size={16} color="var(--mantine-color-green-7)" aria-hidden />
          )}
          <Text size="sm">{missing > 0 ? t('coverage.missing', { count: missing }) : t('coverage.complete')}</Text>
        </Group>
        {missing > 0 && (
          <Anchor component={Link} to="/organizations?view=list&filter[is_active]=1" size="sm">
            {t('coverage.open')}
          </Anchor>
        )}
      </Stack>
    </Paper>
  )
}
