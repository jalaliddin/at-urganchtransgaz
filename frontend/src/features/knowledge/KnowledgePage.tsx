import { Badge, Button, Group, Select, Stack, Switch, Text, TextInput } from '@mantine/core'
import { useDebouncedCallback } from '@mantine/hooks'
import { IconPlus, IconSearch } from '@tabler/icons-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { DataTable } from '@/components/DataTable'
import { PageHeader } from '@/components/PageHeader'
import { useCategories } from '@/features/helpdesk/queries'
import { useCan } from '@/hooks/usePermissions'
import { useTableParams } from '@/hooks/useTableParams'
import { formatDate } from '@/lib/format'
import { ArticleStatusBadge, ArticleTypeBadge } from './KbBadges'
import { useArticles } from './queries'
import { ARTICLE_STATUSES, ARTICLE_TYPES, type ArticleRow } from './types'

/** Knowledge base: FAQ, guides and solutions the user may read; writers also see their drafts. */
export function KnowledgePage() {
  const { t } = useTranslation(['knowledge', 'common'])
  const navigate = useNavigate()
  const canWrite = useCan(['kb.write', 'kb.manage'], 'any')
  const params = useTableParams({
    defaultSort: 'published_at',
    filterKeys: ['type', 'category', 'status', 'mine'],
  })
  const list = useArticles(params.apiParams)
  const categories = useCategories(true)
  const [search, setSearch] = useState(params.search)
  const pushSearch = useDebouncedCallback(params.setSearch, 350)

  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          canWrite && (
            <Button component={Link} to="/kb/new" leftSection={<IconPlus size={16} />}>
              {t('new')}
            </Button>
          )
        }
      />
      <DataTable<ArticleRow>
        storeKey="kb-articles"
        params={params}
        records={list.data?.data}
        meta={list.data?.meta}
        isLoading={list.isPending}
        isFetching={list.isFetching}
        error={list.error}
        onRetry={() => void list.refetch()}
        emptyTitle={t('empty')}
        onRowClick={(a) => navigate(`/kb/${a.id}`)}
        toolbar={
          <Group gap="sm" wrap="wrap" w="100%">
            <TextInput
              leftSection={<IconSearch size={16} />}
              placeholder={t('search')}
              aria-label={t('search')}
              value={search}
              onChange={(e) => {
                setSearch(e.currentTarget.value)
                pushSearch(e.currentTarget.value)
              }}
              w={{ base: '100%', sm: 320 }}
            />
            <Select
              placeholder={t('fields.type')}
              aria-label={t('fields.type')}
              clearable
              data={ARTICLE_TYPES.map((v) => ({ value: v, label: t(`type.${v}`) }))}
              value={params.filters.type ?? null}
              onChange={(v) => params.setFilter('type', v)}
              w={{ base: '100%', sm: 190 }}
            />
            <Select
              placeholder={t('fields.category')}
              aria-label={t('fields.category')}
              clearable
              data={(categories.data ?? []).map((c) => ({ value: String(c.id), label: c.label }))}
              value={params.filters.category ?? null}
              onChange={(v) => params.setFilter('category', v)}
              w={{ base: '100%', sm: 200 }}
            />
            {canWrite && (
              <>
                <Select
                  placeholder={t('fields.status')}
                  aria-label={t('fields.status')}
                  clearable
                  data={ARTICLE_STATUSES.map((v) => ({ value: v, label: t(`status.${v}`) }))}
                  value={params.filters.status ?? null}
                  onChange={(v) => params.setFilter('status', v)}
                  w={{ base: '100%', sm: 170 }}
                />
                <Switch
                  label={t('mine')}
                  checked={params.filters.mine === '1'}
                  onChange={(e) => params.setFilter('mine', e.currentTarget.checked ? '1' : null)}
                />
              </>
            )}
          </Group>
        }
        columns={[
          {
            accessor: 'title',
            title: t('fields.article'),
            sortable: false,
            render: (a) => (
              <Stack gap={2}>
                <Group gap={6}>
                  <Text size="sm" fw={600}>
                    {a.title}
                  </Text>
                  {a.audience === 'it' && (
                    <Badge size="xs" variant="outline" color="gray" tt="none">
                      {t('audience.it')}
                    </Badge>
                  )}
                </Group>
                {a.summary && (
                  <Text size="xs" c="dimmed" lineClamp={2}>
                    {a.summary}
                  </Text>
                )}
              </Stack>
            ),
          },
          { accessor: 'type', title: t('fields.type'), render: (a) => <ArticleTypeBadge type={a.type} /> },
          { accessor: 'category', title: t('fields.category'), render: (a) => a.category?.name ?? '—' },
          ...(canWrite
            ? [
                {
                  accessor: 'status',
                  title: t('fields.status'),
                  render: (a: ArticleRow) => <ArticleStatusBadge status={a.status} />,
                },
              ]
            : []),
          {
            accessor: 'helpful',
            title: t('fields.helpful'),
            textAlign: 'right' as const,
            render: (a) =>
              a.helpful + a.not_helpful > 0 ? `${a.helpful} / ${a.helpful + a.not_helpful}` : '—',
          },
          {
            accessor: 'updated_at',
            title: t('fields.updated'),
            noWrap: true,
            render: (a) => formatDate(a.published_at ?? a.updated_at),
          },
        ]}
      />
    </>
  )
}
