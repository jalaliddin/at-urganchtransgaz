import { Badge, Button, Card, Group, SimpleGrid, Table, Text, Title } from '@mantine/core'
import { IconPencil } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { formatDate } from '@/lib/format'
import { SoftwareFormModal } from './SoftwareFormModal'
import { useSoftware } from './queries'

export function SoftwareDetailPage() {
  const { t } = useTranslation('software')
  const id = Number(useParams().id)
  const query = useSoftware(id)
  const canManage = useCan('software.manage')
  const [editing, setEditing] = useState(false)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const sw = query.data
  if (!sw) return null

  return (
    <>
      <PageHeader
        title={sw.name}
        description={[sw.vendor, t(`categories.${sw.category}`), t(`licenseTypes.${sw.license_type}`)]
          .filter(Boolean)
          .join(', ')}
        meta={
          sw.is_antivirus ? (
            <Badge color="cobalt" variant="light" tt="none">
              {t('catalog.antivirus')}
            </Badge>
          ) : undefined
        }
        actions={
          canManage && (
            <Button variant="default" leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>
              {t('catalog.edit')}
            </Button>
          )
        }
      />
      <SimpleGrid cols={{ base: 1, md: 2 }} spacing="md">
        <Card withBorder p={0}>
          <Title order={3} fz="sm" p="md" pb={0}>
            {t('detail.versions', { count: sw.installations_count ?? 0 })}
          </Title>
          <Table verticalSpacing="xs">
            <Table.Tbody>
              {sw.versions.map((v) => (
                <Table.Tr key={v.version ?? '—'}>
                  <Table.Td ff="monospace">{v.version ?? t('detail.noVersion')}</Table.Td>
                  <Table.Td ta="right">{t('detail.devices', { count: v.devices })}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
          {sw.versions.length === 0 && (
            <Text size="sm" c="dimmed" p="md">
              {t('detail.notInstalled')}
            </Text>
          )}
        </Card>
        <Card withBorder p={0}>
          <Title order={3} fz="sm" p="md" pb={0}>
            {t('licenses.title')}
          </Title>
          <Table verticalSpacing="xs">
            <Table.Tbody>
              {sw.licenses.map((l) => (
                <Table.Tr key={l.id}>
                  <Table.Td>
                    <Text size="sm">{l.number ?? `#${l.id}`}</Text>
                    <Text size="xs" c="dimmed">
                      {l.organization.name}
                    </Text>
                  </Table.Td>
                  <Table.Td ta="right">{t('licenses.usage', { used: l.used, seats: l.seats })}</Table.Td>
                  <Table.Td>{l.expires_at ? formatDate(l.expires_at) : '—'}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
          {sw.licenses.length === 0 && (
            <Group p="md">
              <Text size="sm" c="dimmed">
                {t('licenses.empty')}
              </Text>
            </Group>
          )}
        </Card>
      </SimpleGrid>
      {editing && <SoftwareFormModal software={sw} onClose={() => setEditing(false)} />}
    </>
  )
}
