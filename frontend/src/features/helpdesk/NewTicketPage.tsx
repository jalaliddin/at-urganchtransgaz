import { zodResolver } from '@hookform/resolvers/zod'
import {
  Alert,
  Button,
  Card,
  Group,
  SegmentedControl,
  Select,
  SimpleGrid,
  Stack,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconSend } from '@tabler/icons-react'
import { useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { useCan } from '@/hooks/usePermissions'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { KbSuggestions } from '@/features/knowledge/KbSuggestions'
import { AttachmentDropzone } from './AttachmentDropzone'
import { useCategories, useCreateTicket } from './queries'
import { ticketSchema, toTicketInput, type TicketFormValues } from './schemas'
import { TICKET_PRIORITIES } from './types'

/** Ticket form built for phones first: one column, large targets, submit always reachable. */
export function NewTicketPage() {
  const { t } = useTranslation(['helpdesk', 'validation'])
  const navigate = useNavigate()
  const { data: me } = useMe()
  const isHandler = useCan('helpdesk.process')
  const categories = useCategories(true)
  const create = useCreateTicket()
  const [files, setFiles] = useState<File[]>([])
  const [generalError, setGeneralError] = useState<string | null>(null)
  const homeOrganization = me?.organization?.id ?? null
  const chooseOrganization = isHandler || homeOrganization === null

  const {
    register,
    control,
    handleSubmit,
    setError,
    setValue,
    formState: { errors },
  } = useForm<TicketFormValues>({
    resolver: zodResolver(ticketSchema),
    defaultValues: {
      organization_id: homeOrganization,
      requester_employee_id: null,
      category_id: null,
      priority: 'normal',
      title: '',
      description: '',
      location: '',
      contact_phone: '',
      contact_email: me?.email ?? '',
    },
  })
  const organizationId = useWatch({ control, name: 'organization_id' })
  const title = useWatch({ control, name: 'title' })
  const categoryId = useWatch({ control, name: 'category_id' })

  const onSubmit = handleSubmit(async (values) => {
    setGeneralError(null)
    try {
      const ticket = await create.mutateAsync({ input: toTicketInput(values), files })
      notifications.show({ color: 'green', message: t('new.submitted', { number: ticket.number }) })
      navigate(`/helpdesk/${ticket.id}`)
    } catch (error) {
      const apiError = toApiError(error)
      // files.N errors have no input of their own: show them above the form.
      const fileError = Object.entries(apiError.fieldErrors).find(([key]) => key.startsWith('files'))?.[1][0]
      if (!applyApiErrors(error, setError) || fileError) setGeneralError(fileError ?? apiError.message)
    }
  })

  return (
    <>
      <PageHeader title={t('new.title')} description={t('new.description')} />
      <Card withBorder radius="md" p={{ base: 'md', sm: 'lg' }} maw={760}>
        <form onSubmit={onSubmit} noValidate>
          <Stack gap="md">
            {generalError && (
              <Alert color="red" role="alert">
                {generalError}
              </Alert>
            )}
            {chooseOrganization && (
              <SimpleGrid cols={{ base: 1, sm: 2 }}>
                <Controller
                  control={control}
                  name="organization_id"
                  render={({ field }) => (
                    <OrganizationSelect
                      label={t('fields.organization')}
                      required
                      onlyActive
                      value={field.value}
                      onChange={(value) => {
                        field.onChange(value)
                        setValue('requester_employee_id', null)
                      }}
                      error={fieldError(t, errors.organization_id)}
                    />
                  )}
                />
                {isHandler && (
                  <Controller
                    control={control}
                    name="requester_employee_id"
                    render={({ field }) => (
                      <EmployeeSelect
                        label={t('new.onBehalf')}
                        organizationId={organizationId}
                        value={field.value}
                        onChange={field.onChange}
                        clearable
                        disabled={organizationId === null}
                        error={fieldError(t, errors.requester_employee_id)}
                      />
                    )}
                  />
                )}
              </SimpleGrid>
            )}
            <Controller
              control={control}
              name="category_id"
              render={({ field }) => (
                <Select
                  label={t('fields.category')}
                  required
                  searchable
                  data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
                  value={field.value === null ? null : String(field.value)}
                  onChange={(value) => field.onChange(value === null ? null : Number(value))}
                  error={fieldError(t, errors.category_id)}
                />
              )}
            />
            <Controller
              control={control}
              name="priority"
              render={({ field }) => (
                <Stack gap={4}>
                  <Text size="sm" fw={500} id="priority-label">
                    {t('fields.priority')}
                  </Text>
                  <SegmentedControl
                    aria-labelledby="priority-label"
                    fullWidth
                    value={field.value}
                    onChange={field.onChange}
                    data={TICKET_PRIORITIES.map((p) => ({ value: p, label: t(`priority.${p}`) }))}
                  />
                </Stack>
              )}
            />
            <TextInput
              label={t('fields.title')}
              required
              {...register('title')}
              error={fieldError(t, errors.title)}
            />
            <KbSuggestions title={title ?? ''} categoryId={categoryId ?? null} />
            <Textarea
              label={t('fields.description')}
              required
              autosize
              minRows={4}
              maxRows={12}
              {...register('description')}
              error={fieldError(t, errors.description)}
            />
            <Text size="sm" fw={600} mt="xs">
              {t('new.contactSection')}
            </Text>
            <SimpleGrid cols={{ base: 1, sm: 3 }}>
              <TextInput
                label={t('fields.contactPhone')}
                type="tel"
                inputMode="tel"
                {...register('contact_phone')}
                error={fieldError(t, errors.contact_phone)}
              />
              <TextInput
                label={t('fields.contactEmail')}
                type="email"
                {...register('contact_email')}
                error={fieldError(t, errors.contact_email)}
              />
              <TextInput
                label={t('fields.location')}
                {...register('location')}
                error={fieldError(t, errors.location)}
              />
            </SimpleGrid>
            <AttachmentDropzone value={files} onChange={setFiles} />
            <Group
              justify="flex-end"
              pos="sticky"
              bottom={0}
              py="sm"
              bg="var(--mantine-color-body)"
              style={{ zIndex: 1 }}
            >
              <Button
                type="submit"
                leftSection={<IconSend size={16} />}
                loading={create.isPending}
                fullWidth={false}
              >
                {t('new.submit')}
              </Button>
            </Group>
          </Stack>
        </form>
      </Card>
    </>
  )
}
