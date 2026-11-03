import { Alert, Button, Group, Paper, Skeleton, Stack, Textarea, TextInput } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { PermissionMatrix } from './PermissionMatrix'
import { MONO_INPUT_STYLES } from '@/lib/ui'
import { useDeleteRole, usePermissionModules, useRole, useSaveRole } from './queries'
import type { PermissionModule, Role } from './types'

const SUPER_ADMIN = 'super-admin'

/** /roles/new and /roles/:id — role details and its permission matrix. */
export function RoleEditorPage() {
  const params = useParams()
  const isNew = params.id === undefined
  const id = isNew ? null : Number(params.id)
  const canManage = useCan('roles.manage')
  const role = useRole(id)
  const modules = usePermissionModules()

  if (role.error instanceof ApiError && role.error.status === 404) return <NotFoundPage />
  if (role.isError) return <ErrorState error={role.error} onRetry={() => void role.refetch()} />
  if ((!isNew && !role.data) || !modules.data) return <Skeleton height={420} radius="md" />

  return (
    <RoleForm key={role.data?.id ?? 'new'} role={role.data} modules={modules.data} canManage={canManage} />
  )
}

function RoleForm({
  role,
  modules,
  canManage,
}: {
  role?: Role
  modules: PermissionModule[]
  canManage: boolean
}) {
  const { t } = useTranslation(['roles', 'common'])
  const navigate = useNavigate()
  const save = useSaveRole()
  const remove = useDeleteRole()
  const isNew = role === undefined
  const id = role?.id ?? null

  const [name, setName] = useState(role?.name ?? '')
  const [displayName, setDisplayName] = useState(role?.display_name ?? '')
  const [description, setDescription] = useState(role?.description ?? '')
  const [permissions, setPermissions] = useState<string[]>(role?.permissions ?? [])

  const isSuperAdmin = role?.name === SUPER_ADMIN
  const readOnly = !canManage
  const fieldErrors = save.error instanceof ApiError ? save.error.fieldErrors : {}

  const onSave = async () => {
    try {
      const saved = await save.mutateAsync({
        id: id ?? undefined,
        input: {
          name: isNew ? name.trim() : undefined,
          display_name: displayName.trim(),
          description: description.trim() || null,
          permissions: isSuperAdmin ? undefined : permissions,
        },
      })
      notifications.show({ color: 'teal', message: isNew ? t('form.created') : t('form.updated') })
      if (isNew) navigate(`/roles/${saved.id}`, { replace: true })
    } catch (error) {
      if (!(error instanceof ApiError && error.status === 422))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  const onDelete = async () => {
    if (!role) return
    const ok = await confirmAction({
      message: t('delete.message', { name: role.display_name }),
      confirmLabel: t('actions.delete'),
      danger: true,
    })
    if (!ok) return
    try {
      await remove.mutateAsync(role.id)
      notifications.show({ color: 'teal', message: t('delete.done') })
      navigate('/roles', { replace: true })
    } catch (error) {
      notifications.show({ color: 'red', message: (error as Error).message })
    }
  }

  return (
    <>
      <PageHeader
        title={isNew ? t('form.createTitle') : role.display_name || role.name}
        actions={
          canManage &&
          !isNew &&
          !role.is_system && (
            <Button
              variant="light"
              color="red"
              leftSection={<IconTrash size={16} />}
              onClick={() => void onDelete()}
              loading={remove.isPending}
            >
              {t('actions.delete')}
            </Button>
          )
        }
      />
      <Stack gap="lg">
        <Paper withBorder p="lg">
          <Stack gap="md" maw={640}>
            <TextInput
              label={t('fields.name')}
              description={isNew ? t('hints.name') : undefined}
              value={name}
              onChange={(e) => setName(e.currentTarget.value)}
              disabled={!isNew || readOnly}
              styles={MONO_INPUT_STYLES}
              withAsterisk={isNew}
              error={fieldErrors.name?.[0]}
            />
            <TextInput
              label={t('fields.display_name')}
              value={displayName}
              onChange={(e) => setDisplayName(e.currentTarget.value)}
              disabled={readOnly}
              withAsterisk
              error={fieldErrors.display_name?.[0]}
            />
            <Textarea
              label={t('fields.description')}
              value={description}
              onChange={(e) => setDescription(e.currentTarget.value)}
              disabled={readOnly}
              autosize
              minRows={2}
              error={fieldErrors.description?.[0]}
            />
          </Stack>
        </Paper>

        {isSuperAdmin ? (
          <Alert color="cobalt" variant="light">
            {t('hints.superAdmin')}
          </Alert>
        ) : (
          <PermissionMatrix
            modules={modules}
            value={permissions}
            onChange={setPermissions}
            disabled={readOnly}
          />
        )}
        {fieldErrors.permissions?.[0] && (
          <Alert color="red" variant="light">
            {fieldErrors.permissions[0]}
          </Alert>
        )}

        {canManage && (
          <Group justify="flex-end">
            <Button variant="default" onClick={() => navigate('/roles')}>
              {t('common:actions.cancel')}
            </Button>
            <Button
              onClick={() => void onSave()}
              loading={save.isPending}
              disabled={!displayName.trim() || (isNew && !name.trim())}
            >
              {t('actions.save')}
            </Button>
          </Group>
        )}
      </Stack>
    </>
  )
}
