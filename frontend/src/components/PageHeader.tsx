import { Group, Stack, Text, Title } from '@mantine/core'
import type { ReactNode } from 'react'

interface Props {
  title: ReactNode
  description?: ReactNode
  actions?: ReactNode
  /** Small line above the title (e.g. status badges); only when it carries information. */
  meta?: ReactNode
}

export function PageHeader({ title, description, actions, meta }: Props) {
  return (
    <Group justify="space-between" align="flex-start" gap="md" mb="lg" wrap="wrap">
      <Stack gap={4} style={{ minWidth: 0, flex: '1 1 320px' }}>
        {meta}
        <Title order={1}>{title}</Title>
        {description && (
          <Text c="dimmed" size="sm">
            {description}
          </Text>
        )}
      </Stack>
      {actions && <Group gap="sm">{actions}</Group>}
    </Group>
  )
}
