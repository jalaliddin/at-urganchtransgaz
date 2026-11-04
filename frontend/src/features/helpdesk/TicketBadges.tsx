import { Badge, Group, Text, Tooltip, type MantineColor } from '@mantine/core'
import { IconAlertTriangle, IconClockPause, IconCircleCheck, IconClock, IconFlame } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { formatDateTime } from '@/lib/format'
import { slaState, type SlaState } from './sla'
import type { TicketListItem, TicketPriority, TicketStatus } from './types'

const STATUS_COLORS: Record<TicketStatus, MantineColor> = {
  new: 'turquoise',
  in_review: 'cobalt',
  assigned: 'cobalt',
  in_progress: 'indigo',
  waiting_user: 'yellow',
  resolved: 'green',
  closed: 'gray',
  rejected: 'red',
}

const PRIORITY_COLORS: Record<TicketPriority, MantineColor> = {
  low: 'gray',
  normal: 'cobalt',
  high: 'orange',
  critical: 'red',
}

export function TicketStatusBadge({ status }: { status: TicketStatus }) {
  const { t } = useTranslation('helpdesk')
  return (
    <Badge color={STATUS_COLORS[status]} variant="light" radius="sm" tt="none" fw={500}>
      {t(`status.${status}`)}
    </Badge>
  )
}

export function PriorityBadge({ priority }: { priority: TicketPriority }) {
  const { t } = useTranslation('helpdesk')
  return (
    <Badge
      color={PRIORITY_COLORS[priority]}
      variant={priority === 'critical' ? 'filled' : 'outline'}
      radius="sm"
      tt="none"
      fw={500}
      leftSection={priority === 'critical' ? <IconFlame size={12} aria-hidden /> : undefined}
    >
      {t(`priority.${priority}`)}
    </Badge>
  )
}

const SLA_LOOK: Record<SlaState, { color: MantineColor; icon: typeof IconClock }> = {
  ok: { color: 'green', icon: IconClock },
  soon: { color: 'orange', icon: IconClock },
  breached: { color: 'red', icon: IconAlertTriangle },
  paused: { color: 'yellow', icon: IconClockPause },
  done: { color: 'gray', icon: IconCircleCheck },
}

/** Colour, icon and label together, so the SLA state never relies on colour alone. */
export function SlaBadge({ ticket, showDue = false }: { ticket: TicketListItem; showDue?: boolean }) {
  const { t } = useTranslation('helpdesk')
  const state = slaState(ticket)
  const { color, icon: Icon } = SLA_LOOK[state]
  const due = formatDateTime(ticket.resolution_due_at)
  return (
    <Tooltip label={`${t('fields.resolutionDue')}: ${due}`} withArrow>
      <Group gap={6} wrap="nowrap">
        <Badge
          color={color}
          variant="light"
          radius="sm"
          tt="none"
          fw={500}
          leftSection={<Icon size={12} aria-hidden />}
        >
          {t(`sla.${state}`)}
        </Badge>
        {showDue && state !== 'done' && (
          <Text size="xs" c="dimmed" ff="monospace">
            {due}
          </Text>
        )}
      </Group>
    </Tooltip>
  )
}
