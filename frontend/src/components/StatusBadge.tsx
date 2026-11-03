import { Badge, type MantineColor } from '@mantine/core'
import { useTranslation } from 'react-i18next'

/** One colour per meaning across the whole app. */
const COLORS: Record<string, MantineColor> = {
  active: 'green',
  on_leave: 'yellow',
  business_trip: 'cobalt',
  dismissed: 'gray',
  inactive: 'red',
  must_change_password: 'orange',
}

export function StatusBadge({ status, label }: { status: string; label?: string }) {
  const { t } = useTranslation()
  return (
    <Badge color={COLORS[status] ?? 'gray'} variant="light" radius="sm" tt="none" fw={500}>
      {label ?? t(`status.${status}`, { defaultValue: status })}
    </Badge>
  )
}
