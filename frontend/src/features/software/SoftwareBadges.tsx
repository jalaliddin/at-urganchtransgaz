import { Badge, type MantineColor } from '@mantine/core'
import {
  IconAlertTriangle,
  IconHelpCircle,
  IconShieldCheck,
  IconShieldOff,
  IconShield,
} from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import type { AntivirusEffective } from './types'

const AV: Record<AntivirusEffective, { color: MantineColor; icon: typeof IconShield }> = {
  updated: { color: 'green', icon: IconShieldCheck },
  installed: { color: 'teal', icon: IconShield },
  outdated: { color: 'orange', icon: IconAlertTriangle },
  not_installed: { color: 'red', icon: IconShieldOff },
  unknown: { color: 'gray', icon: IconHelpCircle },
}

export function AntivirusBadge({ status }: { status: AntivirusEffective }) {
  const { t } = useTranslation('software')
  const { color, icon: Icon } = AV[status]
  return (
    <Badge
      color={color}
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={<Icon size={12} aria-hidden />}
    >
      {t(`antivirus.status.${status}`)}
    </Badge>
  )
}

export const antivirusColor = (status: AntivirusEffective): MantineColor => AV[status].color
