import { Badge, type MantineColor } from '@mantine/core'
import { IconAlertTriangle, IconShieldCheck } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import type { Asset, AssetStatus, TransferStatus } from './types'

const STATUS_COLORS: Record<AssetStatus, MantineColor> = {
  in_stock: 'cobalt',
  in_use: 'green',
  in_repair: 'orange',
  reserve: 'turquoise',
  written_off: 'gray',
  lost: 'red',
}

export function AssetStatusBadge({ status }: { status: AssetStatus }) {
  const { t } = useTranslation('assets')
  return (
    <Badge color={STATUS_COLORS[status]} variant="light" radius="sm" tt="none" fw={500}>
      {t(`status.${status}`)}
    </Badge>
  )
}

export function WarrantyBadge({ asset }: { asset: Pick<Asset, 'warranty_state' | 'warranty_until'> }) {
  const { t } = useTranslation('assets')
  if (!asset.warranty_state || asset.warranty_state === 'ok') return null
  const expired = asset.warranty_state === 'expired'
  return (
    <Badge
      color={expired ? 'gray' : 'orange'}
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={
        expired ? <IconShieldCheck size={12} aria-hidden /> : <IconAlertTriangle size={12} aria-hidden />
      }
    >
      {t(`warranty.${asset.warranty_state}`)}
    </Badge>
  )
}

const TRANSFER_COLORS: Record<TransferStatus, MantineColor> = {
  draft: 'gray',
  sent: 'orange',
  received: 'green',
  cancelled: 'red',
}

export function TransferStatusBadge({ status }: { status: TransferStatus }) {
  const { t } = useTranslation('assets')
  return (
    <Badge color={TRANSFER_COLORS[status]} variant="light" radius="sm" tt="none" fw={500}>
      {t(`transfers.status.${status}`)}
    </Badge>
  )
}
