import { Badge, Button, Group, Select, Stack, Switch, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { useWork } from './queries'
import { TASK_STATUSES, type WorkItem } from './types'
import { CompleteModal, SkipModal } from './WorkDialogs'

const STATUS_COLOR = { planned: 'cobalt', done: 'green', skipped: 'orange', cancelled: 'gray' } as const

/** Scheduled service of servers, UPS, printers and network devices (design M2). */
export function MaintenancePage() {
  const { t } = useTranslation(['maintenance', 'common'])
  const canExport = useCan('maintenance.export')
  const params = useTableParams({
    defaultSort: 'due_on',
    filterKeys: ['status', 'overdue', 'mine', 'plan', 'organization'],
    organizationContext: true,
  })
  const list = useWork(params.apiParams)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)
  const [dialog, setDialog] = useState<{ item: WorkItem; kind: 'complete' | 'skip' } | null>(null)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="maintenance_tasks" filters={params.filters} />}
            <Button variant="default" component={Link} to="/maintenance/plans">
              {t('plans.title')}
            </Button>
          </Group>
        }
      />
      <DataTable<WorkItem>
        storeKey="maintenance-work"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('search')}
              aria-label={t('search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 240 }}
            />
            <Select
              placeholder={t('fields.status')}
              aria-label={t('fields.status')}
              clearable
              data={TASK_STATUSES.map((v) => ({ value: v, label: t(`status.${v}`) }))}
              value={params.filters.status ?? null}
              onChange={(v) => params.setFilter('status', v)}
              w={{ base: '100%', sm: 170 }}
            />
            <Switch
              label={t('overdueOnly')}
              checked={params.filters.overdue === '1'}
              onChange={(e) => params.setFilter('overdue', e.currentTarget.checked ? '1' : null)}
            />
            <Switch
              label={t('mine')}
              checked={params.filters.mine === '1'}
              onChange={(e) => params.setFilter('mine', e.currentTarget.checked ? '1' : null)}
            />
          </Group>
        }
        columns={[
          {
            accessor: 'due_on',
            title: t('fields.due'),
            sortable: false,
            noWrap: true,
            render: (w) => (
              <Group gap={6} wrap="nowrap">
                <Text size="sm" c={w.overdue ? 'red.8' : undefined} fw={w.overdue ? 600 : undefined}>
                  {formatDate(w.due_on)}
                </Text>
                {w.overdue && (
                  <Badge size="xs" color="red" variant="light" tt="none">
                    {t('overdue')}
                  </Badge>
                )}
              </Group>
            ),
          },
          {
            accessor: 'asset',
            title: t('fields.asset'),
            render: (w) => (
              <Stack gap={0}>
                <Text size="sm" fw={500}>
                  {w.asset.name}
                </Text>
                <Text size="xs" c="dimmed">
                  <Text span ff="monospace" inherit>
                    {w.asset.inventory_number}
                  </Text>
                  , {w.organization.name}
                </Text>
              </Stack>
            ),
          },
          { accessor: 'plan', title: t('fields.plan'), render: (w) => w.plan.title },
          { accessor: 'assignee', title: t('fields.assignee'), render: (w) => w.plan.assignee?.name ?? '—' },
          {
            accessor: 'status',
            title: t('fields.status'),
            render: (w) => (
              <Stack gap={0}>
                <Badge color={STATUS_COLOR[w.status]} variant="light" tt="none">
                  {t(`status.${w.status}`)}
                </Badge>
                {w.result && (
                  <Text size="xs" c={w.result === 'issues' ? 'orange.8' : 'dimmed'}>
                    {t(`result.${w.result}`)}
                    {w.completed_on ? `, ${formatDate(w.completed_on)}` : ''}
                  </Text>
                )}
              </Stack>
            ),
          },
          {
            accessor: 'actions',
            title: '',
            textAlign: 'right',
            render: (w) =>
              w.can.perform && (
                <Group gap={4} justify="flex-end" wrap="nowrap">
                  <Button size="compact-xs" onClick={() => setDialog({ item: w, kind: 'complete' })}>
                    {t('complete.action')}
                  </Button>
                  <Button
                    size="compact-xs"
                    variant="subtle"
                    color="orange"
                    onClick={() => setDialog({ item: w, kind: 'skip' })}
                  >
                    {t('skip.action')}
                  </Button>
                </Group>
              ),
          },
        ]}
      />
      {dialog?.kind === 'complete' && <CompleteModal item={dialog.item} onClose={() => setDialog(null)} />}
      {dialog?.kind === 'skip' && <SkipModal item={dialog.item} onClose={() => setDialog(null)} />}
    </>
  )
}
