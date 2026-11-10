import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { ChangeRisk, ChangeStatus, ChangeType } from './types'

const STATUS_COLOR: Record<ChangeStatus, string> = {
  draft: 'gray',
  pending_approval: 'yellow',
  approved: 'cobalt',
  rejected: 'red',
  in_progress: 'orange',
  implemented: 'green',
  failed: 'red',
  cancelled: 'gray',
}
const RISK_COLOR: Record<ChangeRisk, string> = { low: 'gray', medium: 'orange', high: 'red' }

export function ChangeStatusBadge({ status }: { status: ChangeStatus }) {
  const { t } = useTranslation('changes')
  return (
    <Badge color={STATUS_COLOR[status]} variant="light" tt="none">
      {t(`status.${status}`)}
    </Badge>
  )
}

export function ChangeRiskBadge({ risk }: { risk: ChangeRisk }) {
  const { t } = useTranslation('changes')
  return (
    <Badge color={RISK_COLOR[risk]} variant="outline" tt="none">
      {t('riskLabel', { risk: t(`risk.${risk}`) })}
    </Badge>
  )
}

export function ChangeTypeBadge({ type }: { type: ChangeType }) {
  const { t } = useTranslation('changes')
  if (type === 'normal') return null
  return (
    <Badge color={type === 'emergency' ? 'red' : 'teal'} variant="filled" tt="none">
      {t(`type.${type}`)}
    </Badge>
  )
}
