import { Anchor, Badge, Button, Group, Paper, SimpleGrid, Skeleton, Tabs, Text } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconEdit, IconPlus, IconTrash } from '@tabler/icons-react'
import type { ReactNode } from 'react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { ApiError } from '@/api/errors'
import { useCan } from '@/hooks/usePermissions'
import { formatDateTime } from '@/lib/format'
import { ItResponsiblesPanel } from './ItResponsiblesPanel'
import { OrganizationFormDrawer } from './OrganizationFormDrawer'
import { useDeleteOrganization, useOrganization } from './queries'
import type { Organization } from './types'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm">{children || '—'}</Text>
    </div>
  )
}

function InfoTab({ organization }: { organization: Organization }) {
  const { t } = useTranslation('organizations')
  return (
    <Paper withBorder p="lg">
      <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
        <Field label={t('fields.type')}>{t(`types.${organization.type}`)}</Field>
        <Field label={t('fields.parent')}>
          {organization.parent ? (
            <Anchor component={Link} to={`/organizations/${organization.parent.id}`} size="sm">
              {organization.parent.name}
            </Anchor>
          ) : null}
        </Field>
        <Field label={t('fields.code')}>
          <Text span ff="monospace" size="sm">
            {organization.code}
          </Text>
        </Field>
        <Field label={t('fields.short_name')}>{organization.short_name}</Field>
        <Field label={t('fields.tin')}>{organization.tin}</Field>
        <Field label={t('fields.head_name')}>{organization.head_name}</Field>
        <Field label={t('fields.address')}>{organization.address}</Field>
        <Field label={t('fields.phone')}>{organization.phone}</Field>
        <Field label={t('fields.email')}>{organization.email}</Field>
        <Field label={t('fields.children')}>{String(organization.children_count ?? 0)}</Field>
        <Field label={t('fields.created_at')}>{formatDateTime(organization.created_at)}</Field>
        <Field label={t('fields.updated_at')}>{formatDateTime(organization.updated_at)}</Field>
      </SimpleGrid>
    </Paper>
  )
}

export function OrganizationDetailPage() {
  const { t } = useTranslation(['organizations', 'common'])
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const query = useOrganization(id)
  const remove = useDeleteOrganization()
  const canCreate = useCan('organizations.create')
  const [editing, setEditing] = useState(false)
  const [addingChild, setAddingChild] = useState(false)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.isError) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  if (!query.data) return <Skeleton height={320} radius="md" />

  const organization = query.data
  const tab = params.get('tab') ?? 'info'

  const onDelete = async () => {
    const ok = await confirmAction({
      title: t('delete.title'),
      message: t('delete.message', { name: organization.name }),
      confirmLabel: t('actions.delete'),
      danger: true,
    })
    if (!ok) return
    try {
      await remove.mutateAsync(organization.id)
      notifications.show({ color: 'teal', message: t('delete.done') })
      navigate('/organizations', { replace: true })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <>
      <PageHeader
        title={organization.name}
        description={organization.short_name ?? undefined}
        meta={
          <Group gap="xs">
            <Badge variant="light" color="cobalt" tt="none">
              {t(`types.${organization.type}`)}
            </Badge>
            {!organization.is_active && (
              <Badge variant="light" color="red" tt="none">
                {t('common:labels.inactive')}
              </Badge>
            )}
          </Group>
        }
        actions={
          <>
            {canCreate && organization.is_active && (
              <Button variant="default" leftSection={<IconPlus size={16} />} onClick={() => setAddingChild(true)}>
                {t('actions.addChild')}
              </Button>
            )}
            {organization.can.update && (
              <Button variant="default" leftSection={<IconEdit size={16} />} onClick={() => setEditing(true)}>
                {t('actions.edit')}
              </Button>
            )}
            {organization.can.delete && (
              <Button variant="light" color="red" leftSection={<IconTrash size={16} />} onClick={() => void onDelete()} loading={remove.isPending}>
                {t('actions.delete')}
              </Button>
            )}
          </>
        }
      />

      <Tabs
        value={tab}
        onChange={(value) =>
          setParams((prev) => {
            const next = new URLSearchParams(prev)
            if (value && value !== 'info') next.set('tab', value)
            else next.delete('tab')
            return next
          }, { replace: true })
        }
        keepMounted={false}
      >
        <Tabs.List mb="md">
          <Tabs.Tab value="info">{t('tabs.info')}</Tabs.Tab>
          <Tabs.Tab value="it">{t('tabs.it')}</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="info">
          <InfoTab organization={organization} />
        </Tabs.Panel>
        <Tabs.Panel value="it">
          <ItResponsiblesPanel organization={organization} />
        </Tabs.Panel>
      </Tabs>

      <OrganizationFormDrawer opened={editing} organization={organization} onClose={() => setEditing(false)} />
      <OrganizationFormDrawer
        opened={addingChild}
        parentId={organization.id}
        onClose={(saved) => {
          setAddingChild(false)
          if (saved) navigate(`/organizations/${saved.id}`)
        }}
      />
    </>
  )
}
