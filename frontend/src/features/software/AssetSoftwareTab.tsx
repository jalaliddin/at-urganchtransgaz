import {
  ActionIcon,
  Alert,
  Badge,
  Button,
  Card,
  Code,
  CopyButton,
  Group,
  Modal,
  SegmentedControl,
  Select,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useDebouncedValue } from '@mantine/hooks'
import { notifications } from '@mantine/notifications'
import { IconCheck, IconCopy, IconKey, IconPlus, IconTrash } from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { useCan } from '@/hooks/usePermissions'
import { formatDate, formatDateTime } from '@/lib/format'
import * as s from './api'
import { useDeviceSoftware, useLicenses, useSoftwareList, useSoftwareMutation } from './queries'
import { AntivirusBadge } from './SoftwareBadges'
import type { DeviceSoftware } from './types'

const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })
const fieldErrors = (e: unknown) =>
  Object.fromEntries(Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']))

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" component="div">
        {children || '—'}
      </Text>
    </div>
  )
}

/** Plain token shown once, with a copy button and a warning. */
export function TokenModal({ token, onClose }: { token: string; onClose: () => void }) {
  const { t } = useTranslation('software')
  return (
    <Modal opened onClose={onClose} title={t('agents.tokenTitle')} centered size="lg">
      <Stack>
        <Alert color="orange" icon={<IconKey size={18} />}>
          {t('agents.tokenOnce')}
        </Alert>
        <Group wrap="nowrap" align="center">
          <Code block style={{ flex: 1, wordBreak: 'break-all' }} data-testid="agent-token">
            {token}
          </Code>
          <CopyButton value={token}>
            {({ copied, copy }) => (
              <Button
                variant="light"
                leftSection={copied ? <IconCheck size={16} /> : <IconCopy size={16} />}
                onClick={copy}
              >
                {copied ? t('agents.copied') : t('agents.copy')}
              </Button>
            )}
          </CopyButton>
        </Group>
        <Text size="xs" c="dimmed">
          {t('agents.usage')}
        </Text>
      </Stack>
    </Modal>
  )
}

function AntivirusModal({
  assetId,
  current,
  onClose,
}: {
  assetId: number
  current: DeviceSoftware['antivirus']
  onClose: () => void
}) {
  const { t } = useTranslation(['software', 'common'])
  const products = useSoftwareList({ 'filter[antivirus]': 1, per_page: 100 })
  const [productId, setProductId] = useState<string | null>(
    current.software_id ? String(current.software_id) : null,
  )
  const [state, setState] = useState<string>(current.state ?? 'installed')
  const [definitions, setDefinitions] = useState<string | null>(
    current.definitions_updated_at?.slice(0, 10) ?? null,
  )
  const [scan, setScan] = useState<string | null>(current.last_scan_at?.slice(0, 10) ?? null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useSoftwareMutation(() =>
    s.reportAntivirus(assetId, {
      software_id: productId ? Number(productId) : null,
      state,
      definitions_updated_at: state === 'installed' ? definitions : null,
      last_scan_at: state === 'installed' ? scan : null,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('antivirus.report')} centered>
      <Stack>
        <SegmentedControl
          aria-label={t('antivirus.state')}
          value={state}
          onChange={setState}
          data={[
            { value: 'installed', label: t('antivirus.state_installed') },
            { value: 'not_installed', label: t('antivirus.state_not_installed') },
          ]}
        />
        {state === 'installed' && (
          <>
            <Select
              label={t('antivirus.product')}
              clearable
              data={(products.data?.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))}
              value={productId}
              onChange={setProductId}
              error={errors.software_id}
            />
            <SimpleGrid cols={2}>
              <DateInput
                label={t('antivirus.definitions')}
                value={definitions}
                onChange={setDefinitions}
                valueFormat="DD.MM.YYYY"
                maxDate={new Date()}
                clearable
                error={errors.definitions_updated_at}
              />
              <DateInput
                label={t('antivirus.lastScan')}
                value={scan}
                onChange={setScan}
                valueFormat="DD.MM.YYYY"
                maxDate={new Date()}
                clearable
                error={errors.last_scan_at}
              />
            </SimpleGrid>
          </>
        )}
        <Group justify="flex-end">
          <Button
            loading={save.isPending}
            onClick={() =>
              save.mutate(undefined, { onSuccess: onClose, onError: (e) => setErrors(fieldErrors(e)) })
            }
          >
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function AddInstallationModal({ assetId, onClose }: { assetId: number; onClose: () => void }) {
  const { t } = useTranslation(['software', 'common', 'validation'])
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const catalog = useSoftwareList({
    per_page: 30,
    'filter[active]': 1,
    ...(debounced ? { search: debounced } : {}),
  })
  const [softwareId, setSoftwareId] = useState<string | null>(null)
  const [name, setName] = useState('')
  const [version, setVersion] = useState('')
  const [licenseId, setLicenseId] = useState<string | null>(null)
  const licenses = useLicenses({ 'filter[software]': softwareId ?? 0, per_page: 50 }, softwareId !== null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const add = useSoftwareMutation(() =>
    s.addInstallation(assetId, {
      software_id: softwareId ? Number(softwareId) : null,
      name: softwareId ? null : name,
      version: version || null,
      license_id: licenseId ? Number(licenseId) : null,
    }),
  )

  return (
    <Modal opened onClose={onClose} title={t('installations.add')} centered>
      <Stack>
        <Select
          label={t('installations.fromCatalog')}
          searchable
          clearable
          filter={({ options }) => options}
          searchValue={search}
          onSearchChange={setSearch}
          data={(catalog.data?.data ?? []).map((c) => ({
            value: String(c.id),
            label: c.vendor ? `${c.name} (${c.vendor})` : c.name,
          }))}
          value={softwareId}
          onChange={(v) => {
            setSoftwareId(v)
            setLicenseId(null)
          }}
          error={errors.software_id}
        />
        {!softwareId && (
          <TextInput
            label={t('installations.otherName')}
            description={t('installations.otherNameHint')}
            value={name}
            onChange={(e) => setName(e.currentTarget.value)}
            error={errors.name}
          />
        )}
        <TextInput
          label={t('installations.version')}
          value={version}
          onChange={(e) => setVersion(e.currentTarget.value)}
          error={errors.version}
        />
        {softwareId && (
          <Select
            label={t('installations.license')}
            clearable
            data={(licenses.data?.data ?? []).map((l) => ({
              value: String(l.id),
              label: `${l.number ?? '#' + l.id} — ${t('licenses.freeSeats', { count: l.free })}`,
              disabled: l.free === 0,
            }))}
            value={licenseId}
            onChange={setLicenseId}
            error={errors.license_id}
          />
        )}
        <Group justify="flex-end">
          <Button
            loading={add.isPending}
            onClick={() => {
              if (!softwareId && !name.trim()) {
                setErrors({ name: t('validation:required') })
                return
              }
              add.mutate(undefined, { onSuccess: onClose, onError: (e) => setErrors(fieldErrors(e)) })
            }}
          >
            {t('common:actions.add')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}

function AgentBlock({ assetId, data }: { assetId: number; data: DeviceSoftware }) {
  const { t } = useTranslation(['software', 'common'])
  const canAgents = useCan('software.agents')
  const [token, setToken] = useState<string | null>(null)
  const register = useSoftwareMutation(() => s.registerAgent(assetId, t('agents.defaultName')))
  const reissue = useSoftwareMutation((id: number) => s.issueToken(id))
  const revoke = useSoftwareMutation((id: number) => s.revokeAgent(id))
  const agent = data.agent

  return (
    <Card withBorder p="md">
      <Group justify="space-between" mb="xs">
        <Title order={3} fz="sm">
          {t('agents.title')}
        </Title>
        {agent && (
          <Badge color={agent.is_active ? 'green' : 'gray'} variant="light" tt="none">
            {agent.is_active ? t('agents.active') : t('agents.revoked')}
          </Badge>
        )}
      </Group>
      {agent ? (
        <Stack gap="xs">
          <SimpleGrid cols={{ base: 1, sm: 3 }}>
            <Field label={t('agents.platform')}>{agent.platform}</Field>
            <Field label={t('agents.lastCheckin')}>
              {agent.last_checkin_at ? formatDateTime(agent.last_checkin_at) : t('agents.never')}
            </Field>
            <Field label={t('agents.version')}>{agent.agent_version}</Field>
          </SimpleGrid>
          {canAgents && (
            <Group gap="sm">
              <Button
                size="xs"
                variant="light"
                leftSection={<IconKey size={14} />}
                loading={reissue.isPending}
                onClick={async () => {
                  if (
                    await confirmAction({
                      message: t('agents.reissueConfirm'),
                      confirmLabel: t('agents.reissue'),
                    })
                  )
                    reissue.mutate(agent.id, { onSuccess: (r) => setToken(r.token), onError: fail })
                }}
              >
                {t('agents.reissue')}
              </Button>
              {agent.is_active && (
                <Button
                  size="xs"
                  variant="subtle"
                  color="red"
                  loading={revoke.isPending}
                  onClick={async () => {
                    if (
                      await confirmAction({
                        message: t('agents.revokeConfirm'),
                        confirmLabel: t('agents.revoke'),
                        danger: true,
                      })
                    )
                      revoke.mutate(agent.id, { onError: fail })
                  }}
                >
                  {t('agents.revoke')}
                </Button>
              )}
            </Group>
          )}
        </Stack>
      ) : (
        <Stack gap="xs" align="flex-start">
          <Text size="sm" c="dimmed">
            {t('agents.none')}
          </Text>
          {canAgents && (
            <Button
              size="xs"
              variant="light"
              leftSection={<IconPlus size={14} />}
              loading={register.isPending}
              onClick={() =>
                register.mutate(undefined, { onSuccess: (r) => setToken(r.token), onError: fail })
              }
            >
              {t('agents.register')}
            </Button>
          )}
        </Stack>
      )}
      {token && <TokenModal token={token} onClose={() => setToken(null)} />}
    </Card>
  )
}

/** "Dasturlar" tab of the asset card: antivirus, installed software, agent. */
export function AssetSoftwareTab({ assetId }: { assetId: number }) {
  const { t } = useTranslation(['software', 'common'])
  const query = useDeviceSoftware(assetId)
  const canInstall = useCan('software.install')
  const [dialog, setDialog] = useState<'antivirus' | 'add' | null>(null)
  const remove = useSoftwareMutation((id: number) => s.removeInstallation(id))
  const data = query.data
  if (!data) return null

  return (
    <Stack gap="md">
      <Card withBorder p="md">
        <Group justify="space-between" mb="xs">
          <Title order={3} fz="sm">
            {t('antivirus.title')}
          </Title>
          {canInstall && (
            <Button size="xs" variant="subtle" onClick={() => setDialog('antivirus')}>
              {t('antivirus.report')}
            </Button>
          )}
        </Group>
        <SimpleGrid cols={{ base: 1, sm: 2, lg: 4 }}>
          <Field label={t('antivirus.state')}>
            <AntivirusBadge status={data.antivirus.effective} />
          </Field>
          <Field label={t('antivirus.product')}>{data.antivirus.product}</Field>
          <Field label={t('antivirus.definitions')}>
            {data.antivirus.definitions_updated_at
              ? formatDateTime(data.antivirus.definitions_updated_at)
              : null}
          </Field>
          <Field label={t('antivirus.reported')}>
            {data.antivirus.reported_at
              ? `${formatDateTime(data.antivirus.reported_at)} · ${t(`sources.${data.antivirus.source}`)}`
              : null}
          </Field>
        </SimpleGrid>
      </Card>

      <Card withBorder p={0}>
        <Group justify="space-between" p="md" pb={0}>
          <Stack gap={0}>
            <Title order={3} fz="sm">
              {t('installations.title')}
            </Title>
            <Text size="xs" c="dimmed">
              {t('installations.inventoried', {
                date: data.inventoried_at ? formatDateTime(data.inventoried_at) : '—',
              })}
            </Text>
          </Stack>
          {canInstall && (
            <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => setDialog('add')}>
              {t('installations.add')}
            </Button>
          )}
        </Group>
        {data.installations.length === 0 ? (
          <Text size="sm" c="dimmed" p="md">
            {t('installations.empty')}
          </Text>
        ) : (
          <Table.ScrollContainer minWidth={560}>
            <Table verticalSpacing="xs">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('installations.name')}</Table.Th>
                  <Table.Th>{t('installations.version')}</Table.Th>
                  <Table.Th>{t('installations.license')}</Table.Th>
                  <Table.Th>{t('installations.source')}</Table.Th>
                  {canInstall && <Table.Th w={48} />}
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {data.installations.map((i) => (
                  <Table.Tr key={i.id}>
                    <Table.Td>
                      <Text size="sm">{i.software?.name ?? i.name}</Text>
                      {!i.software && (
                        <Badge size="xs" color="gray" variant="light" tt="none">
                          {t('installations.unrecognized')}
                        </Badge>
                      )}
                    </Table.Td>
                    <Table.Td ff="monospace">{i.version ?? '—'}</Table.Td>
                    <Table.Td>{i.license?.number ?? '—'}</Table.Td>
                    <Table.Td>
                      <Text size="xs">{t(`sources.${i.source}`)}</Text>
                      <Text size="xs" c="dimmed">
                        {i.last_seen_at ? formatDate(i.last_seen_at) : ''}
                      </Text>
                    </Table.Td>
                    {canInstall && (
                      <Table.Td>
                        <ActionIcon
                          variant="subtle"
                          color="red"
                          aria-label={`${t('common:actions.delete')}: ${i.software?.name ?? i.name}`}
                          onClick={() => remove.mutate(i.id, { onError: fail })}
                        >
                          <IconTrash size={16} />
                        </ActionIcon>
                      </Table.Td>
                    )}
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        )}
      </Card>

      <AgentBlock assetId={assetId} data={data} />
      {dialog === 'antivirus' && (
        <AntivirusModal assetId={assetId} current={data.antivirus} onClose={() => setDialog(null)} />
      )}
      {dialog === 'add' && <AddInstallationModal assetId={assetId} onClose={() => setDialog(null)} />}
    </Stack>
  )
}
