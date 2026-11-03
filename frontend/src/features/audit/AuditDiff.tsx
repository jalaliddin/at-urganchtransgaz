import { Table, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'

type Values = Record<string, unknown> | null

function display(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value))
    return value.every((v) => typeof v !== 'object') ? value.map(String).join(', ') : JSON.stringify(value)
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

/** Field-by-field comparison of an audit entry (keys as recorded by the backend). */
export function AuditDiff({ oldValues, newValues }: { oldValues: Values; newValues: Values }) {
  const { t } = useTranslation('audit')
  const keys = [...new Set([...Object.keys(newValues ?? {}), ...Object.keys(oldValues ?? {})])]

  if (keys.length === 0) {
    return (
      <Text size="sm" c="dimmed">
        {t('diff.empty')}
      </Text>
    )
  }

  return (
    <Table.ScrollContainer minWidth={420}>
      <Table verticalSpacing="xs" withTableBorder withColumnBorders fz="sm">
        <Table.Thead>
          <Table.Tr>
            <Table.Th w="28%">{t('diff.field')}</Table.Th>
            <Table.Th>{t('diff.old')}</Table.Th>
            <Table.Th>{t('diff.new')}</Table.Th>
          </Table.Tr>
        </Table.Thead>
        <Table.Tbody>
          {keys.map((key) => (
            <Table.Tr key={key}>
              <Table.Td>
                <Text size="sm" ff="monospace">
                  {key}
                </Text>
              </Table.Td>
              <Table.Td style={{ wordBreak: 'break-word' }}>{display(oldValues?.[key])}</Table.Td>
              <Table.Td style={{ wordBreak: 'break-word' }}>{display(newValues?.[key])}</Table.Td>
            </Table.Tr>
          ))}
        </Table.Tbody>
      </Table>
    </Table.ScrollContainer>
  )
}
