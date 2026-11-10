import { ActionIcon, Badge, Button, Group, Stack, Text } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import * as m from './api'
import { PlanFormModal } from './PlanFormModal'
import { useMaintenanceMutation, usePlan, usePlans } from './queries'
import type { PlanRow } from './types'

function EditPlan({ id, onClose }: { id: number; onClose: () => void }) {
  const plan = usePlan(id)
  return plan.data ? <PlanFormModal plan={plan.data} onClose={onClose} /> : null
}

export function PlansPage() {
  const { t } = useTranslation(['maintenance', 'common'])
  const canManage = useCan('maintenance.manage')
  const params = useTableParams({
    defaultSort: 'title',
    filterKeys: ['active', 'organization'],
    organizationContext: true,
  })
  const list = usePlans(params.apiParams)
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const remove = useMaintenanceMutation((id: number) => m.deletePlan(id))

  return (
    <>
      <PageHeader
        title={t('plans.title')}
        description={t('plans.description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing('new')}>
              {t('plans.new')}
            </Button>
          )
        }
      />
      <DataTable<PlanRow>
        storeKey="maintenance-plans"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('plans.empty')}
        columns={[
          {
            accessor: 'title',
            title: t('fields.plan'),
            sortable: false,
            render: (p) => (
              <Stack gap={0}>
                <Group gap={6}>
                  <Text size="sm" fw={500}>
                    {p.title}
                  </Text>
                  {!p.is_active && (
                    <Badge size="xs" color="gray" variant="outline" tt="none">
                      {t('plans.inactive')}
                    </Badge>
                  )}
                </Group>
                <Text size="xs" c="dimmed">
                  {p.organization.name}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'interval',
            title: t('fields.interval'),
            render: (p) => t('plans.every', { count: p.interval_count, unit: t(`unit.${p.interval_unit}`) }),
          },
          {
            accessor: 'assets_count',
            title: t('fields.assets'),
            textAlign: 'right',
            render: (p) => p.assets_count,
          },
          { accessor: 'assignee', title: t('fields.assignee'), render: (p) => p.assignee?.name ?? '—' },
          {
            accessor: 'next_due',
            title: t('fields.nextDue'),
            noWrap: true,
            render: (p) => (
              <Stack gap={0}>
                <Text size="sm">{formatDate(p.next_due)}</Text>
                {p.overdue_count > 0 && (
                  <Text size="xs" c="red.8">
                    {t('plans.overdue', { count: p.overdue_count })}
                  </Text>
                )}
              </Stack>
            ),
          },
          ...(canManage
            ? [
                {
                  accessor: 'actions',
                  title: '',
                  textAlign: 'right' as const,
                  render: (p: PlanRow) => (
                    <Group gap={4} justify="flex-end" wrap="nowrap">
                      <ActionIcon
                        variant="subtle"
                        aria-label={t('common:actions.edit')}
                        onClick={() => setEditing(p.id)}
                      >
                        <IconPencil size={16} />
                      </ActionIcon>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={t('common:actions.delete')}
                        onClick={async () => {
                          if (
                            await confirmAction({
                              message: t('plans.deleteConfirm', { title: p.title }),
                              confirmLabel: t('common:actions.delete'),
                              danger: true,
                            })
                          )
                            remove.mutate(p.id, {
                              onError: (e) =>
                                notifications.show({ color: 'red', message: toApiError(e).message }),
                            })
                        }}
                      >
                        <IconTrash size={16} />
                      </ActionIcon>
                    </Group>
                  ),
                },
              ]
            : []),
        ]}
      />
      {editing === 'new' && <PlanFormModal onClose={() => setEditing(null)} />}
      {typeof editing === 'number' && <EditPlan id={editing} onClose={() => setEditing(null)} />}
    </>
  )
}
