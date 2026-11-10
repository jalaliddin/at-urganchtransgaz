import { Anchor, Card, Stack, Text } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconBulb } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { useCan } from '@/hooks/usePermissions'
import { useSuggestions } from './queries'

/** Articles that may answer the question before a ticket is sent (design B4). Opens them in a new tab to keep the form. */
export function KbSuggestions({ title, categoryId }: { title: string; categoryId: number | null }) {
  const { t } = useTranslation('knowledge')
  const canRead = useCan('kb.view')
  const [debounced] = useDebouncedValue(title.trim(), 500)
  const query = useSuggestions(debounced, categoryId, canRead && debounced.length >= 3)
  const rows = query.data ?? []
  if (!canRead || rows.length === 0) return null

  return (
    <Card withBorder bg="var(--mantine-color-cobalt-0)" p="sm">
      <Stack gap={6}>
        <Text size="sm" fw={600}>
          <IconBulb size={16} style={{ verticalAlign: 'text-bottom', marginRight: 6 }} />
          {t('suggest.title')}
        </Text>
        {rows.map((a) => (
          <Stack key={a.id} gap={0}>
            <Anchor href={`/kb/${a.id}`} target="_blank" rel="noopener" size="sm">
              {a.title}
            </Anchor>
            {a.summary && (
              <Text size="xs" c="dimmed" lineClamp={1}>
                {a.summary}
              </Text>
            )}
          </Stack>
        ))}
      </Stack>
    </Card>
  )
}
