import { Anchor, Badge, Button, Card, Group, Menu, Stack, Text, Title } from '@mantine/core'
import { notifications } from '@mantine/notifications'
import {
  IconArchive,
  IconChevronDown,
  IconDownload,
  IconPencil,
  IconSend,
  IconThumbDown,
  IconThumbUp,
  IconTrash,
} from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError, toApiError } from '@/api/errors'
import { confirmAction } from '@/components/confirm'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { fileUrl } from '@/features/exports/api'
import { formatDate, formatNumber } from '@/lib/format'
import * as k from './api'
import { ArticleStatusBadge, ArticleTypeBadge } from './KbBadges'
import { MarkdownText } from './MarkdownText'
import { useArticle, useKbMutation } from './queries'

export function ArticlePage() {
  const { t } = useTranslation(['knowledge', 'common'])
  const navigate = useNavigate()
  const id = Number(useParams().id)
  const query = useArticle(id)
  const act = useKbMutation((action: 'publish' | 'archive') => k.articleAction(id, action))
  const vote = useKbMutation((helpful: boolean) => k.voteArticle(id, helpful))
  const remove = useKbMutation(() => k.deleteArticle(id))
  const fail = (e: unknown) => notifications.show({ color: 'red', message: toApiError(e).message })

  if (query.error instanceof ApiError && query.error.status === 404) return <NotFoundPage />
  if (query.error) return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  const article = query.data
  if (!article) return null

  return (
    <>
      <PageHeader
        title={article.title}
        meta={
          <Group gap="xs">
            <Text ff="monospace" size="sm" c="dimmed">
              {article.number}
            </Text>
            <ArticleTypeBadge type={article.type} />
            {article.status !== 'published' && <ArticleStatusBadge status={article.status} />}
            {article.audience === 'it' && (
              <Badge variant="outline" color="gray" tt="none">
                {t('audience.it')}
              </Badge>
            )}
          </Group>
        }
        description={article.summary ?? undefined}
        actions={
          article.can.edit && (
            <Group gap="sm">
              {article.status !== 'published' && (
                <Button
                  leftSection={<IconSend size={16} />}
                  loading={act.isPending}
                  onClick={() => act.mutate('publish', { onError: fail })}
                >
                  {t('actions.publish')}
                </Button>
              )}
              <Menu position="bottom-end">
                <Menu.Target>
                  <Button variant="default" rightSection={<IconChevronDown size={14} />}>
                    {t('common:actions.more')}
                  </Button>
                </Menu.Target>
                <Menu.Dropdown>
                  <Menu.Item
                    leftSection={<IconPencil size={16} />}
                    component={Link}
                    to={`/kb/${article.id}/edit`}
                  >
                    {t('common:actions.edit')}
                  </Menu.Item>
                  {article.status === 'published' && (
                    <Menu.Item
                      leftSection={<IconArchive size={16} />}
                      onClick={() => act.mutate('archive', { onError: fail })}
                    >
                      {t('actions.archive')}
                    </Menu.Item>
                  )}
                  {article.can.delete && (
                    <Menu.Item
                      color="red"
                      leftSection={<IconTrash size={16} />}
                      onClick={async () => {
                        if (
                          await confirmAction({
                            message: t('deleteConfirm'),
                            confirmLabel: t('common:actions.delete'),
                            danger: true,
                          })
                        )
                          remove.mutate(undefined, { onSuccess: () => navigate('/kb'), onError: fail })
                      }}
                    >
                      {t('common:actions.delete')}
                    </Menu.Item>
                  )}
                </Menu.Dropdown>
              </Menu>
            </Group>
          )
        }
      />
      <Stack maw={860}>
        <Card withBorder p="lg">
          <MarkdownText source={article.body} />
        </Card>
        {article.attachments.length > 0 && (
          <Card withBorder>
            <Title order={5} mb="xs">
              {t('files')}
            </Title>
            <Stack gap={4}>
              {article.attachments.map((a) => (
                <Anchor key={a.id} href={fileUrl(a.file.uuid)} size="sm">
                  <Group gap={6} wrap="nowrap">
                    <IconDownload size={14} />
                    {a.file.name}
                  </Group>
                </Anchor>
              ))}
            </Stack>
          </Card>
        )}
        {article.status === 'published' && (
          <Group justify="space-between" wrap="wrap" gap="sm">
            <Group gap="xs">
              <Text size="sm">{t('vote.question')}</Text>
              <Button
                size="xs"
                variant={article.my_vote === true ? 'filled' : 'default'}
                leftSection={<IconThumbUp size={14} />}
                onClick={() => vote.mutate(true, { onError: fail })}
              >
                {t('vote.yes')} ({article.helpful})
              </Button>
              <Button
                size="xs"
                variant={article.my_vote === false ? 'filled' : 'default'}
                leftSection={<IconThumbDown size={14} />}
                onClick={() => vote.mutate(false, { onError: fail })}
              >
                {t('vote.no')} ({article.not_helpful})
              </Button>
            </Group>
            <Text size="xs" c="dimmed">
              {[
                article.category?.name,
                article.author?.name,
                formatDate(article.published_at ?? article.updated_at),
                t('views', { count: article.views, formatted: formatNumber(article.views) }),
              ]
                .filter(Boolean)
                .join(', ')}
            </Text>
          </Group>
        )}
        {article.source_ticket && (
          <Text size="sm" c="dimmed">
            {t('fromTicket')}{' '}
            <Anchor component={Link} to={`/helpdesk/${article.source_ticket.id}`} size="sm">
              {article.source_ticket.number}
            </Anchor>
          </Text>
        )}
      </Stack>
    </>
  )
}
