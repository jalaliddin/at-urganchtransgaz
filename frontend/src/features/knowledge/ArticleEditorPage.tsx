import {
  ActionIcon,
  Button,
  Card,
  FileInput,
  Group,
  SegmentedControl,
  Select,
  Stack,
  Tabs,
  Text,
  Textarea,
  TextInput,
} from '@mantine/core'
import { notifications } from '@mantine/notifications'
import { IconPaperclip, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { toApiError } from '@/api/errors'
import { ErrorState } from '@/components/ErrorState'
import { PageHeader } from '@/components/PageHeader'
import { getTicket } from '@/features/helpdesk/api'
import { useCategories } from '@/features/helpdesk/queries'
import * as k from './api'
import { MarkdownText } from './MarkdownText'
import { useArticle, useKbMutation } from './queries'
import {
  ARTICLE_AUDIENCES,
  ARTICLE_TYPES,
  type Article,
  type ArticleAudience,
  type ArticleType,
} from './types'

interface Initial {
  title: string
  type: ArticleType
  audience: ArticleAudience
  category: string | null
  summary: string
  body: string
  sourceTicketId: number | null
}

/** New article (optionally from a resolved ticket: ?ticket=ID) or edit of an existing one. */
export function ArticleEditorPage() {
  const { t } = useTranslation('knowledge')
  const id = Number(useParams().id ?? 0)
  const [params] = useSearchParams()
  const ticketId = Number(params.get('ticket') ?? 0)
  const article = useArticle(id, id > 0)
  const ticket = useQuery({
    queryKey: ['kb', 'ticket', ticketId],
    queryFn: () => getTicket(ticketId),
    enabled: id === 0 && ticketId > 0,
  })

  const error = article.error ?? ticket.error
  if (error)
    return <ErrorState error={error} onRetry={() => void (id ? article.refetch() : ticket.refetch())} />
  if ((id > 0 && !article.data) || (id === 0 && ticketId > 0 && !ticket.data)) return null

  const a = article.data
  const tk = ticket.data
  const initial: Initial = a
    ? {
        title: a.title,
        type: a.type,
        audience: a.audience,
        category: a.category ? String(a.category.id) : null,
        summary: a.summary ?? '',
        body: a.body,
        sourceTicketId: a.source_ticket?.id ?? null,
      }
    : {
        title: tk?.title ?? '',
        type: tk ? 'solution' : 'guide',
        audience: 'all',
        category: tk?.category ? String(tk.category.id) : null,
        summary: '',
        body: tk ? `${tk.description}\n\n## ${t('editor.resolutionHeading')}\n${tk.resolution ?? ''}` : '',
        sourceTicketId: tk?.id ?? null,
      }

  return (
    <>
      <PageHeader
        title={a ? t('editor.editTitle') : t('editor.newTitle')}
        description={a ? a.title : t('editor.description')}
      />
      <ArticleForm initial={initial} article={a} />
    </>
  )
}

function ArticleForm({ initial, article }: { initial: Initial; article?: Article }) {
  const { t } = useTranslation(['knowledge', 'common', 'validation'])
  const navigate = useNavigate()
  const categories = useCategories(true)
  const [title, setTitle] = useState(initial.title)
  const [type, setType] = useState<ArticleType>(initial.type)
  const [audience, setAudience] = useState<ArticleAudience>(initial.audience)
  const [category, setCategory] = useState<string | null>(initial.category)
  const [summary, setSummary] = useState(initial.summary)
  const [body, setBody] = useState(initial.body)
  const [files, setFiles] = useState<File[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const values = (publish: boolean) => ({
    title: title.trim(),
    type,
    audience,
    category_id: category ? Number(category) : null,
    summary: summary.trim() || null,
    body,
    ...(article ? {} : { source_ticket_id: initial.sourceTicketId, publish }),
  })
  const save = useKbMutation(async (publish: boolean) => {
    const saved = await k.saveArticle(values(publish), article?.id)
    return files.length > 0 ? k.uploadArticleFiles(saved.id, files) : saved
  })
  const removeFile = useKbMutation((attachmentId: number) =>
    k.removeArticleFile(article?.id ?? 0, attachmentId),
  )

  const submit = (publish: boolean) => {
    const local: Record<string, string> = {}
    if (!title.trim()) local.title = t('validation:required')
    if (!body.trim()) local.body = t('validation:required')
    setErrors(local)
    if (Object.keys(local).length > 0) return
    save.mutate(publish, {
      onSuccess: (saved) => {
        notifications.show({ color: 'green', message: t('common:saved') })
        navigate(`/kb/${saved.id}`)
      },
      onError: (e) => {
        const error = toApiError(e)
        const fields = Object.fromEntries(
          Object.entries(error.fieldErrors).map(([key, v]) => [
            key.startsWith('files') ? 'files' : key,
            v[0] ?? '',
          ]),
        )
        if (Object.keys(fields).length > 0) setErrors(fields)
        else notifications.show({ color: 'red', message: error.message })
      },
    })
  }

  return (
    <Stack maw={900}>
      <Card withBorder>
        <Stack>
          <TextInput
            label={t('fields.title')}
            value={title}
            onChange={(e) => setTitle(e.currentTarget.value)}
            error={errors.title}
            required
            maxLength={255}
            data-autofocus
          />
          <Group grow align="flex-start">
            <Select
              label={t('fields.type')}
              data={ARTICLE_TYPES.map((v) => ({ value: v, label: t(`type.${v}`) }))}
              value={type}
              onChange={(v) => v && setType(v as ArticleType)}
              allowDeselect={false}
            />
            <Select
              label={t('fields.category')}
              clearable
              data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
              value={category}
              onChange={setCategory}
            />
          </Group>
          <Stack gap={4}>
            <Text size="sm" fw={500}>
              {t('fields.audience')}
            </Text>
            <SegmentedControl
              aria-label={t('fields.audience')}
              value={audience}
              onChange={(v) => setAudience(v as ArticleAudience)}
              data={ARTICLE_AUDIENCES.map((v) => ({ value: v, label: t(`audience.${v}`) }))}
            />
          </Stack>
          <Textarea
            label={t('fields.summary')}
            value={summary}
            onChange={(e) => setSummary(e.currentTarget.value)}
            autosize
            minRows={2}
            maxLength={500}
          />
          <Tabs defaultValue="write">
            <Tabs.List>
              <Tabs.Tab value="write">{t('editor.write')}</Tabs.Tab>
              <Tabs.Tab value="preview">{t('editor.preview')}</Tabs.Tab>
            </Tabs.List>
            <Tabs.Panel value="write" pt="xs">
              <Textarea
                aria-label={t('fields.body')}
                description={t('editor.markdownHint')}
                value={body}
                onChange={(e) => setBody(e.currentTarget.value)}
                error={errors.body}
                autosize
                minRows={12}
                styles={{ input: { fontFamily: 'var(--mantine-font-family-monospace)' } }}
              />
            </Tabs.Panel>
            <Tabs.Panel value="preview" pt="xs">
              <Card withBorder>
                {body.trim() ? <MarkdownText source={body} /> : <Text c="dimmed">{t('editor.empty')}</Text>}
              </Card>
            </Tabs.Panel>
          </Tabs>
          {article && article.attachments.length > 0 && (
            <Stack gap={4}>
              <Text size="sm" fw={500}>
                {t('files')}
              </Text>
              {article.attachments.map((a) => (
                <Group key={a.id} gap="xs">
                  <Text size="sm">{a.file.name}</Text>
                  <ActionIcon
                    variant="subtle"
                    color="red"
                    size="sm"
                    aria-label={t('common:actions.delete')}
                    onClick={() => removeFile.mutate(a.id)}
                  >
                    <IconTrash size={14} />
                  </ActionIcon>
                </Group>
              ))}
            </Stack>
          )}
          <FileInput
            label={t('editor.attach')}
            multiple
            clearable
            leftSection={<IconPaperclip size={16} />}
            value={files}
            onChange={setFiles}
            error={errors.files}
          />
        </Stack>
      </Card>
      <Group justify="flex-end">
        <Button variant="default" onClick={() => navigate(article ? `/kb/${article.id}` : '/kb')}>
          {t('common:actions.cancel')}
        </Button>
        {article ? (
          <Button onClick={() => submit(false)} loading={save.isPending}>
            {t('common:actions.save')}
          </Button>
        ) : (
          <>
            <Button variant="default" onClick={() => submit(false)} loading={save.isPending}>
              {t('editor.saveDraft')}
            </Button>
            <Button onClick={() => submit(true)} loading={save.isPending}>
              {t('actions.publish')}
            </Button>
          </>
        )}
      </Group>
    </Stack>
  )
}
