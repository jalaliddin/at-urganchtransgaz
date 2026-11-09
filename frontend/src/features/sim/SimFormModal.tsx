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
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { toApiError } from '@/api/errors'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import { useMe } from '@/features/auth/queries'
import { useProviders } from '@/features/services/queries'
import * as s from './api'
import { useSimMutation } from './queries'
import type { SimCard } from './types'

/** Registers a card (number, ICCID, operator, holder organization) or edits its tariff data. */
export function SimFormModal({ card, onClose }: { card?: SimCard; onClose: () => void }) {
  const { t } = useTranslation(['sim', 'common', 'validation'])
  const navigate = useNavigate()
  const { data: me } = useMe()
  const operators = useProviders({ 'filter[mobile]': 1, 'filter[active]': 1 })
  const [organizationId, setOrganizationId] = useState<number | null>(me?.organization?.id ?? null)
  const [providerId, setProviderId] = useState<string | null>(card ? String(card.provider.id) : null)
  const [phone, setPhone] = useState(card?.phone_number ?? '')
  const [iccid, setIccid] = useState('')
  const [tariff, setTariff] = useState(card?.tariff ?? '')
  const [account, setAccount] = useState(card?.account_number ?? '')
  const [fee, setFee] = useState<string | number>(card?.monthly_fee ?? '')
  const [note, setNote] = useState(card?.note ?? '')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useSimMutation(() =>
    s.saveSim(
      {
        ...(card ? {} : { organization_id: organizationId, iccid: iccid || null }),
        provider_id: providerId ? Number(providerId) : null,
        phone_number: phone,
        tariff: tariff || null,
        account_number: account || null,
        monthly_fee: fee === '' ? null : String(fee),
        note: note || null,
      },
      card?.id,
    ),
  )

  const submit = () => {
    const local: Record<string, string> = {}
    if (!providerId) local.provider_id = t('validation:required')
    if (!phone.trim()) local.phone_number = t('validation:required')
    if (!card && !organizationId) local.organization_id = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(undefined, {
      onSuccess: (saved) => {
        onClose()
        if (!card) navigate(`/sim/${saved.id}`)
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
    <Modal opened onClose={onClose} title={card ? t('form.edit') : t('form.add')} centered size="lg">
      <Stack>
        <SimpleGrid cols={{ base: 1, sm: 2 }}>
          {!card && (
            <OrganizationSelect
              label={t('fields.organization')}
              required
              onlyActive
              value={organizationId}
              onChange={setOrganizationId}
              error={errors.organization_id}
            />
          )}
          <Select
            label={t('fields.operator')}
            required
            data={(operators.data ?? []).map((o) => ({ value: String(o.id), label: o.name }))}
            value={providerId}
            onChange={setProviderId}
            error={errors.provider_id}
          />
          <TextInput
            label={t('fields.phone')}
            required
            placeholder="+998 90 123 45 67"
            inputMode="tel"
            value={phone}
            onChange={(e) => setPhone(e.currentTarget.value)}
            error={errors.phone_number}
          />
          {!card && (
            <TextInput
              label={t('fields.iccid')}
              description={t('form.iccidHint')}
              inputMode="numeric"
              autoComplete="off"
              value={iccid}
              onChange={(e) => setIccid(e.currentTarget.value)}
              error={errors.iccid}
            />
          )}
          <TextInput
            label={t('fields.tariff')}
            value={tariff}
            onChange={(e) => setTariff(e.currentTarget.value)}
          />
          <TextInput
            label={t('fields.account')}
            value={account}
            onChange={(e) => setAccount(e.currentTarget.value)}
          />
          <NumberInput
            label={t('fields.monthlyFee')}
            min={0}
            decimalScale={2}
            thousandSeparator=" "
            value={fee}
            onChange={setFee}
            error={errors.monthly_fee}
          />
        </SimpleGrid>
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
