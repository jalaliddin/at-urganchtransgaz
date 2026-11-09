import {
  Button,
  Checkbox,
  Group,
  Modal,
  NumberInput,
  Select,
  SimpleGrid,
  Stack,
  TextInput,
  Textarea,
} from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useMe } from '@/features/auth/queries'
import * as s from './api'
import { useContracts, useProviders, useServicesMutation } from './queries'
import { BILLING_CYCLES, SERVICE_TYPES, type ItService } from './types'

export function ServiceModal({ service, onClose }: { service?: ItService; onClose: () => void }) {
  const { t } = useTranslation(['services', 'common', 'validation'])
  const navigate = useNavigate()
  const { data: me } = useMe()
  const providers = useProviders({ 'filter[active]': 1 })
  const [organizationId, setOrganizationId] = useState<number | null>(
    service?.organization.id ?? me?.organization?.id ?? null,
  )
  const [providerId, setProviderId] = useState<string | null>(service ? String(service.provider.id) : null)
  const contracts = useContracts(
    { per_page: 100, 'filter[organization]': organizationId ?? 0, 'filter[provider]': providerId ?? 0 },
    organizationId !== null && providerId !== null,
  )
  const [contractId, setContractId] = useState<string | null>(
    service?.contract ? String(service.contract.id) : null,
  )
  const [type, setType] = useState<string | null>(service?.type ?? 'internet')
  const [name, setName] = useState(service?.name ?? '')
  const [address, setAddress] = useState(service?.address ?? '')
  const [identifier, setIdentifier] = useState(service?.identifier ?? '')
  const [tariff, setTariff] = useState(service?.tariff ?? '')
  const [fee, setFee] = useState<string | number>(service?.fee ?? '')
  const [cycle, setCycle] = useState<string | null>(service?.billing_cycle ?? 'monthly')
  const [dueDay, setDueDay] = useState<string | number>(service?.due_day ?? 10)
  const [started, setStarted] = useState<string | null>(service?.started_at ?? null)
  const [ended, setEnded] = useState<string | null>(service?.ended_at ?? null)
  const [active, setActive] = useState(service?.is_active ?? true)
  const [note, setNote] = useState(service?.note ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useServicesMutation(() =>
    s.saveService(
      {
        ...(service ? {} : { organization_id: organizationId }),
        provider_id: providerId ? Number(providerId) : null,
        contract_id: contractId ? Number(contractId) : null,
        type,
        name,
        address: address || null,
        identifier: identifier || null,
        tariff: tariff || null,
        fee: fee === '' ? '0' : String(fee),
        billing_cycle: cycle,
        due_day: Number(dueDay),
        started_at: started,
        ended_at: ended,
        is_active: active,
        note: note || null,
      },
      service?.id,
    ),
  )

  const submit = () => {
    const local: Record<string, string> = {}
    if (!name.trim()) local.name = t('validation:required')
    if (!providerId) local.provider_id = t('validation:required')
    if (!service && !organizationId) local.organization_id = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => {
        onClose()
        if (!service) navigate(`/services/${saved.id}`)
      },
      onError: (e) => {
        const fe = Object.fromEntries(
          Object.entries(toApiError(e).fieldErrors).map(([k, v]) => [k, v[0] ?? '']),
        )
        if (Object.keys(fe).length) setErrors(fe)
        else notifications.show({ color: 'red', message: toApiError(e).message })
      },
    })
  }

  return (
    <Modal
      opened
      onClose={onClose}
      title={service ? t('services.edit') : t('services.add')}
      centered
      size="lg"
    >
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {!service && (
            <OrganizationSelect
              label={t('fields.organization')}
              required
              onlyActive
              value={organizationId}
              onChange={(v) => {
                setOrganizationId(v)
                setContractId(null)
              }}
              error={errors.organization_id}
            />
          )}
          <Select
            label={t('fields.provider')}
            required
            searchable
            data={(providers.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))}
            value={providerId}
            onChange={(v) => {
              setProviderId(v)
              setContractId(null)
            }}
            error={errors.provider_id}
          />
          <Select
            label={t('services.contract')}
            clearable
            disabled={!organizationId || !providerId}
            data={(contracts.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.number }))}
            value={contractId}
            onChange={setContractId}
            error={errors.contract_id}
          />
          <Select
            label={t('services.type')}
            required
            data={SERVICE_TYPES.map((v) => ({ value: v, label: t(`serviceType.${v}`) }))}
            value={type}
            onChange={setType}
            error={errors.type}
          />
          <TextInput
            label={t('services.name')}
            required
            value={name}
            onChange={(e) => setName(e.currentTarget.value)}
            error={errors.name}
          />
          <TextInput
            label={t('services.identifier')}
            value={identifier}
            onChange={(e) => setIdentifier(e.currentTarget.value)}
          />
          <TextInput
            label={t('services.tariff')}
            value={tariff}
            onChange={(e) => setTariff(e.currentTarget.value)}
          />
          <TextInput
            label={t('services.address')}
            value={address}
            onChange={(e) => setAddress(e.currentTarget.value)}
          />
          <NumberInput
            label={t('services.fee')}
            min={0}
            decimalScale={2}
            thousandSeparator=" "
            value={fee}
            onChange={setFee}
            error={errors.fee}
          />
          <Select
            label={t('services.cycle')}
            required
            data={BILLING_CYCLES.map((v) => ({ value: v, label: t(`billingCycle.${v}`) }))}
            value={cycle}
            onChange={setCycle}
          />
          <NumberInput
            label={t('services.dueDay')}
            description={t('services.dueDayHint')}
            min={1}
            max={28}
            value={dueDay}
            onChange={setDueDay}
            error={errors.due_day}
          />
          <DateInput
            label={t('services.startedAt')}
            value={started}
            onChange={setStarted}
            valueFormat="DD.MM.YYYY"
            clearable
          />
          <DateInput
            label={t('services.endedAt')}
            value={ended}
            onChange={setEnded}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.ended_at}
          />
        </SimpleGrid>
        <Checkbox
          label={t('services.active')}
          checked={active}
          onChange={(e) => setActive(e.currentTarget.checked)}
        />
        <Textarea
          label={t('fields.note')}
          autosize
          minRows={2}
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
        <Group justify="flex-end">
          <Button loading={save.isPending} onClick={submit}>
            {t('common:actions.save')}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
