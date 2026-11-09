import { Button, Group, Modal, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { notifications } from '@mantine/notifications'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { DepartmentSelect } from '@/components/DepartmentSelect'
import { EmployeeSelect } from '@/components/EmployeeSelect'
import { OrganizationSelect } from '@/components/OrganizationSelect'
import * as s from './api'
import { useSimMutation } from './queries'
import { formatPhone, type SimAction, type SimCard } from './types'

const today = () => new Date().toISOString().slice(0, 10)

/** One form for every card action; fields depend on the action. */
export function SimActionModal({
  card,
  action,
  onClose,
}: {
  card: SimCard
  action: SimAction
  onClose: () => void
}) {
  const { t } = useTranslation(['sim', 'common', 'validation'])
  const [employeeId, setEmployeeId] = useState<number | null>(null)
  const [departmentId, setDepartmentId] = useState<number | null>(null)
  const [organizationId, setOrganizationId] = useState<number | null>(null)
  const [date, setDate] = useState<string | null>(today())
  const [text, setText] = useState('')
  const [iccid, setIccid] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const payload = (): Record<string, unknown> => {
    switch (action) {
      case 'assign':
        return { employee_id: employeeId, department_id: departmentId, assigned_at: date, note: text || null }
      case 'return':
        return { returned_at: date, note: text || null }
      case 'transfer':
        return { organization_id: organizationId, note: text || null }
      case 'replace':
        return { iccid, reason: text || null }
      case 'block':
      case 'cancel':
        return { reason: text || null }
      default:
        return {}
    }
  }
  const run = useSimMutation(() => s.simAction(card.id, action, payload()))

  const submit = () => {
    const local: Record<string, string> = {}
    if (action === 'assign' && !employeeId && !departmentId) local.employee_id = t('actions.holderRequired')
    if (action === 'transfer' && !organizationId) local.organization_id = t('validation:required')
    if (action === 'replace' && !iccid.trim()) local.iccid = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    run.mutate(undefined, {
      onSuccess: () => {
        notifications.show({ color: 'green', message: t(`actions.done.${action}`) })
        onClose()
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

  const textLabel =
    action === 'block' || action === 'cancel' || action === 'replace' ? t('actions.reason') : t('fields.note')

  return (
    <Modal
      opened
      onClose={onClose}
      title={`${t(`actions.${action}`)}: ${formatPhone(card.phone_number)}`}
      centered
    >
      <Stack>
        {action === 'cancel' && (
          <Text size="sm" c="red.8">
            {t('actions.cancelWarning')}
          </Text>
        )}
        {action === 'assign' && (
          <>
            <EmployeeSelect
              label={t('fields.employee')}
              organizationId={card.organization.id}
              value={employeeId}
              onChange={setEmployeeId}
              error={errors.employee_id}
              clearable
              data-autofocus
            />
            <DepartmentSelect
              label={t('fields.department')}
              description={t('actions.departmentHint')}
              organizationId={card.organization.id}
              value={departmentId}
              onChange={setDepartmentId}
              error={errors.department_id}
              clearable
            />
          </>
        )}
        {(action === 'assign' || action === 'return') && (
          <DateInput
            label={action === 'assign' ? t('fields.allocatedAt') : t('fields.returnedAt')}
            value={date}
            onChange={setDate}
            valueFormat="DD.MM.YYYY"
            maxDate={new Date()}
            error={errors.assigned_at ?? errors.returned_at}
          />
        )}
        {action === 'transfer' && (
          <OrganizationSelect
            label={t('actions.target')}
            required
            onlyActive
            value={organizationId}
            onChange={setOrganizationId}
            error={errors.organization_id}
          />
        )}
        {action === 'replace' && (
          <TextInput
            label={t('actions.newIccid')}
            required
            inputMode="numeric"
            autoComplete="off"
            value={iccid}
            onChange={(e) => setIccid(e.currentTarget.value)}
            error={errors.iccid}
            data-autofocus
          />
        )}
        {action !== 'unblock' && (
          <Textarea
            label={textLabel}
            autosize
            minRows={2}
            value={text}
            onChange={(e) => setText(e.currentTarget.value)}
            error={errors.note ?? errors.reason}
          />
        )}
        <Group justify="flex-end">
          <Button color={action === 'cancel' ? 'red' : undefined} loading={run.isPending} onClick={submit}>
            {t(`actions.${action}`)}
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
