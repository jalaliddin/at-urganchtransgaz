import { Badge, type MantineColor } from '@mantine/core'
import {
  IconAlertTriangle,
  IconArrowBackUp,
  IconArrowDownRight,
  IconArrowUpRight,
  IconArrowsExchange,
  IconHourglassLow,
  IconAdjustmentsHorizontal,
} from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import type { StockCountStatus, StockDocumentType } from './types'

const TYPE_STYLE: Record<StockDocumentType, { color: MantineColor; icon: typeof IconArrowDownRight }> = {
  receipt: { color: 'green', icon: IconArrowDownRight },
  issue: { color: 'orange', icon: IconArrowUpRight },
  return: { color: 'turquoise', icon: IconArrowBackUp },
  transfer: { color: 'cobalt', icon: IconArrowsExchange },
  adjustment: { color: 'gray', icon: IconAdjustmentsHorizontal },
}

export function DocumentTypeBadge({ type }: { type: StockDocumentType }) {
  const { t } = useTranslation('warehouse')
  const { color, icon: Icon } = TYPE_STYLE[type]
  return (
    <Badge
      color={color}
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={<Icon size={12} aria-hidden />}
    >
      {t(`types.${type}`)}
    </Badge>
  )
}

const COUNT_COLORS: Record<StockCountStatus, MantineColor> = {
  open: 'orange',
  closed: 'green',
  cancelled: 'gray',
}

export function CountStatusBadge({ status }: { status: StockCountStatus }) {
  const { t } = useTranslation('warehouse')
  return (
    <Badge color={COUNT_COLORS[status]} variant="light" radius="sm" tt="none" fw={500}>
      {t(`counts.status.${status}`)}
    </Badge>
  )
}

export function LowStockBadge() {
  const { t } = useTranslation('warehouse')
  return (
    <Badge
      color="red"
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={<IconAlertTriangle size={12} aria-hidden />}
    >
      {t('balances.low')}
    </Badge>
  )
}

/** Shown when a date is within the notice period (or past). */
export function ExpiryBadge({ date, soon }: { date: string; soon: boolean }) {
  const { t } = useTranslation('warehouse')
  if (!soon) return null
  return (
    <Badge
      color="orange"
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={<IconHourglassLow size={12} aria-hidden />}
    >
      {t('balances.expiring', { date })}
    </Badge>
  )
}

/** Quantity without trailing zeros: "4.000" → "4", "2.500" → "2.5". */
export function qty(value: string | null | undefined): string {
  if (value === null || value === undefined) return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString('ru-RU', { maximumFractionDigits: 3 }) : value
}
