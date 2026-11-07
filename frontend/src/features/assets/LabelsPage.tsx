import { Alert, Button, Group, Paper, SimpleGrid, Stack, Text, Title } from '@mantine/core'
import { IconArrowLeft, IconPrinter } from '@tabler/icons-react'
import QRCode from 'qrcode'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { listAssets } from './api'
import type { Asset } from './types'

/** The QR encodes the lookup URL, so any phone camera opens the asset card. */
export function lookupUrl(inventoryNumber: string): string {
  return `${window.location.origin}/assets/lookup/${encodeURIComponent(inventoryNumber)}`
}

function Label({ asset }: { asset: Asset }) {
  const [svg, setSvg] = useState<string>('')
  useEffect(() => {
    void QRCode.toString(lookupUrl(asset.inventory_number), {
      type: 'svg',
      margin: 0,
      errorCorrectionLevel: 'M',
    }).then(setSvg)
  }, [asset.inventory_number])

  return (
    <Paper withBorder p="xs" radius="sm" style={{ breakInside: 'avoid' }} data-testid="asset-label">
      <Group gap="xs" wrap="nowrap" align="flex-start">
        <div
          style={{ width: 84, height: 84, flex: 'none' }}
          aria-label={asset.inventory_number}
          role="img"
          dangerouslySetInnerHTML={{ __html: svg }}
        />
        <Stack gap={2} miw={0}>
          <Text fw={700} ff="monospace" size="sm">
            {asset.inventory_number}
          </Text>
          <Text size="xs" lineClamp={2}>
            {asset.name}
          </Text>
          <Text size="xs" c="dimmed" lineClamp={1}>
            {asset.organization?.name}
          </Text>
          {asset.serial_number && (
            <Text size="xs" c="dimmed" ff="monospace">
              S/N {asset.serial_number}
            </Text>
          )}
        </Stack>
      </Group>
    </Paper>
  )
}

/** Print page (outside the app shell): a grid of QR labels for the selected assets. */
export function LabelsPage() {
  const { t } = useTranslation('assets')
  const [params] = useSearchParams()
  const ids = (params.get('ids') ?? '')
    .split(',')
    .filter((v) => /^\d+$/.test(v))
    .slice(0, 500)
  // The API returns at most 100 per page: fetch the selection in chunks of 100.
  const list = useQuery({
    queryKey: ['assets', 'labels', ids.join(',')],
    queryFn: async () => {
      const chunks: string[][] = []
      for (let i = 0; i < ids.length; i += 100) chunks.push(ids.slice(i, i + 100))
      const pages = await Promise.all(
        chunks.map((chunk) => listAssets({ 'filter[id]': chunk.join(','), per_page: 100 })),
      )
      return pages.flatMap((p) => p.data)
    },
    enabled: ids.length > 0,
  })

  return (
    <Stack p="md" gap="md" maw={1100} mx="auto">
      <Group justify="space-between" className="no-print">
        <Stack gap={0}>
          <Title order={1} fz="h3">
            {t('labels.title')}
          </Title>
          <Text size="sm" c="dimmed">
            {t('labels.description')}
          </Text>
        </Stack>
        <Group>
          <Button component={Link} to="/assets" variant="default" leftSection={<IconArrowLeft size={16} />}>
            {t('title')}
          </Button>
          <Button
            leftSection={<IconPrinter size={16} />}
            onClick={() => window.print()}
            disabled={!list.data?.length}
          >
            {t('labels.print')}
          </Button>
        </Group>
      </Group>
      {ids.length === 0 && <Alert color="cobalt">{t('labels.empty')}</Alert>}
      <SimpleGrid cols={{ base: 1, xs: 2, md: 3 }} spacing="xs">
        {list.data?.map((asset) => (
          <Label key={asset.id} asset={asset} />
        ))}
      </SimpleGrid>
    </Stack>
  )
}
