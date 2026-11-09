import { Badge, Group } from '@mantine/core'
import { IconFlag } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import type { Label, Priority } from './types'

const PRIORITY_COLORS: Record<Priority, string> = {
  low: 'gray',
  normal: 'blue',
  high: 'orange',
  urgent: 'red',
}

export function PriorityBadge({ priority }: { priority: Priority }) {
  const { t } = useTranslation('tasks')
  if (priority === 'normal') return null
  return (
    <Badge size="sm" variant="light" color={PRIORITY_COLORS[priority]} leftSection={<IconFlag size={10} />}>
      {t(`priority.${priority}`)}
    </Badge>
  )
}

export function OverdueBadge() {
  const { t } = useTranslation('tasks')
  return (
    <Badge size="sm" color="red" variant="filled">
      {t('overdue')}
    </Badge>
  )
}

export function LabelChips({ labels }: { labels: Label[] }) {
  if (labels.length === 0) return null
  return (
    <Group gap={4}>
      {labels.map((l) => (
        <Badge key={l.id} size="xs" variant="dot" color={l.color}>
          {l.name}
        </Badge>
      ))}
    </Group>
  )
}
