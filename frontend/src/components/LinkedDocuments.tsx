import { Anchor, Stack, Text } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCan } from '@/hooks/usePermissions'

export interface LinkedDocument {
  id: number
  type: string
  number: string | null
  title: string
}

/** Documents linked to an object (contract, SIM card …); nothing without documents.view. */
export function LinkedDocuments({
  queryKey,
  fetch,
}: {
  queryKey: readonly unknown[]
  fetch: () => Promise<LinkedDocument[]>
}) {
  const { t } = useTranslation(['documents', 'common'])
  const canDocs = useCan('documents.view')
  const rows = useQuery({ queryKey, queryFn: fetch, enabled: canDocs })
  if (!canDocs || rows.data?.length === 0) return <Text c="dimmed">{t('documents:noLinked')}</Text>
  return (
    <Stack gap={6}>
      {rows.data?.map((d) => (
        <Anchor key={d.id} component={Link} to={`/documents/${d.id}`} size="sm">
          {t(`documents:types.${d.type}`)} {d.number ? `№ ${d.number}` : ''} — {d.title}
        </Anchor>
      ))}
    </Stack>
  )
}
