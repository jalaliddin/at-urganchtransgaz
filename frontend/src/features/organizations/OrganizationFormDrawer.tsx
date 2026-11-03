import { MONO_INPUT_STYLES } from '@/lib/ui'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  Divider,
  Drawer,
  Group,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Switch,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useCurrentUser } from '@/features/auth/queries'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { useSaveOrganization } from './queries'
import { organizationSchema, toOrganizationInput, type OrganizationFormValues } from './schemas'
import type { Organization } from './types'

interface Props {
  opened: boolean
  onClose: (saved?: Organization) => void
  /** Edit mode when given. */
  organization?: Organization
  /** Pre-selected parent for "add child". */
  parentId?: number | null
}

const TYPES = ['head_office', 'subsidiary', 'branch', 'other'] as const

function defaults(organization?: Organization, parentId?: number | null): OrganizationFormValues {
  return {
    parent_id: organization?.parent_id ?? parentId ?? null,
    type: organization?.type ?? 'subsidiary',
    name: organization?.name ?? '',
    short_name: organization?.short_name ?? '',
    code: organization?.code ?? '',
    tin: organization?.tin ?? '',
    address: organization?.address ?? '',
    phone: organization?.phone ?? '',
    email: organization?.email ?? '',
    head_name: organization?.head_name ?? '',
    is_active: organization?.is_active ?? true,
    sort_order: organization?.sort_order ?? 0,
  }
}

export function OrganizationFormDrawer({ opened, onClose, organization, parentId }: Props) {
  const { t } = useTranslation(['organizations', 'common'])
  const me = useCurrentUser()
  const save = useSaveOrganization()
  const {
    register,
    control,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<OrganizationFormValues>({
    resolver: zodResolver(organizationSchema(me.is_super_admin, !organization)),
    values: defaults(organization, parentId),
  })

  const onSubmit = handleSubmit(async (values) => {
    try {
      const input = toOrganizationInput(values)
      // An unchanged parent is not sent: re-validating it could block the edit (scope top, inactive parent).
      if (organization && input.parent_id === organization.parent_id) delete input.parent_id
      const saved = await save.mutateAsync({ id: organization?.id, input })
      notifications.show({ color: 'teal', message: organization ? t('form.updated') : t('form.created') })
      onClose(saved)
    } catch (error) {
      if (!applyApiErrors(error, setError)) {
        notifications.show({ color: 'red', message: (error as Error).message })
      }
    }
  })

  return (
    <Drawer
      opened={opened}
      onClose={() => onClose()}
      title={<Text fw={600}>{organization ? t('form.editTitle') : t('form.createTitle')}</Text>}
    >
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          <Text fw={600} size="sm">
            {t('sections.main')}
          </Text>
          <Controller
            control={control}
            name="parent_id"
            render={({ field }) => (
              <OrganizationSelect
                label={t('fields.parent')}
                description={me.is_super_admin ? t('form.rootHint') : undefined}
                value={field.value}
                onChange={field.onChange}
                onlyActive
                excludeIds={organization ? [organization.id] : []}
                withAsterisk={!me.is_super_admin}
                error={fieldError(t, errors.parent_id)}
              />
            )}
          />
          <Controller
            control={control}
            name="type"
            render={({ field }) => (
              <Select
                label={t('fields.type')}
                data={TYPES.map((type) => ({ value: type, label: t(`types.${type}`) }))}
                value={field.value}
                onChange={(value) => value && field.onChange(value)}
                allowDeselect={false}
                withAsterisk
                error={fieldError(t, errors.type)}
              />
            )}
          />
          <TextInput
            label={t('fields.name')}
            withAsterisk
            error={fieldError(t, errors.name)}
            {...register('name')}
          />
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput
              label={t('fields.short_name')}
              error={fieldError(t, errors.short_name)}
              {...register('short_name')}
            />
            <TextInput
              label={t('fields.code')}
              withAsterisk
              styles={MONO_INPUT_STYLES}
              autoCapitalize="characters"
              error={fieldError(t, errors.code)}
              {...register('code')}
            />
          </SimpleGrid>
          <TextInput
            label={t('fields.tin')}
            inputMode="numeric"
            maxLength={9}
            error={fieldError(t, errors.tin)}
            {...register('tin')}
          />

          <Divider />
          <Text fw={600} size="sm">
            {t('sections.contacts')}
          </Text>
          <Textarea
            label={t('fields.address')}
            autosize
            minRows={2}
            error={fieldError(t, errors.address)}
            {...register('address')}
          />
          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <TextInput
              label={t('fields.phone')}
              type="tel"
              error={fieldError(t, errors.phone)}
              {...register('phone')}
            />
            <TextInput
              label={t('fields.email')}
              type="email"
              error={fieldError(t, errors.email)}
              {...register('email')}
            />
          </SimpleGrid>
          <TextInput
            label={t('fields.head_name')}
            error={fieldError(t, errors.head_name)}
            {...register('head_name')}
          />

          <Divider />
          <Text fw={600} size="sm">
            {t('sections.state')}
          </Text>
          <Controller
            control={control}
            name="is_active"
            render={({ field }) => (
              <Switch
                label={t('fields.is_active')}
                description={t('form.inactiveHint')}
                checked={field.value}
                onChange={(event) => field.onChange(event.currentTarget.checked)}
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
                max={100000}
                allowDecimal={false}
                value={field.value}
                onChange={(value) => field.onChange(typeof value === 'number' ? value : 0)}
                error={fieldError(t, errors.sort_order)}
                w={160}
              />
            )}
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
