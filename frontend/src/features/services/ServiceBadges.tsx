import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { ContractStatus, InvoiceStatus } from './types'

const INVOICE_COLORS: Record<InvoiceStatus, string> = {
  planned: 'gray',
  awaiting_invoice: 'blue',
  due: 'yellow',
  partially_paid: 'orange',
  paid: 'green',
  overdue: 'red',
}
const CONTRACT_COLORS: Record<ContractStatus, string> = {
  active: 'green',
  expired: 'gray',
  terminated: 'red',
}

export function InvoiceStatusBadge({ status }: { status: InvoiceStatus }) {
  const { t } = useTranslation('services')
  return (
    <Badge color={INVOICE_COLORS[status]} variant="light" style={{ flexShrink: 0 }}>
      {t(`invoiceStatus.${status}`)}
    </Badge>
  )
}

export function ContractStatusBadge({ status }: { status: ContractStatus }) {
  const { t } = useTranslation('services')
  return (
    <Badge color={CONTRACT_COLORS[status]} variant="light" style={{ flexShrink: 0 }}>
      {t(`contractStatus.${status}`)}
    </Badge>
  )
}
