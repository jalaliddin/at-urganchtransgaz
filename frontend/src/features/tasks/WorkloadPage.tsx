import { Card, SimpleGrid, Table, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useOrgContext } from '@/hooks/useOrgContext'
import { useWorkload } from './queries'
import type { WorkloadCounts } from './types'

const KEYS = ['open', 'overdue', 'due_week', 'done_30'] as const

function Counts({ row }: { row: WorkloadCounts }) {
  return (
    <>
      {KEYS.map((k) => (
        <Table.Td
          key={k}
          ta="right"
          c={k === 'overdue' && row.overdue > 0 ? 'red.7' : undefined}
          fw={k === 'overdue' && row.overdue > 0 ? 600 : undefined}
        >
          {row[k]}
        </Table.Td>
      ))}
    </>
  )
}

/** Load per IT staff member and per organization (central IT head). */
export function WorkloadPage() {
  const { t } = useTranslation(['tasks', 'common'])
  const { organizationId } = useOrgContext()
  const params: Record<string, string | number> = {}
  if (organizationId) params['filter[organization]'] = organizationId
  const data = useWorkload(params)
  const head = (first: string) => (
    <Table.Thead>
      <Table.Tr>
        <Table.Th>{first}</Table.Th>
        {KEYS.map((k) => (
          <Table.Th key={k} ta="right">
            {t(`workload.${k}`)}
          </Table.Th>
        ))}
      </Table.Tr>
    </Table.Thead>
  )

  return (
    <>
      <PageHeader
        title={t('workload.title')}
        description={t('workload.description')}
        actions={
          <ExportMenu
            type="task_workload"
            filters={organizationId ? { organization: String(organizationId) } : {}}
          />
        }
      />
      {data.error && <ErrorState error={data.error} onRetry={() => void data.refetch()} />}
      <SimpleGrid cols={{ base: 1, lg: 2 }}>
        <Card withBorder>
          <Title order={4} mb="sm">
            {t('workload.byPerson')}
          </Title>
          <Table.ScrollContainer minWidth={420}>
            <Table striped>
              {head(t('fields.assignee'))}
              <Table.Tbody>
                {data.data?.by_assignee.map((r) => (
                  <Table.Tr key={r.user?.id ?? 0}>
                    <Table.Td>
                      {r.user?.name ?? (
                        <Text c="dimmed" size="sm">
                          {t('workload.unassigned')}
                        </Text>
                      )}
                    </Table.Td>
                    <Counts row={r} />
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
        <Card withBorder>
          <Title order={4} mb="sm">
            {t('workload.byOrganization')}
          </Title>
          <Table.ScrollContainer minWidth={420}>
            <Table striped>
              {head(t('fields.organization'))}
              <Table.Tbody>
                {data.data?.by_organization.map((r) => (
                  <Table.Tr key={r.organization.id}>
                    <Table.Td>
                      <Text component={Link} to={`/organizations/${r.organization.id}`} size="sm">
                        {r.organization.name}
                      </Text>
                    </Table.Td>
                    <Counts row={r} />
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
      </SimpleGrid>
    </>
  )
}
