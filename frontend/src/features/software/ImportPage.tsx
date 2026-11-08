import {
  Alert,
  Button,
  Card,
  Code,
  FileInput,
  Group,
  SegmentedControl,
  Stack,
  Table,
  Text,
  Title,
} from '@mantine/core'
import { IconFileImport } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toApiError } from '@/api/errors'
import { PageHeader } from '@/components/PageHeader'
import { importCsv } from './api'
import { useSoftwareMutation } from './queries'
import type { ImportResult } from './types'

const SAMPLES = {
  installations:
    'inventory_number;name;version;vendor;installed_at\nAT-000123;Microsoft Office 2021;16.0;Microsoft;2024-02-01',
  antivirus:
    'inventory_number;product;state;definitions_updated_at;last_scan_at\nAT-000123;Kaspersky Endpoint Security;installed;2026-11-08 09:00;2026-11-07',
}

export function ImportPage() {
  const { t } = useTranslation(['software', 'validation'])
  const [type, setType] = useState<'installations' | 'antivirus'>('installations')
  const [file, setFile] = useState<File | null>(null)
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const upload = useSoftwareMutation(() => importCsv(type, file as File))

  return (
    <>
      <PageHeader title={t('import.title')} description={t('import.description')} />
      <Card withBorder p="lg" maw={760}>
        <Stack>
          <SegmentedControl
            aria-label={t('import.type')}
            value={type}
            onChange={(v) => {
              setType(v as typeof type)
              setResult(null)
            }}
            data={[
              { value: 'installations', label: t('import.types.installations') },
              { value: 'antivirus', label: t('import.types.antivirus') },
            ]}
          />
          <Text size="sm">{t('import.format')}</Text>
          <Code block>{SAMPLES[type]}</Code>
          <FileInput
            label={t('import.file')}
            accept=".csv,text/csv,text/plain"
            value={file}
            onChange={setFile}
            error={error}
            clearable
          />
          <Group>
            <Button
              leftSection={<IconFileImport size={16} />}
              loading={upload.isPending}
              onClick={() => {
                if (!file) {
                  setError(t('validation:required'))
                  return
                }
                setError(null)
                upload.mutate(undefined, {
                  onSuccess: setResult,
                  onError: (e) => setError(toApiError(e).fieldErrors.file?.[0] ?? toApiError(e).message),
                })
              }}
            >
              {t('import.run')}
            </Button>
          </Group>
        </Stack>
      </Card>
      {result && (
        <Card withBorder p="lg" mt="md" maw={760}>
          <Title order={3} fz="sm" mb="sm">
            {t('import.result', { imported: result.imported, total: result.total })}
          </Title>
          {result.errors.length === 0 ? (
            <Alert color="green">{t('import.noErrors')}</Alert>
          ) : (
            <Table verticalSpacing="xs">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th w={80}>{t('import.line')}</Table.Th>
                  <Table.Th>{t('import.error')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {result.errors.map((e) => (
                  <Table.Tr key={e.line}>
                    <Table.Td ff="monospace">{e.line}</Table.Td>
                    <Table.Td>{e.message}</Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          )}
        </Card>
      )}
    </>
  )
}
