import { MONO_INPUT_STYLES } from '@/lib/ui'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  Divider,
  Drawer,
  Group,
  Select,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useEffect, useRef } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { confirmAction } from '@/components/confirm'
import { DepartmentSelect } from '@/components/DepartmentSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { useSaveEmployee } from './queries'
import { EMPLOYEE_STATUSES, employeeSchema, toEmployeeInput, type EmployeeFormValues } from './schemas'
import type { Employee } from './types'

interface Props {
  opened: boolean
  onClose: (saved?: Employee) => void
  employee?: Employee
  organizationId?: number | null
}

function defaults(employee?: Employee, organizationId?: number | null): EmployeeFormValues {
  return {
    organization_id: employee?.organization?.id ?? organizationId ?? null,
    department_id: employee?.department?.id ?? null,
    last_name: employee?.last_name ?? '',
    first_name: employee?.first_name ?? '',
    middle_name: employee?.middle_name ?? '',
    position: employee?.position ?? '',
    personnel_number: employee?.personnel_number ?? '',
    phone: employee?.phone ?? '',
    internal_phone: employee?.internal_phone ?? '',
    email: employee?.email ?? '',
    status: employee?.status ?? 'active',
    hired_at: employee?.hired_at ?? null,
    dismissed_at: employee?.dismissed_at ?? null,
    notes: employee?.notes ?? '',
  }
}

export function EmployeeFormDrawer({ opened, onClose, employee, organizationId }: Props) {
  const { t } = useTranslation(['employees', 'common'])
  const save = useSaveEmployee()
  const {
    register,
    control,
    setValue,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<EmployeeFormValues>({
    resolver: zodResolver(employeeSchema),
    values: defaults(employee, organizationId),
  })
  const selectedOrganization = useWatch({ control, name: 'organization_id' })
  const status = useWatch({ control, name: 'status' })

  // A department belongs to one organization: changing the organization clears it.
  const previousOrganization = useRef(selectedOrganization)
  useEffect(() => {
    if (previousOrganization.current !== selectedOrganization) {
      if (previousOrganization.current !== null) setValue('department_id', null)
      previousOrganization.current = selectedOrganization
    }
  }, [selectedOrganization, setValue])

  const onSubmit = handleSubmit(async (values) => {
    const dismissing = values.status === 'dismissed' && employee?.status !== 'dismissed'
    if (dismissing) {
      const ok = await confirmAction({
        title: t('form.dismissTitle'),
        message: t('form.dismissMessage'),
        confirmLabel: t('form.dismissConfirm'),
        danger: true,
      })
      if (!ok) return
    }
    try {
      const input = toEmployeeInput(values)
      // Only a real transfer sends the organization (it is then validated as a transfer).
      if (employee && input.organization_id === employee.organization?.id) delete input.organization_id
      const saved = await save.mutateAsync({ id: employee?.id, input })
      notifications.show({ color: 'teal', message: employee ? t('form.updated') : t('form.created') })
      onClose(saved)
    } catch (error) {
      if (!applyApiErrors(error, setError))
        notifications.show({ color: 'red', message: (error as Error).message })
    }
  })

  return (
    <Drawer
      opened={opened}
      onClose={() => onClose()}
      title={<Text fw={600}>{employee ? t('form.editTitle') : t('form.createTitle')}</Text>}
    >
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text fw={600} size="sm">
            {t('sections.work')}
          </Text>
          <Controller
            control={control}
            name="organization_id"
            render={({ field }) => (
              <OrganizationSelect
                label={t('fields.organization')}
                value={field.value}
                onChange={field.onChange}
                onlyActive
                withAsterisk
                error={fieldError(t, errors.organization_id)}
              />
            )}
          />
          <Controller
            control={control}
            name="department_id"
            render={({ field }) => (
              <DepartmentSelect
                label={t('fields.department')}
                organizationId={selectedOrganization}
                value={field.value}
                onChange={field.onChange}
                placeholder={selectedOrganization === null ? t('form.departmentHint') : undefined}
                error={fieldError(t, errors.department_id)}
              />
            )}
          />
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput
              label={t('fields.position')}
              error={fieldError(t, errors.position)}
              {...register('position')}
            />
            <TextInput
              label={t('fields.personnel_number')}
              styles={MONO_INPUT_STYLES}
              error={fieldError(t, errors.personnel_number)}
              {...register('personnel_number')}
            />
          </SimpleGrid>

          <Divider />
          <Text fw={600} size="sm">
            {t('sections.person')}
          </Text>
          <SimpleGrid cols={{ base: 1, sm: 3 }}>
            <TextInput
              label={t('fields.last_name')}
              withAsterisk
              error={fieldError(t, errors.last_name)}
              {...register('last_name')}
            />
            <TextInput
              label={t('fields.first_name')}
              withAsterisk
              error={fieldError(t, errors.first_name)}
              {...register('first_name')}
            />
            <TextInput
              label={t('fields.middle_name')}
              error={fieldError(t, errors.middle_name)}
              {...register('middle_name')}
            />
          </SimpleGrid>

          <Divider />
          <Text fw={600} size="sm">
            {t('sections.contacts')}
          </Text>
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput
              label={t('fields.phone')}
              type="tel"
              error={fieldError(t, errors.phone)}
              {...register('phone')}
            />
            <TextInput
              label={t('fields.internal_phone')}
              error={fieldError(t, errors.internal_phone)}
              {...register('internal_phone')}
            />
          </SimpleGrid>
          <TextInput
            label={t('fields.email')}
            type="email"
            error={fieldError(t, errors.email)}
            {...register('email')}
          />

          <Divider />
          <Text fw={600} size="sm">
            {t('sections.status')}
          </Text>
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <Controller
              control={control}
              name="status"
              render={({ field }) => (
                <Select
                  label={t('fields.status')}
                  data={EMPLOYEE_STATUSES.map((s) => ({ value: s, label: t(`common:status.${s}`) }))}
                  value={field.value}
                  onChange={(v) => v && field.onChange(v)}
                  allowDeselect={false}
                  withAsterisk
                />
              )}
            />
            <Controller
              control={control}
              name="hired_at"
              render={({ field }) => (
                <DateInput
                  label={t('fields.hired_at')}
                  valueFormat="DD.MM.YYYY"
                  clearable
                  value={field.value}
                  onChange={field.onChange}
                  error={fieldError(t, errors.hired_at)}
                />
              )}
            />
          </SimpleGrid>
          {status === 'dismissed' && (
            <Controller
              control={control}
              name="dismissed_at"
              render={({ field }) => (
                <DateInput
                  label={t('fields.dismissed_at')}
                  valueFormat="DD.MM.YYYY"
                  withAsterisk
                  value={field.value}
                  onChange={field.onChange}
                  error={fieldError(t, errors.dismissed_at)}
                />
              )}
            />
          )}
          <Textarea
            label={t('fields.notes')}
            autosize
            minRows={2}
            error={fieldError(t, errors.notes)}
            {...register('notes')}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={() => onClose()}>
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
