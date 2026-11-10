import { Button, Card, Group, Text, Title } from '@mantine/core'
import { IconBook } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCan } from '@/hooks/usePermissions'

/** On a resolved or closed ticket: turn its solution into a knowledge base article (design B5). */
export function TicketKnowledgePanel({ ticketId, status }: { ticketId: number; status: string }) {
  const { t } = useTranslation('knowledge')
  const canWrite = useCan(['kb.write', 'kb.manage'], 'any')
  if (!canWrite || (status !== 'resolved' && status !== 'closed')) return null

  return (
    <Card withBorder>
      <Group justify="space-between" wrap="nowrap" gap="sm">
        <div>
          <Title order={5}>{t('fromTicketPanel.title')}</Title>
          <Text size="xs" c="dimmed">
            {t('fromTicketPanel.hint')}
          </Text>
        </div>
        <Button
          size="xs"
          variant="light"
          component={Link}
          to={`/kb/new?ticket=${ticketId}`}
          leftSection={<IconBook size={14} />}
        >
          {t('fromTicketPanel.action')}
        </Button>
      </Group>
    </Card>
  )
}
