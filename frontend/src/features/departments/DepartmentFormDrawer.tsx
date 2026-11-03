import { MONO_INPUT_STYLES } from '@/lib/ui'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Drawer, Group, NumberInput, Stack, Switch, Text, TextInput } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { DepartmentSelect } from '@/components/DepartmentSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { useSaveDepartment } from './queries'
import { departmentSchema, toDepartmentInput, type DepartmentFormValues } from './schemas'
import type { Department } from './types'

interface Props {
  opened: boolean
  onClose: () => void
  department?: Department
  organizationId?: number | null
}

export function DepartmentFormDrawer({ opened, onClose, department, organizationId }: Props) {
  const { t } = useTranslation(['departments', 'common'])
  const save = useSaveDepartment()
  const {
    register,
    control,
    setValue,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<DepartmentFormValues>({
    resolver: zodResolver(departmentSchema),
    values: {
      organization_id: department?.organization?.id ?? organizationId ?? null,
      parent_id: department?.parent?.id ?? null,
      name: department?.name ?? '',
      code: department?.code ?? '',
      phone: department?.phone ?? '',
      is_active: department?.is_active ?? true,
      sort_order: department?.sort_order ?? 0,
    },
  })
  const selectedOrganization = useWatch({ control, name: 'organization_id' })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await save.mutateAsync({ id: department?.id, input: toDepartmentInput(values) })
      notifications.show({ color: 'teal', message: department ? t('form.updated') : t('form.created') })
      onClose()
    } catch (error) {
      if (!applyApiErrors(error, setError))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  })

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={<Text fw={600}>{department ? t('form.editTitle') : t('form.createTitle')}</Text>}
    >
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Controller
            control={control}
            name="organization_id"
            render={({ field }) => (
              <OrganizationSelect
                label={t('fields.organization')}
                value={field.value}
                onChange={(value) => {
                  field.onChange(value)
                  setValue('parent_id', null)
                }}
                onlyActive
                withAsterisk
                disabled={Boolean(department)}
                description={department ? t('form.organizationLocked') : undefined}
                error={fieldError(t, errors.organization_id)}
              />
            )}
          />
          <Controller
            control={control}
            name="parent_id"
            render={({ field }) => (
              <DepartmentSelect
                label={t('fields.parent')}
                organizationId={selectedOrganization}
                value={field.value}
                onChange={field.onChange}
                excludeId={department?.id}
                error={fieldError(t, errors.parent_id)}
              />
            )}
          />
          <TextInput
            label={t('fields.name')}
            withAsterisk
            error={fieldError(t, errors.name)}
            {...register('name')}
          />
          <Group grow align="flex-start">
            <TextInput
              label={t('fields.code')}
              styles={MONO_INPUT_STYLES}
              error={fieldError(t, errors.code)}
              {...register('code')}
            />
            <TextInput
              label={t('fields.phone')}
              type="tel"
              error={fieldError(t, errors.phone)}
              {...register('phone')}
            />
          </Group>
          <Controller
            control={control}
            name="is_active"
            render={({ field }) => (
              <Switch
                label={t('fields.is_active')}
                checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
              />
            )}
          />
          <Controller
            control={control}
            name="sort_order"
            render={({ field }) => (
              <NumberInput
                label={t('fields.sort_order')}
                min={0}
                allowDecimal={false}
                value={field.value}
                onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)}
                w={160}
              />
            )}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>
              {t('common:actions.cancel')}
            </Button>
            <Button type="submit" loading={save.isPending}>
              {t('common:actions.save')}
            </Button>
          </Group>
        </Stack>
      </form>
    </Drawer>
  )
}
