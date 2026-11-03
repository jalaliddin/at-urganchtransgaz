import { Anchor, Breadcrumbs, Text } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { Link, useMatches } from 'react-router'

export interface RouteHandle {
  /** i18n key, e.g. "nav:items.employees" */
  crumb?: string
}

/** Trail built from the `handle.crumb` of matched routes. */
export function AppBreadcrumbs() {
  const { t } = useTranslation()
  const crumbs = useMatches()
    .filter((m) => (m.handle as RouteHandle | undefined)?.crumb)
    .map((m) => ({ path: m.pathname, label: t((m.handle as RouteHandle).crumb as string) }))

  if (crumbs.length < 2) return null

  return (
    <Breadcrumbs separatorMargin={6} visibleFrom="md" fz="sm">
      {crumbs.map((crumb, index) =>
        index === crumbs.length - 1 ? (
          <Text key={crumb.path} size="sm" c="dimmed" aria-current="page">
            {crumb.label}
          </Text>
        ) : (
          <Anchor key={crumb.path} component={Link} to={crumb.path} size="sm">
            {crumb.label}
          </Anchor>
        ),
      )}
    </Breadcrumbs>
  )
}
