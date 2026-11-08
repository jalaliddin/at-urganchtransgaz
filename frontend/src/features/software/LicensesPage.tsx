import {
  ActionIcon,
  Button,
  Checkbox,
  Code,
  Group,
  Modal,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  Text,
  TextInput,
  Textarea,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { IconEye, IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { DataTable } from '@/components/DataTable'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { PageHeader } from '@/components/PageHeader'
import { useMe } from '@/features/auth/queries'
import { ExportMenu } from '@/features/exports/ExportMenu'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate, formatMoney } from '@/lib/format'
import * as s from './api'
import { useLicenses, useSoftwareList, useSoftwareMutation } from './queries'
import type { License } from './types'

function LicenseModal({ license, onClose }: { license?: License; onClose: () => void }) {
  const { t } = useTranslation(['software', 'common', 'validation'])
  const { data: me } = useMe()
  const catalog = useSoftwareList({ per_page: 100 })
  const [softwareId, setSoftwareId] = useState<string | null>(license ? String(license.software.id) : null)
  const [organizationId, setOrganizationId] = useState<number | null>(
    license?.organization.id ?? me?.organization?.id ?? null,
  )
  const [number, setNumber] = useState(license?.number ?? '')
  const [key, setKey] = useState('')
  const [seats, setSeats] = useState<string | number>(license?.seats ?? 1)
  const [validFrom, setValidFrom] = useState<string | null>(license?.valid_from ?? null)
  const [expires, setExpires] = useState<string | null>(license?.expires_at ?? null)
  const [supplier, setSupplier] = useState(license?.supplier ?? '')
  const [cost, setCost] = useState<string | number>(license?.cost ?? '')
  const [note, setNote] = useState(license?.note ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useSoftwareMutation(() =>
    s.saveLicense(
      {
        software_id: softwareId ? Number(softwareId) : null,
        ...(license ? {} : { organization_id: organizationId }),
        number: number || null,
        ...(key ? { license_key: key } : {}),
        seats: Number(seats),
        valid_from: validFrom,
        expires_at: expires,
        supplier: supplier || null,
        cost: cost === '' ? null : String(cost),
        note: note || null,
      },
      license?.id,
    ),
  )

  return (
    <Modal
      opened
      onClose={onClose}
      title={license ? t('licenses.edit') : t('licenses.add')}
      centered
      size="lg"
    >
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          <Select
            label={t('fields.name')}
            required
            searchable
            data={(catalog.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))}
            value={softwareId}
            onChange={setSoftwareId}
            error={errors.software_id}
          />
          {!license && (
            <OrganizationSelect
              label={t('licenses.owner')}
              required
              onlyActive
              value={organizationId}
              onChange={setOrganizationId}
              error={errors.organization_id}
            />
          )}
          <TextInput
            label={t('licenses.number')}
            value={number}
            onChange={(e) => setNumber(e.currentTarget.value)}
          />
          <NumberInput
            label={t('licenses.seats')}
            required
            min={1}
            value={seats}
            onChange={setSeats}
            error={errors.seats}
          />
          <DateInput
            label={t('licenses.validFrom')}
            value={validFrom}
            onChange={setValidFrom}
            valueFormat="DD.MM.YYYY"
            clearable
          />
          <DateInput
            label={t('licenses.expiresAt')}
            value={expires}
            onChange={setExpires}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.expires_at}
          />
          <TextInput
            label={t('licenses.supplier')}
            value={supplier}
            onChange={(e) => setSupplier(e.currentTarget.value)}
          />
          <NumberInput
            label={t('licenses.cost')}
            min={0}
            decimalScale={2}
            thousandSeparator=" "
            value={cost}
            onChange={setCost}
          />
        </SimpleGrid>
        <TextInput
          label={t('licenses.key')}
          description={license?.has_key ? t('licenses.keyKeep') : t('licenses.keyHint')}
          value={key}
          onChange={(e) => setKey(e.currentTarget.value)}
          autoComplete="off"
        />
        <Textarea
          label={t('fields.note')}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() => {
              if (!softwareId || (!license && !organizationId)) {
                setErrors({
                  ...(softwareId ? {} : { software_id: t('validation:required') }),
                  ...(!license && !organizationId ? { organization_id: t('validation:required') } : {}),
                })
                return
              }
              save.mutate(undefined, {
                onSuccess: onClose,
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

export function LicensesPage() {
  const { t } = useTranslation(['software', 'common'])
  const canManage = useCan('software.manage')
  const canExport = useCan('software.export')
  const params = useTableParams({
    defaultSort: 'expires_at',
    filterKeys: ['software', 'expiring', 'organization'],
  })
  const list = useLicenses(params.apiParams)
  const [editing, setEditing] = useState<License | null | undefined>(undefined)
  const [revealed, setRevealed] = useState<string | null>(null)
  const reveal = useSoftwareMutation((id: number) => s.revealLicense(id))
  const remove = useSoftwareMutation((id: number) => s.deleteLicense(id))
  const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

  return (
    <>
      <PageHeader
        title={t('licenses.title')}
        description={t('licenses.description')}
        actions={
          <Group gap="sm">
            {canExport && <ExportMenu type="software_licenses" filters={params.filters} />}
            {canManage && (
              <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>
                {t('licenses.add')}
              </Button>
            )}
          </Group>
        }
      />
      <DataTable<License>
        storeKey="software-licenses"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('licenses.empty')}
        toolbar={
          <Checkbox
            label={t('licenses.expiringOnly')}
            checked={params.filters.expiring === '1'}
            onChange={(e) => params.setFilter('expiring', e.currentTarget.checked ? '1' : null)}
          />
        }
        columns={[
          {
            accessor: 'software',
            title: t('fields.name'),
            render: (l) => (
              <>
                <Text size="sm" fw={500}>
                  {l.software.name}
                </Text>
                <Text size="xs" c="dimmed">
                  {l.number ?? `#${l.id}`}
                </Text>
              </>
            ),
          },
          { accessor: 'organization', title: t('licenses.owner'), render: (l) => l.organization.name },
          {
            accessor: 'seats',
            title: t('licenses.seats'),
            render: (l) => (
              <Text size="sm" c={l.free === 0 ? 'orange.8' : undefined}>
                {t('licenses.usage', { used: l.used, seats: l.seats })}
              </Text>
            ),
          },
          {
            accessor: 'expires_at',
            title: t('licenses.expiresAt'),
            noWrap: true,
            render: (l) => (l.expires_at ? formatDate(l.expires_at) : '—'),
          },
          {
            accessor: 'license_key_masked',
            title: t('licenses.key'),
            render: (l) => (l.license_key_masked ? <Code>{l.license_key_masked}</Code> : '—'),
          },
          {
            accessor: 'cost',
            title: t('licenses.cost'),
            textAlign: 'right',
            render: (l) => formatMoney(l.cost),
          },
          {
            accessor: 'actions',
            title: '',
            render: (l) =>
              canManage && (
                <Group gap={4} wrap="nowrap">
                  {l.has_key && (
                    <ActionIcon
                      variant="subtle"
                      aria-label={`${t('licenses.reveal')}: ${l.software.name}`}
                      loading={reveal.isPending}
                      onClick={() =>
                        reveal.mutate(l.id, {
                          onSuccess: (r) => setRevealed(r.license_key ?? ''),
                          onError: fail,
                        })
                      }
                    >
                      <IconEye size={16} />
                    </ActionIcon>
                  )}
                  <ActionIcon
                    variant="subtle"
                    aria-label={`${t('common:actions.edit')}: ${l.software.name}`}
                    onClick={() => setEditing(l)}
                  >
                    <IconPencil size={16} />
                  </ActionIcon>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    aria-label={`${t('common:actions.delete')}: ${l.software.name}`}
                    onClick={async () => {
                      if (
                        await confirmAction({
                          message: t('licenses.deleteConfirm'),
                          confirmLabel: t('common:actions.delete'),
                          danger: true,
                        })
                      )
                        remove.mutate(l.id, { onError: fail })
                    }}
                  >
                    <IconTrash size={16} />
                  </ActionIcon>
                </Group>
              ),
          },
        ]}
      />
      {editing !== undefined && (
        <LicenseModal license={editing ?? undefined} onClose={() => setEditing(undefined)} />
      )}
      {revealed !== null && (
        <Modal opened onClose={() => setRevealed(null)} title={t('licenses.key')} centered>
          <Stack>
            <Code block data-testid="license-key">
              {revealed}
            </Code>
            <Text size="xs" c="dimmed">
              {t('licenses.revealAudited')}
            </Text>
          </Stack>
        </Modal>
      )}
    </>
  )
}
