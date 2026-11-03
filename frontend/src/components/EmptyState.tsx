import { Stack, Text, ThemeIcon } from '@mantine/core'
import { IconInbox } from '@tabler/icons-react'
import type { ReactNode } from 'react'

interface Props {
  title: string
  description?: ReactNode
  action?: ReactNode
  icon?: ReactNode
}

/** An empty list is an invitation to act: say what is missing and offer the next step. */
export function EmptyState({ title, description, action, icon }: Props) {
  return (
    <Stack align="center" gap="xs" py={48} px="md" ta="center">
      <ThemeIcon size={44} radius="xl" variant="light" color="gray">
        {icon ?? <IconInbox size={24} stroke={1.5} />}
      </ThemeIcon>
      <Text fw={600}>{title}</Text>
      {description && (
        <Text size="sm" c="dimmed" maw={420}>
          {description}
        </Text>
      )}
      {action}
    </Stack>
  )
}
