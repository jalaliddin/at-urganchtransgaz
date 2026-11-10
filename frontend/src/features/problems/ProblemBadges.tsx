import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { ProblemPriority, ProblemStatus } from './types'

const STATUS_COLOR: Record<ProblemStatus, string> = {
  new: 'cobalt',
  investigating: 'orange',
  known_error: 'grape',
  resolved: 'green',
  closed: 'gray',
}
const PRIORITY_COLOR: Record<ProblemPriority, string> = {
  low: 'gray',
  normal: 'cobalt',
  high: 'orange',
  critical: 'red',
}

export function ProblemStatusBadge({ status }: { status: ProblemStatus }) {
  const { t } = useTranslation('problems')
  return (
    <Badge color={STATUS_COLOR[status]} variant="light" tt="none">
      {t(`status.${status}`)}
    </Badge>
  )
}

export function ProblemPriorityBadge({ priority }: { priority: ProblemPriority }) {
  const { t } = useTranslation('problems')
  return (
    <Badge color={PRIORITY_COLOR[priority]} variant="outline" tt="none">
      {t(`priority.${priority}`)}
    </Badge>
  )
}
