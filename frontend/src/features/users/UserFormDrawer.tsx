import { MONO_INPUT_STYLES } from '@/lib/ui'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Drawer, Group, MultiSelect, Select, Stack, Text, TextInput } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCurrentUser } from '@/features/auth/queries'
import { useRoles } from '@/features/roles/queries'
import { useCan } from '@/hooks/usePermissions'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { useCreateUser, useUpdateUser } from './queries'
import { createUserSchema, updateUserSchema, type CreateUserValues, type UpdateUserValues } from './schemas'
import type { User } from './types'

const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function CreateUserDrawer({
  opened,
  onClose,
}: {
  opened: boolean
  onClose: (created?: { user: User; temporaryPassword: string }) => void
}) {
  const { t } = useTranslation(['users', 'common'])
  const me = useCurrentUser()
  const canViewRoles = useCan('roles.view')
  const roles = useRoles(canViewRoles)
  const create = useCreateUser()
  const {
    register,
    control,
    handleSubmit,
    setError,
    setValue,
    getValues,
    reset,
    formState: { errors },
  } = useForm<CreateUserValues>({
    resolver: zodResolver(createUserSchema(me.is_super_admin)),
    defaultValues: { username: '', name: '', email: '', employee_id: null, organization_id: null, roles: [] },
  })
  const employeeId = useWatch({ control, name: 'employee_id' })

  const onSubmit = handleSubmit(async (values) => {
    try {
      const result = await create.mutateAsync({
        username: values.username.trim(),
        name: values.name.trim(),
        email: blankToNull(values.email),
        employee_id: values.employee_id,
        organization_id: values.employee_id ? null : values.organization_id,
        roles: values.roles,
      })
      notifications.show({ color: 'teal', message: t('form.created') })
      reset()
      onClose(result)
    } catch (error) {
      if (!applyApiErrors(error, setError))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  })

  return (
    <Drawer opened={opened} onClose={() => onClose()} title={<Text fw={600}>{t('form.createTitle')}</Text>}>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <TextInput
            label={t('fields.username')}
            description={t('hints.username')}
            withAsterisk
            styles={MONO_INPUT_STYLES}
            autoCapitalize="none"
            spellCheck={false}
            error={fieldError(t, errors.username)}
            {...register('username')}
          />
          <Controller
            control={control}
            name="employee_id"
            render={({ field }) => (
              <EmployeeSelect
                label={t('fields.employee')}
                description={t('hints.employee')}
                value={field.value}
                onChange={(value) => {
                  field.onChange(value)
                  if (value !== null) setValue('organization_id', null)
                }}
                onEmployeeChange={(employee) => {
                  if (employee && !getValues('name').trim()) setValue('name', employee.full_name)
                }}
                error={fieldError(t, errors.employee_id)}
              />
            )}
          />
          <TextInput
            label={t('fields.name')}
            withAsterisk
            error={fieldError(t, errors.name)}
            {...register('name')}
          />
          <TextInput
            label={t('fields.email')}
            type="email"
            error={fieldError(t, errors.email)}
            {...register('email')}
          />
          <Controller
            control={control}
            name="organization_id"
            render={({ field }) => (
              <OrganizationSelect
                label={t('fields.organization')}
                description={t('hints.organization')}
                value={field.value}
                onChange={field.onChange}
                disabled={employeeId !== null}
                withAsterisk={!me.is_super_admin && employeeId === null}
                onlyActive
                error={fieldError(t, errors.organization_id)}
              />
            )}
          />
          <Controller
            control={control}
            name="roles"
            render={({ field }) => (
              <MultiSelect
                label={t('fields.roles')}
                data={(roles.data ?? []).map((r) => ({ value: r.name, label: r.display_name || r.name }))}
                value={field.value}
                onChange={field.onChange}
                withAsterisk
                searchable
                disabled={!canViewRoles}
                description={canViewRoles ? undefined : t('hints.noRolesView')}
                error={fieldError(t, errors.roles)}
                comboboxProps={{ withinPortal: true }}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={() => onClose()}>
              {t('common:actions.cancel')}
            </Button>
            <Button type="submit" loading={create.isPending}>
              {t('common:actions.save')}
            </Button>
          </Group>
        </Stack>
      </form>
    </Drawer>
  )
}

export function EditUserDrawer({
  opened,
  onClose,
  user,
}: {
  opened: boolean
  onClose: () => void
  user: User
}) {
  const { t } = useTranslation(['users', 'common'])
  const update = useUpdateUser(user.id)
  const {
    register,
    control,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<UpdateUserValues>({
    resolver: zodResolver(updateUserSchema),
    values: {
      name: user.name,
      email: user.email ?? '',
      locale: user.locale,
      employee_id: user.employee?.id ?? null,
      organization_id: user.organization?.id ?? null,
    },
  })
  const employeeId = useWatch({ control, name: 'employee_id' })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await update.mutateAsync({
        name: values.name.trim(),
        email: blankToNull(values.email),
        locale: values.locale,
        employee_id: values.employee_id,
        ...(values.employee_id === null && values.organization_id !== null
          ? { organization_id: values.organization_id }
          : {}),
      })
      notifications.show({ color: 'teal', message: t('form.updated') })
      onClose()
    } catch (error) {
      if (!applyApiErrors(error, setError))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  })

  return (
    <Drawer opened={opened} onClose={onClose} title={<Text fw={600}>{t('form.editTitle')}</Text>}>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <TextInput
            label={t('fields.username')}
            value={user.username}
            styles={MONO_INPUT_STYLES}
            disabled
            readOnly
          />
          <TextInput
            label={t('fields.name')}
            withAsterisk
            error={fieldError(t, errors.name)}
            {...register('name')}
          />
          <TextInput
            label={t('fields.email')}
            type="email"
            error={fieldError(t, errors.email)}
            {...register('email')}
          />
          <Controller
            control={control}
            name="locale"
            render={({ field }) => (
              <Select
                label={t('fields.locale')}
                data={[
                  { value: 'uz', label: t('common:languages.uz') },
                  { value: 'ru', label: t('common:languages.ru') },
                ]}
                value={field.value}
                onChange={(v) => v && field.onChange(v)}
                allowDeselect={false}
              />
            )}
          />
          <Controller
            control={control}
            name="employee_id"
            render={({ field }) => (
              <EmployeeSelect
                label={t('fields.employee')}
                description={t('hints.employee')}
                value={field.value}
                onChange={field.onChange}
                placeholder={user.employee?.full_name}
                error={fieldError(t, errors.employee_id)}
              />
            )}
          />
          <Controller
            control={control}
            name="organization_id"
            render={({ field }) => (
              <OrganizationSelect
                label={t('fields.organization')}
                value={field.value}
                onChange={field.onChange}
                disabled={employeeId !== null}
                error={fieldError(t, errors.organization_id)}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>
              {t('common:actions.cancel')}
            </Button>
            <Button type="submit" loading={update.isPending}>
              {t('common:actions.save')}
            </Button>
          </Group>
        </Stack>
      </form>
    </Drawer>
  )
}
