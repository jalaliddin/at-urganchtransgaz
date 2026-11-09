import {
  Badge,
  Button,
  Card,
  Group,
  Modal,
  SegmentedControl,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Textarea,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPlus } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { useOrgContext } from '@/hooks/useOrgContext'
import * as s from './api'
import { useBoards, useTaskMutation } from './queries'

function NewBoardModal({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation(['tasks', 'common', 'validation'])
  const navigate = useNavigate()
  const { data: me } = useMe()
  const [organizationId, setOrganizationId] = useState<number | null>(me?.organization?.id ?? null)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useTaskMutation(() =>
    s.saveBoard({ organization_id: organizationId, name, description: description || null }),
  )

  return (
    <Modal opened onClose={onClose} title={t('boards.add')} centered>
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
          label={t('boards.name')}
          required
          value={name}
          onChange={(e) => setName(e.currentTarget.value)}
          error={errors.name}
          data-autofocus
        />
        <Textarea
          label={t('fields.description')}
          autosize
          minRows={2}
          value={description}
          onChange={(e) => setDescription(e.currentTarget.value)}
        />
        <Text size="xs" c="dimmed">
          {t('boards.columnsHint')}
        </Text>
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              const local: Record<string, string> = {}
              if (!name.trim()) local.name = t('validation:required')
              if (!organizationId) local.organization_id = t('validation:required')
              setErrors(local)
              if (Object.keys(local).length > 0) return
              save.mutate(undefined, {
                onSuccess: (b) => {
                  onClose()
                  navigate(`/tasks/boards/${b.id}`)
                },
                onError: (e) => {
                  const fe = Object.fromEntries(
                    Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
                  )
                  if (Object.keys(fe).length) setErrors(fe)
                  else notifications.show({ color: 'red', message: toApiError(e).message })
                },
              })
            }}
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

export function BoardsPage() {
  const { t } = useTranslation(['tasks', 'common'])
  const canManage = useCan('tasks.manage')
  const { organizationId } = useOrgContext()
  const [archived, setArchived] = useState('0')
  const params: Record<string, string | number> = { 'filter[archived]': archived }
  if (organizationId) params['filter[organization]'] = organizationId
  const boards = useBoards(params)
  const [creating, setCreating] = useState(false)

  return (
    <>
      <PageHeader
        title={t('boards.title')}
        description={t('boards.description')}
        actions={
          canManage && (
            <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>
              {t('boards.add')}
            </Button>
          )
        }
      />
      <SegmentedControl
        mb="md"
        value={archived}
        onChange={setArchived}
        data={[
          { value: '0', label: t('boards.active') },
          { value: '1', label: t('boards.archived') },
        ]}
      />
      {boards.error && <ErrorState error={boards.error} onRetry={() => void boards.refetch()} />}
      {boards.data?.length === 0 && <EmptyState title={t('boards.empty')} />}
      <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }}>
        {boards.data?.map((b) => (
          <Card key={b.id} withBorder radius="md" component={Link} to={`/tasks/boards/${b.id}`}>
            <Stack gap={6}>
              <Text fw={600}>{b.name}</Text>
              <Text size="sm" c="dimmed">
                {b.organization.name}
              </Text>
              <Group gap="xs">
                <Badge variant="light">{t('boards.open', { count: b.open_tasks ?? 0 })}</Badge>
                {(b.overdue_tasks ?? 0) > 0 && (
                  <Badge color="red" variant="light">
                    {t('boards.overdue', { count: b.overdue_tasks ?? 0 })}
                  </Badge>
                )}
              </Group>
            </Stack>
          </Card>
        ))}
      </SimpleGrid>
      {creating && <NewBoardModal onClose={() => setCreating(false)} />}
    </>
  )
}
