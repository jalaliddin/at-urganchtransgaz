import {
  Alert,
  Badge,
  Button,
  Card,
  Group,
  Progress,
  SegmentedControl,
  SimpleGrid,
  Stack,
  Table,
  Text,
  TextInput,
  Title,
  type MantineColor,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconCamera, IconCameraOff, IconScan } from '@tabler/icons-react'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { useCan } from '@/hooks/usePermissions'
import { formatDateTime } from '@/lib/format'
import { closeSession, scanCode } from './api'
import { AssetStatusBadge } from './AssetBadges'
import { useAssetMutation, useSession, useSessionItems } from './queries'
import type { ScanResult } from './types'

/** Minimal typing for the Shape Detection API (Chrome/Android, not in TS lib.dom). */
interface DetectedBarcode {
  rawValue: string
}
interface BarcodeDetectorLike {
  detect(source: CanvasImageSource): Promise<DetectedBarcode[]>
}
type BarcodeDetectorCtor = new (options?: { formats?: string[] }) => BarcodeDetectorLike

function barcodeDetector(): BarcodeDetectorCtor | null {
  const ctor = (globalThis as unknown as { BarcodeDetector?: BarcodeDetectorCtor }).BarcodeDetector
  return ctor ?? null
}

const RESULT_COLORS: Record<ScanResult, MantineColor> = {
  found: 'green',
  already_found: 'gray',
  unexpected: 'orange',
  already_scanned: 'gray',
}

/** Camera scanning where the browser supports BarcodeDetector; calls onCode once per distinct value. */
function CameraScanner({ onCode, onStop }: { onCode: (code: string) => void; onStop: () => void }) {
  const video = useRef<HTMLVideoElement>(null)
  const last = useRef<string>('')

  useEffect(() => {
    const Detector = barcodeDetector()
    if (!Detector) return
    const detector = new Detector({ formats: ['qr_code', 'code_128', 'ean_13'] })
    let stream: MediaStream | null = null
    let timer = 0
    let stopped = false
    void navigator.mediaDevices
      .getUserMedia({ video: { facingMode: 'environment' } })
      .then((s) => {
        if (stopped) return s.getTracks().forEach((track) => track.stop())
        stream = s
        if (video.current) {
          video.current.srcObject = s
          void video.current.play()
        }
        timer = window.setInterval(() => {
          if (!video.current || video.current.readyState < 2) return
          void detector.detect(video.current).then((codes) => {
            const value = codes[0]?.rawValue
            if (value && value !== last.current) {
              last.current = value
              onCode(value)
            }
          })
        }, 400)
      })
      .catch(() => onStop())
    return () => {
      stopped = true
      window.clearInterval(timer)
      stream?.getTracks().forEach((track) => track.stop())
    }
  }, [onCode, onStop])

  return (
    <video
      ref={video}
      muted
      playsInline
      style={{ width: '100%', maxHeight: 280, borderRadius: 8, background: '#000' }}
    />
  )
}

export function InventorySessionPage() {
  const { t } = useTranslation(['assets', 'common'])
  const id = Number(useParams().id)
  const session = useSession(id)
  const canScan = useCan('assets.inventory')
  const [state, setState] = useState<'found' | 'missing' | 'unexpected'>('missing')
  const items = useSessionItems(id, { 'filter[state]': state, per_page: 50 })
  const [code, setCode] = useState('')
  const [feedback, setFeedback] = useState<{ result: ScanResult; number: string; name?: string } | null>(null)
  const [camera, setCamera] = useState(false)
  const input = useRef<HTMLInputElement>(null)
  const scan = useAssetMutation((value: string) => scanCode(id, value))
  const close = useAssetMutation(() => closeSession(id))
  const cameraSupported =
    barcodeDetector() !== null && typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices)

  const submit = (value: string) => {
    if (value.trim() === '') return
    scan.mutate(value, {
      onSuccess: (r) => {
        setFeedback({ result: r.result, number: r.item.inventory_number, name: r.item.asset?.name })
        setCode('')
        input.current?.focus()
      },
      onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
    })
  }

  if (session.error instanceof ApiError && session.error.status === 404) return <NotFoundPage />
  if (session.error) return <ErrorState error={session.error} onRetry={() => void session.refetch()} />
  const s = session.data
  if (!s) return null
  const open = s.status === 'open'

  return (
    <>
      <PageHeader
        title={s.name}
        description={s.organization.name}
        meta={
          <Badge variant="light" tt="none" color={open ? 'orange' : 'gray'}>
            {t(`inventory.status.${s.status}`)}
          </Badge>
        }
        actions={
          open &&
          canScan && (
            <Button
              variant="default"
              onClick={async () => {
                const ok = await confirmAction({
                  title: t('inventory.close'),
                  message: t('inventory.closeConfirm'),
                  confirmLabel: t('inventory.close'),
                })
                if (ok)
                  close.mutate(undefined, {
                    onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                  })
              }}
            >
              {t('inventory.close')}
            </Button>
          )
        }
      />
      <Stack gap="md">
        <SimpleGrid cols={{ base: 2, sm: 4 }}>
          {(['expected', 'found', 'missing', 'unexpected'] as const).map((k) => (
            <Card key={k} withBorder p="sm">
              <Text size="xs" c="dimmed">
                {t(`inventory.${k}`)}
              </Text>
              <Text fz={24} fw={600} style={{ fontVariantNumeric: 'tabular-nums' }}>
                {s[`${k}_count`]}
              </Text>
            </Card>
          ))}
        </SimpleGrid>
        <Progress
          value={s.expected_count ? (s.found_count / s.expected_count) * 100 : 0}
          aria-label={t('inventory.found')}
        />

        {open && canScan && (
          <Card withBorder p="md">
            <Title order={2} fz="sm" mb="sm">
              {t('inventory.scan')}
            </Title>
            <form
              onSubmit={(e) => {
                e.preventDefault()
                submit(code)
              }}
            >
              <Group gap="sm" align="flex-end" wrap="nowrap">
                <TextInput
                  ref={input}
                  aria-label={t('inventory.scan')}
                  placeholder={t('inventory.scanPlaceholder')}
                  value={code}
                  onChange={(e) => setCode(e.currentTarget.value)}
                  leftSection={<IconScan size={18} />}
                  size="md"
                  autoComplete="off"
                  style={{ flex: 1 }}
                  styles={{ input: { fontFamily: 'var(--mantine-font-family-monospace)' } }}
                />
                <Button type="submit" size="md" loading={scan.isPending}>
                  {t('inventory.scan')}
                </Button>
              </Group>
            </form>
            {feedback && (
              <Alert mt="sm" color={RESULT_COLORS[feedback.result]} role="status">
                {t(`inventory.result.${feedback.result}`, { number: feedback.number })}
                {feedback.name ? ` — ${feedback.name}` : ''}
              </Alert>
            )}
            <Group mt="sm">
              {cameraSupported ? (
                <Button
                  variant="light"
                  leftSection={camera ? <IconCameraOff size={16} /> : <IconCamera size={16} />}
                  onClick={() => setCamera((c) => !c)}
                >
                  {camera ? t('inventory.cameraStop') : t('inventory.camera')}
                </Button>
              ) : (
                <Text size="xs" c="dimmed">
                  {t('inventory.cameraUnsupported')}
                </Text>
              )}
            </Group>
            {camera && <CameraScanner onCode={submit} onStop={() => setCamera(false)} />}
          </Card>
        )}

        <Card withBorder p={0}>
          <Group p="md" pb="xs">
            <SegmentedControl
              aria-label={t('fields.status')}
              value={state}
              onChange={(v) => setState(v as typeof state)}
              data={(['missing', 'found', 'unexpected'] as const).map((k) => ({
                value: k,
                label: t(`inventory.state.${k}`),
              }))}
            />
          </Group>
          <Table.ScrollContainer minWidth={520}>
            <Table verticalSpacing="xs">
              <Table.Tbody>
                {items.data?.data.map((i) => (
                  <Table.Tr key={i.id}>
                    <Table.Td ff="monospace">{i.inventory_number}</Table.Td>
                    <Table.Td>
                      {i.asset ? (
                        <Stack gap={0}>
                          <Text size="sm">{i.asset.name}</Text>
                          <Text size="xs" c="dimmed">
                            {[i.asset.category, i.asset.location, i.asset.room].filter(Boolean).join(' · ')}
                          </Text>
                        </Stack>
                      ) : (
                        '—'
                      )}
                    </Table.Td>
                    <Table.Td>{i.asset && <AssetStatusBadge status={i.asset.status} />}</Table.Td>
                    <Table.Td>
                      <Text size="xs" c="dimmed">
                        {i.found_at ? `${i.scanned_by ?? ''} · ${formatDateTime(i.found_at)}` : ''}
                      </Text>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
      </Stack>
    </>
  )
}
