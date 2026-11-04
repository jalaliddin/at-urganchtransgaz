import { Text, Timeline } from '@mantine/core'
import { IconPoint } from '@tabler/icons-react'
import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { formatDateTime } from '@/lib/format'
import { useTicketHistory } from './queries'
import type { TicketHistoryEntry } from './types'

function describe(entry: TicketHistoryEntry, t: TFunction): string {
  const n = entry.new_values ?? {}
  const o = entry.old_values ?? {}
  const status = (value: unknown) => (typeof value === 'string' ? t(`helpdesk:status.${value}`) : '—')
  const list = (value: unknown) => (Array.isArray(value) ? value.join(', ') : '')
  switch (entry.action) {
    case 'status_changed':
      return t('helpdesk:history.status_changed', { from: status(o.status), to: status(n.status) })
    case 'assigned':
      return t('helpdesk:history.assigned', { name: String(n.assignee ?? '—') })
    case 'attachment_added':
      return t('helpdesk:history.attachment_added', { files: list(n.files) })
    case 'attachment_removed':
      return t('helpdesk:history.attachment_removed', { files: list(o.files) })
    case 'time_logged':
      return t('helpdesk:history.time_logged', { minutes: Number(n.minutes ?? 0) })
    case 'rated':
      return t('helpdesk:history.rated', { rating: Number(n.rating ?? 0) })
    case 'linked':
      return t('helpdesk:history.linked', { ticket: String(n.ticket ?? '') })
    case 'unlinked':
      return t('helpdesk:history.unlinked', { ticket: String(o.ticket ?? '') })
    case 'sla_breached':
      return t('helpdesk:history.sla_breached', { kind: t(`helpdesk:history.kind.${String(n.kind)}`) })
    default:
      return t(`helpdesk:history.${entry.action}`, { defaultValue: entry.action })
  }
}

export function TicketHistory({ ticketId }: { ticketId: number }) {
  const { t } = useTranslation('helpdesk')
  const history = useTicketHistory(ticketId)

  return (
    <Timeline bulletSize={18} lineWidth={2} active={(history.data?.length ?? 0) - 1}>
      {history.data?.map((entry) => (
        <Timeline.Item key={entry.id} bullet={<IconPoint size={12} />} title={describe(entry, t)}>
          {entry.note && (
            <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
              {entry.note}
            </Text>
          )}
          <Text size="xs" c="dimmed" mt={2}>
            {entry.user?.name ?? t('history.system')} · {formatDateTime(entry.created_at)}
          </Text>
        </Timeline.Item>
      ))}
    </Timeline>
  )
}
