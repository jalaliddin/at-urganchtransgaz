import { Anchor, Card, Group, Stack, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCan } from '@/hooks/usePermissions'
import { ProblemStatusBadge } from './ProblemBadges'
import { useTicketProblems } from './queries'

/** Problems behind a ticket (design R3). */
export function TicketProblemsPanel({ ticketId }: { ticketId: number }) {
  const { t } = useTranslation('problems')
  const canView = useCan('problems.view')
  const query = useTicketProblems(ticketId, canView)
  const rows = query.data ?? []
  if (!canView || rows.length === 0) return null

  return (
    <Card withBorder>
      <Title order={5} mb="xs">
        {t('ticketPanel')}
      </Title>
      <Stack gap={6}>
        {rows.map((p) => (
          <Group key={p.id} justify="space-between" wrap="nowrap" gap="xs">
            <Anchor component={Link} to={`/problems/${p.id}`} size="sm" truncate>
              {p.number}: {p.title}
            </Anchor>
            <ProblemStatusBadge status={p.status} />
          </Group>
        ))}
      </Stack>
    </Card>
  )
}
