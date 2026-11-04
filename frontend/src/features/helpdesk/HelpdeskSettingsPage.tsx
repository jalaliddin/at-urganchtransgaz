import {
  ActionIcon,
  Badge,
  Button,
  Card,
  Checkbox,
  Group,
  Modal,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Switch,
  Table,
  Tabs,
  Text,
  TextInput,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import type { CategoryInput } from './api'
import {
  useCalendar,
  useCategories,
  useDeleteCategory,
  useDeleteSlaPolicy,
  useSaveCalendar,
  useSaveCategory,
  useSaveSlaPolicy,
  useSlaPolicies,
} from './queries'
import {
  TICKET_PRIORITIES,
  type CalendarSettings,
  type SlaPolicy,
  type TicketCategory,
  type TicketPriority,
} from './types'

const fail = (error: unknown) => notifications.show({ color: 'red', message: toApiError(error).message })
const firstError = (error: unknown, field: string) =>
  error instanceof ApiError ? (error.fieldErrors[field]?.[0] ?? null) : null

function CategoryModal({ category, onClose }: { category: TicketCategory | null; onClose: () => void }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const save = useSaveCategory()
  const [form, setForm] = useState<CategoryInput>({
    name: category?.name ?? '',
    name_ru: category?.name_ru ?? '',
    description: category?.description ?? '',
    is_active: category?.is_active ?? true,
    sort_order: category?.sort_order ?? 100,
  })

  return (
    <Modal
      opened
      onClose={onClose}
      title={t(category ? 'settings.category.edit' : 'settings.category.add')}
      centered
    >
      <Stack>
        <TextInput
          label={t('settings.category.name')}
          required
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.currentTarget.value })}
          error={firstError(save.error, 'name')}
          data-autofocus
        />
        <TextInput
          label={t('settings.category.nameRu')}
          value={form.name_ru ?? ''}
          onChange={(e) => setForm({ ...form, name_ru: e.currentTarget.value })}
        />
        <TextInput
          label={t('settings.category.description')}
          value={form.description ?? ''}
          onChange={(e) => setForm({ ...form, description: e.currentTarget.value })}
        />
        <Group grow>
          <NumberInput
            label={t('settings.category.sortOrder')}
            min={0}
            value={form.sort_order}
            onChange={(v) => setForm({ ...form, sort_order: Number(v) || 0 })}
          />
          <Switch
            label={t('settings.category.active')}
            checked={form.is_active}
            onChange={(e) => setForm({ ...form, is_active: e.currentTarget.checked })}
            mt="lg"
          />
        </Group>
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() =>
              save.mutate(
                {
                  id: category?.id,
                  input: { ...form, name_ru: form.name_ru || null, description: form.description || null },
                },
                { onSuccess: onClose },
              )
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function CategoriesTab() {
  const { t } = useTranslation(['helpdesk', 'common'])
  const categories = useCategories()
  const remove = useDeleteCategory()
  const [editing, setEditing] = useState<TicketCategory | null | undefined>(undefined)

  return (
    <Stack>
      <Group justify="flex-end">
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>
          {t('settings.category.add')}
        </Button>
      </Group>
      <Card withBorder p={0}>
        <Table.ScrollContainer minWidth={520}>
          <Table verticalSpacing="sm" highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{t('settings.category.name')}</Table.Th>
                <Table.Th>{t('settings.category.nameRu')}</Table.Th>
                <Table.Th>{t('settings.category.active')}</Table.Th>
                <Table.Th w={96} />
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {categories.data?.map((c) => (
                <Table.Tr key={c.id}>
                  <Table.Td>{c.name}</Table.Td>
                  <Table.Td>{c.name_ru ?? '—'}</Table.Td>
                  <Table.Td>
                    <Badge color={c.is_active ? 'green' : 'gray'} variant="light" tt="none">
                      {c.is_active ? t('common:status.active') : t('common:status.inactive')}
                    </Badge>
                  </Table.Td>
                  <Table.Td>
                    <Group gap={4} wrap="nowrap">
                      <ActionIcon
                        variant="subtle"
                        aria-label={`${t('settings.category.edit')}: ${c.name}`}
                        onClick={() => setEditing(c)}
                      >
                        <IconPencil size={16} />
                      </ActionIcon>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={`${t('common:actions.delete')}: ${c.name}`}
                        onClick={async () => {
                          const ok = await confirmAction({
                            title: t('common:actions.delete'),
                            message: t('settings.category.deleteConfirm', { name: c.name }),
                            confirmLabel: t('common:actions.delete'),
                            danger: true,
                          })
                          if (ok) remove.mutate(c.id, { onError: fail })
                        }}
                      >
                        <IconTrash size={16} />
                      </ActionIcon>
                    </Group>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      </Card>
      {editing !== undefined && <CategoryModal category={editing} onClose={() => setEditing(undefined)} />}
    </Stack>
  )
}

function SlaModal({ policy, onClose }: { policy: SlaPolicy | null; onClose: () => void }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const save = useSaveSlaPolicy()
  const categories = useCategories()
  const [priority, setPriority] = useState<TicketPriority>(policy?.priority ?? 'normal')
  const [categoryId, setCategoryId] = useState<string | null>(
    policy?.category ? String(policy.category.id) : null,
  )
  const [response, setResponse] = useState<number>(policy?.response_minutes ?? 120)
  const [resolution, setResolution] = useState<number>(policy?.resolution_minutes ?? 1440)

  return (
    <Modal opened onClose={onClose} title={t('settings.sla.add')} centered>
      <Stack>
        {!policy && (
          <>
            <Select
              label={t('fields.priority')}
              data={TICKET_PRIORITIES.map((p) => ({ value: p, label: t(`priority.${p}`) }))}
              value={priority}
              onChange={(v) => setPriority((v as TicketPriority) ?? 'normal')}
              allowDeselect={false}
              error={firstError(save.error, 'priority')}
            />
            <Select
              label={t('fields.category')}
              data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
              value={categoryId}
              onChange={setCategoryId}
              placeholder={t('settings.sla.general')}
              clearable
            />
          </>
        )}
        <Group grow>
          <NumberInput
            label={t('settings.sla.response')}
            min={1}
            value={response}
            onChange={(v) => setResponse(Number(v) || 0)}
          />
          <NumberInput
            label={t('settings.sla.resolution')}
            min={1}
            value={resolution}
            onChange={(v) => setResolution(Number(v) || 0)}
            error={firstError(save.error, 'resolution_minutes')}
          />
        </Group>
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() =>
              save.mutate(
                {
                  id: policy?.id,
                  input: {
                    priority,
                    category_id: categoryId === null ? null : Number(categoryId),
                    response_minutes: response,
                    resolution_minutes: resolution,
                  },
                },
                { onSuccess: onClose },
              )
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function SlaTab({ dayMinutes }: { dayMinutes: number }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const policies = useSlaPolicies()
  const remove = useDeleteSlaPolicy()
  const [editing, setEditing] = useState<SlaPolicy | null | undefined>(undefined)

  return (
    <Stack>
      <Group justify="space-between" align="flex-start">
        <Text size="sm" c="dimmed" maw={560}>
          {t('settings.sla.hint', { minutes: dayMinutes })}
        </Text>
        <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>
          {t('settings.sla.add')}
        </Button>
      </Group>
      <Card withBorder p={0}>
        <Table.ScrollContainer minWidth={560}>
          <Table verticalSpacing="sm">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>{t('fields.category')}</Table.Th>
                <Table.Th>{t('fields.priority')}</Table.Th>
                <Table.Th ta="right">{t('settings.sla.response')}</Table.Th>
                <Table.Th ta="right">{t('settings.sla.resolution')}</Table.Th>
                <Table.Th w={96} />
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {policies.data?.map((p) => (
                <Table.Tr key={p.id}>
                  <Table.Td>{p.category?.name ?? t('settings.sla.general')}</Table.Td>
                  <Table.Td>{t(`priority.${p.priority}`)}</Table.Td>
                  <Table.Td ta="right" ff="monospace">
                    {p.response_minutes}
                  </Table.Td>
                  <Table.Td ta="right" ff="monospace">
                    {p.resolution_minutes}
                  </Table.Td>
                  <Table.Td>
                    <Group gap={4} wrap="nowrap">
                      <ActionIcon
                        variant="subtle"
                        aria-label={`${t('common:actions.edit')}: ${t(`priority.${p.priority}`)}`}
                        onClick={() => setEditing(p)}
                      >
                        <IconPencil size={16} />
                      </ActionIcon>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={`${t('common:actions.delete')}: ${t(`priority.${p.priority}`)}`}
                        onClick={async () => {
                          const ok = await confirmAction({
                            title: t('common:actions.delete'),
                            message: t('settings.sla.deleteConfirm'),
                            confirmLabel: t('common:actions.delete'),
                            danger: true,
                          })
                          if (ok) remove.mutate(p.id, { onError: fail })
                        }}
                      >
                        <IconTrash size={16} />
                      </ActionIcon>
                    </Group>
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      </Card>
      {editing !== undefined && <SlaModal policy={editing} onClose={() => setEditing(undefined)} />}
    </Stack>
  )
}

function CalendarTab({ calendar }: { calendar: CalendarSettings }) {
  const { t } = useTranslation(['helpdesk', 'common'])
  const save = useSaveCalendar()
  const [form, setForm] = useState(calendar)
  const weekdays = t('settings.calendar.weekdays', { returnObjects: true }) as string[]
  const error = (field: string) => firstError(save.error, field)

  const timeInput = (field: 'work_start' | 'work_end' | 'lunch_start' | 'lunch_end', label: string) => (
    <TextInput
      label={label}
      type="time"
      value={form[field]}
      onChange={(e) => setForm({ ...form, [field]: e.currentTarget.value })}
      error={error(field)}
    />
  )

  return (
    <Card withBorder p="lg" maw={760}>
      <Stack>
        <Checkbox.Group
          label={t('settings.calendar.workDays')}
          value={form.work_days.map(String)}
          onChange={(values) => setForm({ ...form, work_days: values.map(Number).sort() })}
          error={error('work_days')}
        >
          <Group mt="xs">
            {weekdays.map((day, index) => (
              <Checkbox key={day} value={String(index + 1)} label={day} />
            ))}
          </Group>
        </Checkbox.Group>
        <SimpleGrid cols={{ base: 2, sm: 4 }}>
          {timeInput('work_start', t('settings.calendar.workStart'))}
          {timeInput('work_end', t('settings.calendar.workEnd'))}
          {timeInput('lunch_start', t('settings.calendar.lunchStart'))}
          {timeInput('lunch_end', t('settings.calendar.lunchEnd'))}
        </SimpleGrid>
        <DatePickerInput
          type="multiple"
          label={t('settings.calendar.holidays')}
          description={t('settings.calendar.holidaysHint')}
          value={form.holidays}
          onChange={(values) => setForm({ ...form, holidays: values.map(String).sort() })}
          valueFormat="DD.MM.YYYY"
          clearable
          error={error('holidays')}
        />
        <SimpleGrid cols={{ base: 1, sm: 3 }}>
          <NumberInput
            label={t('settings.calendar.autoClose')}
            description={t('settings.calendar.autoCloseHint')}
            min={0}
            max={60}
            value={form.auto_close_days}
            onChange={(v) => setForm({ ...form, auto_close_days: Number(v) || 0 })}
          />
          <NumberInput
            label={t('settings.calendar.reopenDays')}
            min={0}
            max={90}
            value={form.reopen_days}
            onChange={(v) => setForm({ ...form, reopen_days: Number(v) || 0 })}
          />
          <NumberInput
            label={t('settings.calendar.dueSoon')}
            min={0}
            max={1440}
            value={form.due_soon_minutes}
            onChange={(v) => setForm({ ...form, due_soon_minutes: Number(v) || 0 })}
          />
        </SimpleGrid>
        <Group justify="space-between">
          <Text size="xs" c="dimmed">
            {t('settings.calendar.timezone', { tz: calendar.timezone })}
          </Text>
          <Button
            loading={save.isPending}
            onClick={() => {
              const { timezone: _timezone, ...input } = form
              save.mutate(input, {
                onSuccess: () => notifications.show({ color: 'green', message: t('settings.saved') }),
              })
            }}
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Card>
  )
}

function dayMinutes(c: CalendarSettings): number {
  const m = (v: string) => Number(v.slice(0, 2)) * 60 + Number(v.slice(3, 5))
  const lunch = Math.max(
    0,
    Math.min(m(c.lunch_end), m(c.work_end)) - Math.max(m(c.lunch_start), m(c.work_start)),
  )
  return Math.max(0, m(c.work_end) - m(c.work_start) - lunch)
}

export function HelpdeskSettingsPage() {
  const { t } = useTranslation('helpdesk')
  const calendar = useCalendar()

  return (
    <>
      <PageHeader title={t('settings.title')} description={t('settings.description')} />
      {calendar.error ? (
        <ErrorState error={calendar.error} onRetry={() => void calendar.refetch()} />
      ) : (
        <Tabs defaultValue="categories" keepMounted={false}>
          <Tabs.List mb="md">
            <Tabs.Tab value="categories">{t('settings.tabs.categories')}</Tabs.Tab>
            <Tabs.Tab value="sla">{t('settings.tabs.sla')}</Tabs.Tab>
            <Tabs.Tab value="calendar">{t('settings.tabs.calendar')}</Tabs.Tab>
          </Tabs.List>
          <Tabs.Panel value="categories">
            <CategoriesTab />
          </Tabs.Panel>
          <Tabs.Panel value="sla">
            <SlaTab dayMinutes={calendar.data ? dayMinutes(calendar.data) : 480} />
          </Tabs.Panel>
          <Tabs.Panel value="calendar">
            {calendar.data && <CalendarTab key={JSON.stringify(calendar.data)} calendar={calendar.data} />}
          </Tabs.Panel>
        </Tabs>
      )}
    </>
  )
}
