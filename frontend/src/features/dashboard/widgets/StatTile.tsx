import { Anchor, Paper, Stack, Text } from '@mantine/core'
import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { formatNumber } from '@/lib/format'

interface Props {
  label: string
  value: number
  detail?: ReactNode
  href?: string
}

/** A single operational number: label, value, one line of context. */
export function StatTile({ label, value, detail, href }: Props) {
  return (
    <Paper withBorder p="md">
      <Stack gap={4}>
        {href ? (
          <Anchor component={Link} to={href} size="sm" c="dimmed" fw={500}>
            {label}
          </Anchor>
        ) : (
          <Text size="sm" c="dimmed" fw={500}>
            {label}
          </Text>
        )}
        <Text fz={30} fw={600} lh={1.15} style={{ fontVariantNumeric: 'tabular-nums' }}>
          {formatNumber(value)}
        </Text>
        {detail && (
          <Text size="sm" c="dimmed">
            {detail}
          </Text>
        )}
      </Stack>
    </Paper>
  )
}
