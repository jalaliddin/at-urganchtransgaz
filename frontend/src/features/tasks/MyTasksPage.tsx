import { Checkbox, Group, Stack, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCurrentUser } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { useTaskList } from './queries'
import { OverdueBadge, PriorityBadge } from './TaskBadges'
import type { Card } from './types'

/** Open tasks where I am the responsible person, across all boards. */
export function MyTasksPage() {
  const { t } = useTranslation(['tasks', 'common'])
  const navigate = useNavigate()
  const me = useCurrentUser()
  const canExport = useCan('tasks.export')
  const params = useTableParams({ defaultSort: 'due_date', filterKeys: ['overdue', 'all'] })
  const apiParams: Record<string, string | number> = { ...params.apiParams, 'filter[mine]': 1 }
  if (params.filters.all !== '1') apiParams['filter[open]'] = 1
  const list = useTaskList(apiParams)

  return (
    <>
      <PageHeader
        title={t('my.title')}
        description={t('my.description')}
        actions={
          canExport && (
            <ExportMenu
              type="tasks"
              filters={{ assignee: String(me.id), open: params.filters.all === '1' ? '' : '1' }}
            />
          )
        }
      />
      <DataTable<Card>
        storeKey="my-tasks"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('my.empty')}
        onRowClick={(c) => navigate(`/tasks/${c.id}`)}
        toolbar={
          <Group gap="md">
            <Checkbox
              label={t('board.overdueOnly')}
              checked={params.filters.overdue === '1'}
              onChange={(e) => params.setFilter('overdue', e.currentTarget.checked ? '1' : null)}
            />
            <Checkbox
              label={t('my.withDone')}
              checked={params.filters.all === '1'}
              onChange={(e) => params.setFilter('all', e.currentTarget.checked ? '1' : null)}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'title',
            title: t('fields.title'),
            render: (c) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {c.title}
                </Text>
                <Text size="xs" c="dimmed" ff="monospace">
                  {c.number}
                </Text>
              </Stack>
            ),
          },
          { accessor: 'organization', title: t('fields.organization'), render: (c) => c.organization.name },
          {
            accessor: 'due_date',
            title: t('fields.dueDate'),
            noWrap: true,
            render: (c) => (
              <Group gap={6} wrap="nowrap">
                <Text size="sm">{c.due_date ? formatDate(c.due_date) : '—'}</Text>
                {c.is_overdue && <OverdueBadge />}
              </Group>
            ),
          },
          {
            accessor: 'priority',
            title: t('fields.priority'),
            render: (c) => <PriorityBadge priority={c.priority} />,
          },
        ]}
      />
    </>
  )
}
