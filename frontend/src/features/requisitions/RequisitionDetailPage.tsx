import {
  Anchor,
  Button,
  Card,
  FileButton,
  Grid,
  Group,
  SimpleGrid,
  Stack,
  Table,
  Text,
  Textarea,
  Timeline,
  Title,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import {
  IconCheck,
  IconDownload,
  IconEdit,
  IconLock,
  IconPackageExport,
  IconPaperclip,
  IconSend,
  IconTrash,
  IconUserCheck,
} from '@tabler/icons-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { formatDate, formatDateTime } from '@/lib/format'
import { qty } from '@/features/warehouse/WarehouseBadges'
import * as r from './api'
import { RequisitionStatusBadge } from './RequisitionBadges'
import { AllocateDialog, CommentDialog, DecisionDialog } from './RequisitionDialogs'
import { RequisitionFormDrawer } from './RequisitionFormDrawer'
import { useRequisition, useRequisitionMutation } from './queries'
import type { RequisitionItem } from './types'
import { formatPhone } from '@/features/sim/types'

type Dialog = 'edit' | 'org' | 'central' | 'request_info' | 'provide_info' | 'complete' | 'receive' | null

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

export function RequisitionDetailPage() {
  const { t } = useTranslation(['requisitions', 'common'])
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const query = useRequisition(id)
  const [dialog, setDialog] = useState<Dialog>(null)
  const [allocating, setAllocating] = useState<RequisitionItem | null>(null)
  const [comment, setComment] = useState('')
  const step = useRequisitionMutation((fn: () => Promise<unknown>) => fn())
  const remove = useRequisitionMutation(() => r.deleteRequisition(id))
  const sendComment = useRequisitionMutation(() => r.addComment(id, comment))
  const upload = useRequisitionMutation((files: File[]) => r.uploadAttachments(id, files))

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const req = query.data
  if (!req) return null
  const can = req.can
  const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })
  const run = (fn: () => Promise<unknown>) => step.mutate(fn, { onError: fail })
  const fulfilledFor = (itemId: number) => req.fulfillments.filter((f) => f.item_id === itemId)

  return (
    <>
      <PageHeader
        title={req.title}
        description={`${req.number} · ${req.organization.name}`}
        meta={<RequisitionStatusBadge status={req.status} />}
        actions={
          <Group gap="sm">
            {can.edit && (
              <Button
                variant="default"
                leftSection={<IconEdit size={16} />}
                onClick={() => setDialog('edit')}
              >
                {t('actions.edit')}
              </Button>
            )}
            {can.delete && (
              <Button
                variant="subtle"
                color="red"
                leftSection={<IconTrash size={16} />}
                onClick={async () => {
                  if (
                    await confirmAction({
                      message: t('actions.deleteConfirm', { number: req.number }),
                      confirmLabel: t('common:actions.delete'),
                      danger: true,
                    })
                  )
                    remove.mutate(undefined, { onSuccess: () => navigate('/requisitions'), onError: fail })
                }}
              >
                {t('common:actions.delete')}
              </Button>
            )}
            {can.submit && (
              <Button
                leftSection={<IconSend size={16} />}
                loading={step.isPending}
                onClick={() => run(() => r.submitRequisition(id))}
              >
                {t('actions.submit')}
              </Button>
            )}
            {can.org_decide && (
              <Button leftSection={<IconUserCheck size={16} />} onClick={() => setDialog('org')}>
                {t('actions.orgDecide')}
              </Button>
            )}
            {can.take && (
              <Button
                variant="default"
                loading={step.isPending}
                onClick={() => run(() => r.takeRequisition(id))}
              >
                {t('actions.take')}
              </Button>
            )}
            {can.request_info && (
              <Button variant="default" onClick={() => setDialog('request_info')}>
                {t('actions.requestInfo')}
              </Button>
            )}
            {can.provide_info && (
              <Button onClick={() => setDialog('provide_info')}>{t('actions.provideInfo')}</Button>
            )}
            {can.decide && (
              <Button leftSection={<IconCheck size={16} />} onClick={() => setDialog('central')}>
                {t('actions.decide')}
              </Button>
            )}
            {can.ready && (
              <Button variant="default" loading={step.isPending} onClick={() => run(() => r.markReady(id))}>
                {t('actions.ready')}
              </Button>
            )}
            {can.complete && (
              <Button variant="default" onClick={() => setDialog('complete')}>
                {t('actions.complete')}
              </Button>
            )}
            {can.receive && (
              <Button leftSection={<IconCheck size={16} />} onClick={() => setDialog('receive')}>
                {t('actions.receive')}
              </Button>
            )}
            {can.close && (
              <Button
                variant="default"
                leftSection={<IconLock size={16} />}
                loading={step.isPending}
                onClick={() => run(() => r.closeRequisition(id))}
              >
                {t('actions.close')}
              </Button>
            )}
          </Group>
        }
      />
      <Grid gap="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <Stack gap="md">
            <Card withBorder p="lg">
              <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="lg" verticalSpacing="md">
                <Field label={t('fields.requester')}>{req.requester?.name}</Field>
                <Field label={t('fields.recipient')}>{req.recipient_employee?.name}</Field>
                <Field label={t('fields.neededBy')}>{req.needed_by ? formatDate(req.needed_by) : null}</Field>
                <Field label={t('fields.submittedAt')}>
                  {req.submitted_at ? formatDateTime(req.submitted_at) : null}
                </Field>
                <Field label={t('fields.reviewer')}>{req.reviewer?.name}</Field>
                <Field label={t('fields.approvedBy')}>
                  {req.approved_by ? `${req.approved_by.name} · ${formatDateTime(req.approved_at)}` : null}
                </Field>
                {req.rejection_reason && (
                  <Field label={t('fields.rejectionReason')}>{req.rejection_reason}</Field>
                )}
                {req.received_at && (
                  <Field label={t('fields.receivedAt')}>{formatDateTime(req.received_at)}</Field>
                )}
              </SimpleGrid>
              {req.description && (
                <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>
                  {req.description}
                </Text>
              )}
            </Card>

            <Card withBorder p={0}>
              <Title order={3} fz="sm" p="md" pb={0}>
                {t('items.title')}
              </Title>
              <Table.ScrollContainer minWidth={680}>
                <Table verticalSpacing="sm">
                  <Table.Thead>
                    <Table.Tr>
                      <Table.Th>{t('items.name')}</Table.Th>
                      <Table.Th ta="right">{t('items.requested')}</Table.Th>
                      <Table.Th ta="right">{t('items.approved')}</Table.Th>
                      <Table.Th ta="right">{t('items.fulfilled')}</Table.Th>
                      <Table.Th>{t('items.allocations')}</Table.Th>
                      {can.allocate && <Table.Th />}
                    </Table.Tr>
                  </Table.Thead>
                  <Table.Tbody>
                    {req.items.map((item) => (
                      <Table.Tr key={item.id}>
                        <Table.Td>
                          <Text size="sm">{item.name}</Text>
                          <Text size="xs" c="dimmed">
                            {t(`kinds.${item.kind}`)}
                            {item.material ? ` · ${item.material.code}` : ''}
                            {item.asset_category ? ` · ${item.asset_category.name}` : ''}
                          </Text>
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {qty(item.quantity)} {item.unit?.name ?? ''}
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {qty(item.approved_quantity)}
                        </Table.Td>
                        <Table.Td ta="right" ff="monospace">
                          {qty(item.fulfilled_quantity)}
                        </Table.Td>
                        <Table.Td>
                          <Stack gap={2}>
                            {fulfilledFor(item.id).map((f) => (
                              <Text key={f.id} size="xs">
                                {f.stock_document ? (
                                  <Anchor
                                    component={Link}
                                    to={`/warehouse/documents/${f.stock_document.id}`}
                                    ff="monospace"
                                  >
                                    {f.stock_document.number}
                                  </Anchor>
                                ) : f.asset ? (
                                  <Anchor component={Link} to={`/assets/${f.asset.id}`}>
                                    {f.asset.inventory_number}
                                  </Anchor>
                                ) : f.sim_card ? (
                                  <Anchor component={Link} to={`/sim/${f.sim_card.id}`}>
                                    {formatPhone(f.sim_card.phone_number)}
                                  </Anchor>
                                ) : (
                                  f.note
                                )}
                                {f.asset_transfer ? (
                                  <>
                                    {' · '}
                                    <Anchor
                                      component={Link}
                                      to={`/assets/transfers/${f.asset_transfer.id}`}
                                      ff="monospace"
                                    >
                                      {f.asset_transfer.number}
                                    </Anchor>
                                  </>
                                ) : null}
                                {` · ${qty(f.quantity)}`}
                              </Text>
                            ))}
                          </Stack>
                        </Table.Td>
                        {can.allocate && (
                          <Table.Td>
                            {Number(item.remaining) > 0 && (
                              <Button
                                size="compact-sm"
                                variant="light"
                                leftSection={<IconPackageExport size={14} />}
                                onClick={() => setAllocating(item)}
                              >
                                {t('actions.allocate')}
                              </Button>
                            )}
                          </Table.Td>
                        )}
                      </Table.Tr>
                    ))}
                  </Table.Tbody>
                </Table>
              </Table.ScrollContainer>
            </Card>

            <Card withBorder p="md">
              <Title order={3} fz="sm" mb="sm">
                {t('comments.title')}
              </Title>
              <Stack gap="sm">
                {req.comments.length === 0 && (
                  <Text size="sm" c="dimmed">
                    {t('comments.empty')}
                  </Text>
                )}
                {req.comments.map((c) => (
                  <div key={c.id}>
                    <Text size="xs" c="dimmed">
                      {c.user?.name} · {formatDateTime(c.created_at)}
                    </Text>
                    <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>
                      {c.body}
                    </Text>
                  </div>
                ))}
                {can.comment && (
                  <Group align="flex-end" wrap="nowrap">
                    <Textarea
                      aria-label={t('comments.add')}
                      placeholder={t('comments.placeholder')}
                      autosize
                      minRows={1}
                      value={comment}
                      onChange={(e) => setComment(e.currentTarget.value)}
                      style={{ flex: 1 }}
                    />
                    <Button
                      disabled={!comment.trim()}
                      loading={sendComment.isPending}
                      onClick={() =>
                        sendComment.mutate(undefined, { onSuccess: () => setComment(''), onError: fail })
                      }
                    >
                      {t('comments.send')}
                    </Button>
                  </Group>
                )}
              </Stack>
            </Card>
          </Stack>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Stack gap="md">
            <Card withBorder p="md">
              <Group justify="space-between" mb="xs">
                <Title order={3} fz="sm">
                  {t('files.title')}
                </Title>
                {can.comment && (
                  <FileButton
                    multiple
                    onChange={(files) => files.length > 0 && upload.mutate(files, { onError: fail })}
                  >
                    {(props) => (
                      <Button
                        size="compact-sm"
                        variant="subtle"
                        leftSection={<IconPaperclip size={14} />}
                        loading={upload.isPending}
                        {...props}
                      >
                        {t('files.add')}
                      </Button>
                    )}
                  </FileButton>
                )}
              </Group>
              {req.attachments.length === 0 && (
                <Text size="sm" c="dimmed">
                  {t('files.empty')}
                </Text>
              )}
              {req.attachments.map((a) => (
                <Anchor key={a.id} href={fileUrl(a.file.uuid)} size="sm" display="block">
                  <Group gap={4} wrap="nowrap">
                    <IconDownload size={14} aria-hidden />
                    {a.file.name}
                  </Group>
                </Anchor>
              ))}
            </Card>
            <Card withBorder p="md">
              <Title order={3} fz="sm" mb="sm">
                {t('history.title')}
              </Title>
              <Timeline bulletSize={14} lineWidth={2} active={req.history.length}>
                {req.history.map((h) => (
                  <Timeline.Item
                    key={h.id}
                    title={
                      <Text size="sm" fw={500}>
                        {t(`history.actions.${h.action}`, { defaultValue: h.action })}
                      </Text>
                    }
                  >
                    <Text size="xs" c="dimmed">
                      {h.user?.name ?? '—'} · {formatDateTime(h.created_at)}
                    </Text>
                    {h.to_status && h.from_status !== h.to_status && (
                      <Text size="xs">{t('history.movedTo', { status: t(`status.${h.to_status}`) })}</Text>
                    )}
                    {h.comment && (
                      <Text size="xs" style={{ whiteSpace: 'pre-wrap' }}>
                        «{h.comment}»
                      </Text>
                    )}
                  </Timeline.Item>
                ))}
              </Timeline>
            </Card>
          </Stack>
        </Grid.Col>
      </Grid>

      {dialog === 'edit' && <RequisitionFormDrawer requisition={req} onClose={() => setDialog(null)} />}
      {(dialog === 'org' || dialog === 'central') && (
        <DecisionDialog requisition={req} stage={dialog} onClose={() => setDialog(null)} />
      )}
      {dialog === 'request_info' && (
        <CommentDialog
          title={t('actions.requestInfo')}
          label={t('dialogs.question')}
          confirmLabel={t('actions.requestInfo')}
          required
          run={(c) => r.requestInfo(id, c)}
          onClose={() => setDialog(null)}
        />
      )}
      {dialog === 'provide_info' && (
        <CommentDialog
          title={t('actions.provideInfo')}
          label={t('dialogs.answer')}
          confirmLabel={t('actions.provideInfo')}
          required
          run={(c) => r.provideInfo(id, c)}
          onClose={() => setDialog(null)}
        />
      )}
      {dialog === 'complete' && (
        <CommentDialog
          title={t('actions.complete')}
          label={t('dialogs.completeReason')}
          confirmLabel={t('actions.complete')}
          required
          run={(c) => r.completeAllocation(id, c)}
          onClose={() => setDialog(null)}
        />
      )}
      {dialog === 'receive' && (
        <CommentDialog
          title={t('actions.receive')}
          label={t('dialogs.receiveNote')}
          confirmLabel={t('actions.receive')}
          required={false}
          run={(c) => r.confirmReceipt(id, c || undefined)}
          onClose={() => setDialog(null)}
        />
      )}
      {allocating && (
        <AllocateDialog requisition={req} item={allocating} onClose={() => setAllocating(null)} />
      )}
    </>
  )
}
