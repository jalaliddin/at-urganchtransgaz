import { Anchor, Button, Checkbox, Group, Modal, Progress, Stack, Text, TextInput } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDateTime } from '@/lib/format'
import { startSession } from './api'
import { useAssetMutation, useSessions } from './queries'
import type { InventorySession } from './types'

function StartModal({ onClose }: { onClose: (id?: number) => void }) {
  const { t } = useTranslation(['assets', 'common', 'validation'])
  const { data: me } = useMe()
  const [organizationId, setOrganizationId] = useState<number | null>(me?.organization?.id ?? null)
  const [name, setName] = useState('')
  const [descendants, setDescendants] = useState(true)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const start = useAssetMutation(() =>
    startSession({ organization_id: organizationId ?? 0, name, include_descendants: descendants }),
  )

  return (
    <Modal opened onClose={() => onClose()} title={t('inventory.start')} centered>
      <Stack>
        <OrganizationSelect
          label={t('fields.organization')}
          required
          onlyActive
          value={organizationId}
          onChange={setOrganizationId}
          error={errors.organization_id}
        />
        <TextInput
          label={t('inventory.name')}
          required
          value={name}
          onChange={(e) => setName(e.currentTarget.value)}
          error={errors.name}
        />
        <Checkbox
          label={t('inventory.includeDescendants')}
          checked={descendants}
          onChange={(e) => setDescendants(e.currentTarget.checked)}
        />
        <Group justify="flex-end">
          <Button
            loading={start.isPending}
            onClick={() => {
              const local: Record<string, string> = {}
              if (organizationId === null) local.organization_id = t('validation:required')
              if (name.trim() === '') local.name = t('validation:required')
              setErrors(local)
              if (Object.keys(local).length > 0) return
              start.mutate(undefined, {
                onSuccess: (s) => onClose(s.id),
                onError: (e) => {
                  const api = toApiError(e)
                  if (e instanceof ApiError && e.status === 422)
                    setErrors(
                      Object.fromEntries(Object.entries(api.fieldErrors).map(([k, v]) => [k, v[0] ?? ''])),
                    )
                  else notifications.show({ color: 'red', message: api.message })
                },
              })
            }}
          >
            {t('inventory.start')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function InventoryPage() {
  const { t } = useTranslation('assets')
  const navigate = useNavigate()
  const canStart = useCan('assets.inventory')
  const params = useTableParams({ defaultSort: '-started_at' })
  const list = useSessions(params.apiParams)
  const [starting, setStarting] = useState(false)

  return (
    <>
      <PageHeader
        title={t('inventory.title')}
        description={t('inventory.description')}
        actions={
          canStart && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setStarting(true)}>
              {t('inventory.start')}
            </Button>
          )
        }
      />
      <DataTable<InventorySession>
        storeKey="inventory-sessions"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('inventory.empty')}
        onRowClick={(s) => navigate(`/assets/inventory/${s.id}`)}
        columns={[
          {
            accessor: 'name',
            title: t('inventory.name'),
            render: (s) => (
              <Stack gap={0}>
                <Anchor
                  component={Link}
                  to={`/assets/inventory/${s.id}`}
                  onClick={(e) => e.stopPropagation()}
                  size="sm"
                  fw={500}
                >
                  {s.name}
                </Anchor>
                <Text size="xs" c="dimmed">
                  {s.organization.name}
                </Text>
              </Stack>
            ),
          },
          {
            accessor: 'progress',
            title: t('inventory.found'),
            width: 200,
            render: (s) => (
              <Stack gap={2}>
                <Text size="xs">
                  {t('inventory.progress', { found: s.found_count, expected: s.expected_count })}
                </Text>
                <Progress
                  value={s.expected_count ? (s.found_count / s.expected_count) * 100 : 0}
                  size="sm"
                  aria-label={t('inventory.found')}
                />
              </Stack>
            ),
          },
          { accessor: 'status', title: '', render: (s) => t(`inventory.status.${s.status}`) },
          { accessor: 'started_at', title: '', noWrap: true, render: (s) => formatDateTime(s.started_at) },
        ]}
      />
      {starting && (
        <StartModal
          onClose={(id) => {
            setStarting(false)
            if (id) navigate(`/assets/inventory/${id}`)
          }}
        />
      )}
    </>
  )
}
