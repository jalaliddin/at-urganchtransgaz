import { Card, Group, SimpleGrid, Stack, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { EmptyState } from '@/components/EmptyState'
import { PageHeader } from '@/components/PageHeader'
import { AssetStatusBadge } from './AssetBadges'
import { useMyAssets } from './queries'

export function MyAssetsPage() {
  const { t } = useTranslation('assets')
  const query = useMyAssets()

  return (
    <>
      <PageHeader title={t('mine.title')} description={t('mine.description')} />
      {query.data?.length === 0 && <EmptyState title={t('empty.mine')} />}
      <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
        {query.data?.map((a) => (
          <Card key={a.id} withBorder p="md">
            <Stack gap={4}>
              <Group justify="space-between">
                <Text ff="monospace" fw={600} size="sm">
                  {a.inventory_number}
                </Text>
                <AssetStatusBadge status={a.status} />
              </Group>
              <Text fw={500}>{a.name}</Text>
              <Text size="sm" c="dimmed">
                {[a.category?.name, a.serial_number && `S/N ${a.serial_number}`].filter(Boolean).join(' · ')}
              </Text>
              {(a.location || a.room) && (
                <Text size="sm" c="dimmed">
                  {[a.location?.name, a.room].filter(Boolean).join(', ')}
                </Text>
              )}
            </Stack>
          </Card>
        ))}
      </SimpleGrid>
    </>
  )
}
