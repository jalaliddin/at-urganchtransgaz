import { ActionIcon, Button, Kbd, Text } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { Spotlight, spotlight } from '@mantine/spotlight'
import { IconBuildingCommunity, IconSearch, IconUser, IconUsers } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/queries'
import { globalSearch } from '@/features/search/api'
import classes from './AppLayout.module.css'
import { visibleNav } from './navigation'

/** Ctrl/⌘+K: jump to pages, organizations, employees and users (scoped by the API). */
export function GlobalSearch() {
  const { t } = useTranslation('nav')
  const navigate = useNavigate()
  const me = useCurrentUser()
  const [query, setQuery] = useState('')
  const [debounced] = useDebouncedValue(query.trim(), 300)

  const results = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => globalSearch(debounced),
    enabled: debounced.length >= 2,
    staleTime: 30_000,
  })

  const go = (path: string) => {
    navigate(path)
    spotlight.close()
  }

  const pages = visibleNav(me)
    .flatMap((group) => group.items)
    .filter((item) => !query.trim() || t(item.labelKey).toLowerCase().includes(query.trim().toLowerCase()))

  const data = debounced.length >= 2 ? results.data : undefined
  const hasResults =
    pages.length > 0 || Boolean(data?.organizations?.length || data?.employees?.length || data?.users?.length)

  return (
    <>
      <Button
        variant="default"
        leftSection={<IconSearch size={16} />}
        rightSection={<Kbd size="xs">Ctrl K</Kbd>}
        onClick={spotlight.open}
        visibleFrom="sm"
        c="dimmed"
        fw={400}
        w={260}
        classNames={{ inner: classes.searchButton, label: classes.searchLabel }}
      >
        {t('header.search')}
      </Button>
      <ActionIcon
        variant="subtle"
        color="gray"
        size="lg"
        onClick={spotlight.open}
        hiddenFrom="sm"
        aria-label={t('header.search')}
      >
        <IconSearch size={20} />
      </ActionIcon>

      <Spotlight.Root
        query={query}
        onQueryChange={setQuery}
        shortcut={['mod + K']}
        scrollable
        maxHeight={480}
      >
        <Spotlight.Search
          placeholder={t('header.searchPlaceholder')}
          leftSection={<IconSearch size={18} />}
        />
        <Spotlight.ActionsList>
          {pages.length > 0 && (
            <Spotlight.ActionsGroup label={t('header.pages')}>
              {pages.map((item) => {
                const Icon = item.icon
                return (
                  <Spotlight.Action
                    key={item.key}
                    onClick={() => go(item.path)}
                    leftSection={<Icon size={18} />}
                    label={t(item.labelKey)}
                  />
                )
              })}
            </Spotlight.ActionsGroup>
          )}
          {data?.organizations?.length ? (
            <Spotlight.ActionsGroup label={t('search.organizations')}>
              {data.organizations.map((o) => (
                <Spotlight.Action
                  key={`o${o.id}`}
                  onClick={() => go(`/organizations/${o.id}`)}
                  leftSection={<IconBuildingCommunity size={18} />}
                  label={o.name}
                  description={o.code ?? undefined}
                />
              ))}
            </Spotlight.ActionsGroup>
          ) : null}
          {data?.employees?.length ? (
            <Spotlight.ActionsGroup label={t('search.employees')}>
              {data.employees.map((e) => (
                <Spotlight.Action
                  key={`e${e.id}`}
                  onClick={() => go(`/employees/${e.id}`)}
                  leftSection={<IconUsers size={18} />}
                  label={e.full_name}
                  description={[e.position, e.organization?.name].filter(Boolean).join(', ')}
                />
              ))}
            </Spotlight.ActionsGroup>
          ) : null}
          {data?.users?.length ? (
            <Spotlight.ActionsGroup label={t('search.users')}>
              {data.users.map((u) => (
                <Spotlight.Action
                  key={`u${u.id}`}
                  onClick={() => go(`/users/${u.id}`)}
                  leftSection={<IconUser size={18} />}
                  label={u.name}
                  description={u.username}
                />
              ))}
            </Spotlight.ActionsGroup>
          ) : null}
          {!hasResults && (
            <Spotlight.Empty>
              {query.trim().length < 2 ? t('header.searchHint') : t('header.nothingFound')}
            </Spotlight.Empty>
          )}
        </Spotlight.ActionsList>
        {query.trim().length > 0 && query.trim().length < 2 && (
          <Text size="xs" c="dimmed" px="md" pb="sm">
            {t('header.searchHint')}
          </Text>
        )}
      </Spotlight.Root>
    </>
  )
}
