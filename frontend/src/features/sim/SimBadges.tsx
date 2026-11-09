import { Badge } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import type { SimStatus } from './types'

const COLORS: Record<SimStatus, string> = {
  in_stock: 'gray',
  active: 'green',
  blocked: 'orange',
  returned: 'blue',
  cancelled: 'red',
}

export function SimStatusBadge({ status }: { status: SimStatus }) {
  const { t } = useTranslation('sim')
  return (
    <Badge color={COLORS[status]} variant="light" style={{ flexShrink: 0 }}>
      {t(`status.${status}`)}
    </Badge>
  )
}
