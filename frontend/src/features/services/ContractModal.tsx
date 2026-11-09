import {
  Button,
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
import { useProviders, useServicesMutation } from './queries'
import type { Contract } from './types'

export function ContractModal({ contract, onClose }: { contract?: Contract; onClose: () => void }) {
  const { t } = useTranslation(['services', 'common', 'validation'])
  const navigate = useNavigate()
  const { data: me } = useMe()
  const providers = useProviders({ 'filter[active]': 1 })
  const [organizationId, setOrganizationId] = useState<number | null>(me?.organization?.id ?? null)
  const [providerId, setProviderId] = useState<string | null>(contract ? String(contract.provider.id) : null)
  const [number, setNumber] = useState(contract?.number ?? '')
  const [subject, setSubject] = useState(contract?.subject ?? '')
  const [signed, setSigned] = useState<string | null>(contract?.signed_at ?? null)
  const [from, setFrom] = useState<string | null>(contract?.valid_from ?? null)
  const [to, setTo] = useState<string | null>(contract?.valid_to ?? null)
  const [terminated, setTerminated] = useState<string | null>(contract?.terminated_at ?? null)
  const [amount, setAmount] = useState<string | number>(contract?.amount ?? '')
  const [note, setNote] = useState(contract?.note ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useServicesMutation(() =>
    s.saveContract(
      {
        ...(contract
          ? {}
          : { organization_id: organizationId, provider_id: providerId ? Number(providerId) : null }),
        number,
        subject: subject || null,
        signed_at: signed,
        valid_from: from,
        valid_to: to,
        ...(contract ? { terminated_at: terminated } : {}),
        amount: amount === '' ? null : String(amount),
        note: note || null,
      },
      contract?.id,
    ),
  )

  const submit = () => {
    const local: Record<string, string> = {}
    if (!number.trim()) local.number = t('validation:required')
    if (!contract && !providerId) local.provider_id = t('validation:required')
    if (!contract && !organizationId) local.organization_id = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => {
        onClose()
        if (!contract) navigate(`/services/contracts/${saved.id}`)
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
      title={contract ? t('contracts.edit') : t('contracts.add')}
      centered
      size="lg"
    >
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {!contract && (
            <>
              <OrganizationSelect
                label={t('fields.organization')}
                required
                onlyActive
                value={organizationId}
                onChange={setOrganizationId}
                error={errors.organization_id}
              />
              <Select
                label={t('fields.provider')}
                required
                searchable
                data={(providers.data ?? []).map((p) => ({ value: String(p.id), label: p.name }))}
                value={providerId}
                onChange={setProviderId}
                error={errors.provider_id}
              />
            </>
          )}
          <TextInput
            label={t('contracts.number')}
            required
            value={number}
            onChange={(e) => setNumber(e.currentTarget.value)}
            error={errors.number}
          />
          <DateInput
            label={t('contracts.signedAt')}
            value={signed}
            onChange={setSigned}
            valueFormat="DD.MM.YYYY"
            clearable
          />
          <DateInput
            label={t('contracts.validFrom')}
            value={from}
            onChange={setFrom}
            valueFormat="DD.MM.YYYY"
            clearable
          />
          <DateInput
            label={t('contracts.validTo')}
            value={to}
            onChange={setTo}
            valueFormat="DD.MM.YYYY"
            clearable
            error={errors.valid_to}
          />
          <NumberInput
            label={t('contracts.amount')}
            min={0}
            decimalScale={2}
            thousandSeparator=" "
            value={amount}
            onChange={setAmount}
          />
          {contract && (
            <DateInput
              label={t('contracts.terminatedAt')}
              value={terminated}
              onChange={setTerminated}
              valueFormat="DD.MM.YYYY"
              clearable
            />
          )}
        </SimpleGrid>
        <TextInput
          label={t('contracts.subject')}
          value={subject}
          onChange={(e) => setSubject(e.currentTarget.value)}
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
