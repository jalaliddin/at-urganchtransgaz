import { Anchor, Group, Paper, Stack, Text, Title } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { formatDateTime } from '@/lib/format'
import type { AuditEntry } from '../api'

export function RecentAudit({ entries }: { entries: AuditEntry[] }) {
  const { t } = useTranslation(['dashboard', 'common'])

  return (
    <Paper withBorder p="md">
      <Group justify="space-between" mb="md">
        <Title order={2} size="h4">
          {t('dashboard:audit.title')}
        </Title>
        <Anchor component={Link} to="/audit" size="sm">
          {t('dashboard:audit.all')}
        </Anchor>
      </Group>
      {entries.length === 0 ? (
        <Text size="sm" c="dimmed">
          {t('dashboard:audit.empty')}
        </Text>
      ) : (
        <Stack gap="xs">
          {entries.map((entry) => (
            <Group key={entry.id} justify="space-between" wrap="nowrap" gap="sm" align="flex-start">
              <div style={{ minWidth: 0 }}>
                <Text size="sm" fw={500} truncate>
                  {entry.event_label}
                  {entry.auditable_type
                    ? ` · ${t(`common:entities.${entry.auditable_type}`, { defaultValue: entry.auditable_type })} #${entry.auditable_id}`
                    : ''}
                </Text>
                <Text size="xs" c="dimmed" truncate>
                  {entry.user?.username ?? entry.user_label ?? t('audit.system')}
                  {entry.organization ? `, ${entry.organization.name}` : ''}
                </Text>
              </div>
              <Text size="xs" c="dimmed" style={{ whiteSpace: 'nowrap' }}>
                {formatDateTime(entry.created_at)}
              </Text>
            </Group>
          ))}
        </Stack>
      )}
    </Paper>
  )
}
