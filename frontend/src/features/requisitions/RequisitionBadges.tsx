import { Badge, type MantineColor } from '@mantine/core'
import {
  IconCheck,
  IconCircleCheck,
  IconCircleDashed,
  IconClock,
  IconEye,
  IconHelpCircle,
  IconLock,
  IconPackage,
  IconPackageExport,
  IconSend,
  IconUserCheck,
  IconX,
} from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import type { RequisitionStatus } from './types'

const STYLE: Record<RequisitionStatus, { color: MantineColor; icon: typeof IconCheck }> = {
  draft: { color: 'gray', icon: IconCircleDashed },
  submitted: { color: 'cobalt', icon: IconSend },
  org_approval: { color: 'violet', icon: IconUserCheck },
  central_review: { color: 'cobalt', icon: IconEye },
  info_requested: { color: 'yellow', icon: IconHelpCircle },
  approved: { color: 'teal', icon: IconCheck },
  rejected: { color: 'red', icon: IconX },
  ready_for_allocation: { color: 'teal', icon: IconClock },
  partially_fulfilled: { color: 'orange', icon: IconPackageExport },
  fulfilled: { color: 'green', icon: IconPackage },
  received: { color: 'green', icon: IconCircleCheck },
  closed: { color: 'gray', icon: IconLock },
}

export function RequisitionStatusBadge({ status }: { status: RequisitionStatus }) {
  const { t } = useTranslation('requisitions')
  const { color, icon: Icon } = STYLE[status]
  return (
    <Badge
      color={color}
      variant="light"
      radius="sm"
      tt="none"
      fw={500}
      leftSection={<Icon size={12} aria-hidden />}
    >
      {t(`status.${status}`)}
    </Badge>
  )
}
