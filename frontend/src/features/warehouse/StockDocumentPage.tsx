import { Anchor, Button, Card, FileButton, Group, SimpleGrid, Stack, Table, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconArrowBackUp, IconDownload, IconPaperclip, IconPrinter } from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { useMe } from '@/features/auth/queries'
import { hasPermission } from '@/hooks/usePermissions'
import { formatDate, formatDateTime, formatMoney } from '@/lib/format'
import { attachDocumentFile } from './api'
import { useDocument, useWarehouseMutation } from './queries'
import { StockDocumentDrawer } from './StockDocumentDrawer'
import { DocumentTypeBadge, qty } from './WarehouseBadges'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <Text size="xs" c="dimmed">
        {label}
      </Text>
      <Text size="sm" component="div">
        {children || '—'}
      </Text>
    </div>
  )
}

export function StockDocumentPage() {
  const { t } = useTranslation(['warehouse', 'common'])
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const { data: me } = useMe()
  const query = useDocument(id)
  const upload = useWarehouseMutation((file: File) => attachDocumentFile(id, file))
  const [returning, setReturning] = useState(false)

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const doc = query.data
  if (!doc) return null
  const canReturn = doc.type === 'issue' && me !== undefined && hasPermission(me, 'warehouse.receive')

  return (
    <>
      <PageHeader
        title={doc.number}
        description={`${t(`types.${doc.type}`)} · ${formatDate(doc.document_date)}`}
        meta={<DocumentTypeBadge type={doc.type} />}
        actions={
          <Group gap="sm" className="no-print">
            <Button variant="default" leftSection={<IconPrinter size={16} />} onClick={() => window.print()}>
              {t('actions.print')}
            </Button>
            {doc.can.attach_file && (
              <FileButton
                onChange={(file) =>
                  file &&
                  upload.mutate(file, {
                    onSuccess: () =>
                      notifications.show({ color: 'green', message: t('documents.fileAttached') }),
                    onError: (e) => notifications.show({ color: 'red', message: toApiError(e).message }),
                  })
                }
              >
                {(props) => (
                  <Button
                    variant="default"
                    leftSection={<IconPaperclip size={16} />}
                    loading={upload.isPending}
                    {...props}
                  >
                    {doc.file ? t('actions.replaceFile') : t('actions.attachFile')}
                  </Button>
                )}
              </FileButton>
            )}
            {canReturn && (
              <Button leftSection={<IconArrowBackUp size={16} />} onClick={() => setReturning(true)}>
                {t('actions.return')}
              </Button>
            )}
          </Group>
        }
      />
      <Stack gap="md">
        <Card withBorder p="lg">
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
            <Field label={t('fields.warehouse')}>
              {doc.warehouse.name}
              {doc.organization ? ` (${doc.organization.name})` : ''}
            </Field>
            {doc.target_warehouse && (
              <Field label={t('fields.targetWarehouse')}>
                {doc.target_warehouse.name} ({doc.target_warehouse.organization.name})
              </Field>
            )}
            {doc.type === 'receipt' && <Field label={t('fields.supplier')}>{doc.supplier}</Field>}
            {doc.type === 'receipt' && (
              <Field label={t('fields.supplierDocument')}>{doc.supplier_document}</Field>
            )}
            {doc.recipient_organization && (
              <Field label={t('fields.recipientOrganization')}>{doc.recipient_organization.name}</Field>
            )}
            {doc.recipient_employee && (
              <Field label={t('fields.recipientEmployee')}>
                <Anchor component={Link} to={`/employees/${doc.recipient_employee.id}`} size="sm">
                  {doc.recipient_employee.name}
                </Anchor>
              </Field>
            )}
            {doc.asset && (
              <Field label={t('fields.device')}>
                <Anchor component={Link} to={`/assets/${doc.asset.id}`} size="sm">
                  {doc.asset.inventory_number} — {doc.asset.name}
                </Anchor>
              </Field>
            )}
            {doc.related_document && (
              <Field label={t('fields.relatedIssue')}>
                <Anchor
                  component={Link}
                  to={`/warehouse/documents/${doc.related_document.id}`}
                  size="sm"
                  ff="monospace"
                >
                  {doc.related_document.number}
                </Anchor>
              </Field>
            )}
            {doc.requisition && (
              <Field label={t('fields.requisition')}>
                <Anchor component={Link} to={`/requisitions/${doc.requisition.id}`} size="sm" ff="monospace">
                  {doc.requisition.number}
                </Anchor>
              </Field>
            )}
            {doc.stock_count_id && (
              <Field label={t('fields.count')}>
                <Anchor component={Link} to={`/warehouse/counts/${doc.stock_count_id}`} size="sm">
                  {t('counts.open')}
                </Anchor>
              </Field>
            )}
            <Field label={t('fields.reason')}>{doc.reason}</Field>
            <Field label={t('fields.amount')}>{formatMoney(doc.total_amount)}</Field>
            <Field label={t('fields.postedBy')}>
              {doc.created_by?.name} · {formatDateTime(doc.created_at)}
            </Field>
            {doc.file && (
              <Field label={t('fields.file')}>
                <Anchor href={fileUrl(doc.file.uuid)} size="sm">
                  <Group gap={4} wrap="nowrap">
                    <IconDownload size={14} aria-hidden />
                    {doc.file.name}
                  </Group>
                </Anchor>
              </Field>
            )}
          </SimpleGrid>
        </Card>

        <Card withBorder p={0}>
          <Title order={3} fz="sm" p="md" pb={0}>
            {t('lines.title')}
          </Title>
          <Table.ScrollContainer minWidth={640}>
            <Table verticalSpacing="sm">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.material')}</Table.Th>
                  <Table.Th>{t('lines.batch')}</Table.Th>
                  <Table.Th ta="right">{t('fields.quantity')}</Table.Th>
                  <Table.Th ta="right">{t('fields.unitCost')}</Table.Th>
                  <Table.Th ta="right">{t('fields.amount')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {doc.lines.map((l) => (
                  <Table.Tr key={l.id}>
                    <Table.Td>
                      <Text size="sm">{l.material.name}</Text>
                      <Text size="xs" c="dimmed" ff="monospace">
                        {l.material.code}
                      </Text>
                    </Table.Td>
                    <Table.Td ff="monospace">
                      {l.batch ?? (doc.type === 'issue' || doc.type === 'transfer' ? t('lines.fifo') : '—')}
                      {l.expires_at ? ` · ${formatDate(l.expires_at)}` : ''}
                    </Table.Td>
                    <Table.Td ta="right" ff="monospace">
                      {qty(l.quantity)} {l.material.unit}
                    </Table.Td>
                    <Table.Td ta="right">{formatMoney(l.unit_cost)}</Table.Td>
                    <Table.Td ta="right">{formatMoney(l.amount)}</Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>

        <Card withBorder p={0}>
          <Title order={3} fz="sm" p="md" pb={0}>
            {t('documents.movements')}
          </Title>
          <Table.ScrollContainer minWidth={520}>
            <Table verticalSpacing="xs">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>{t('fields.warehouse')}</Table.Th>
                  <Table.Th>{t('lines.batch')}</Table.Th>
                  <Table.Th ta="right">{t('fields.quantity')}</Table.Th>
                  <Table.Th ta="right">{t('fields.balanceAfter')}</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {doc.movements.map((m) => (
                  <Table.Tr key={m.id}>
                    <Table.Td>{m.warehouse.name}</Table.Td>
                    <Table.Td ff="monospace">{m.batch ?? '—'}</Table.Td>
                    <Table.Td ta="right" ff="monospace" c={Number(m.quantity) < 0 ? 'orange.8' : 'green.8'}>
                      {Number(m.quantity) > 0 ? '+' : ''}
                      {qty(m.quantity)}
                    </Table.Td>
                    <Table.Td ta="right" ff="monospace">
                      {qty(m.balance_after)}
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>

        {doc.returns.length > 0 && (
          <Card withBorder p="md">
            <Title order={3} fz="sm" mb="xs">
              {t('documents.returns')}
            </Title>
            <Group gap="sm">
              {doc.returns.map((r) => (
                <Anchor
                  key={r.id}
                  component={Link}
                  to={`/warehouse/documents/${r.id}`}
                  size="sm"
                  ff="monospace"
                >
                  {r.number} · {formatDate(r.document_date)}
                </Anchor>
              ))}
            </Group>
          </Card>
        )}
      </Stack>
      {returning && (
        <StockDocumentDrawer
          type="return"
          defaults={{ warehouse_id: doc.warehouse.id, related_document_id: doc.id }}
          onClose={(created) => {
            setReturning(false)
            if (created) navigate(`/warehouse/documents/${created.id}`)
          }}
        />
      )}
    </>
  )
}
