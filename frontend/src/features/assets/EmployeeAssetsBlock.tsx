import { Alert, Anchor, Card, Group, Stack, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCan } from '@/hooks/usePermissions'
import { AssetStatusBadge } from './AssetBadges'
import { useEmployeeAssets } from './queries'

/** Employee card: assets the person holds; a reminder to collect them after dismissal (spec §5.3). */
export function EmployeeAssetsBlock({ employeeId, dismissed }: { employeeId: number; dismissed: boolean }) {
  const { t } = useTranslation('assets')
  const canView = useCan('assets.view')
  const assets = useEmployeeAssets(employeeId, canView)
  if (!canView) return null
  const rows = assets.data ?? []

  return (
    <Card withBorder p="md">
      <Title order={3} fz="sm" mb="sm">
        {t('employeeBlock.title')} ({rows.length})
      </Title>
      {dismissed && rows.length > 0 && (
        <Alert color="orange" mb="sm">
          {t('employeeBlock.dismissed')}
        </Alert>
      )}
      {rows.length === 0 && (
        <Text size="sm" c="dimmed">
          {t('empty.noAssignments')}
        </Text>
      )}
      <Stack gap={6}>
        {rows.map((a) => (
          <Group key={a.id} justify="space-between" wrap="nowrap">
            <Group gap={6} wrap="nowrap" miw={0}>
              <Anchor component={Link} to={`/assets/${a.id}`} size="sm" ff="monospace">
                {a.inventory_number}
              </Anchor>
              <Text size="sm" truncate>
                {a.name}
              </Text>
            </Group>
            <AssetStatusBadge status={a.status} />
          </Group>
        ))}
      </Stack>
    </Card>
  )
}
