import { AppShell, Burger, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useTranslation } from 'react-i18next'
import { Link, Outlet } from 'react-router'
import { useUiStore } from '@/stores/ui'
import { AppBreadcrumbs } from './AppBreadcrumbs'
import classes from './AppLayout.module.css'
import { GlobalSearch } from './GlobalSearch'
import { NotificationsMenu } from './NotificationsMenu'
import { OrgContextSwitcher } from './OrgContextSwitcher'
import { Sidebar } from './Sidebar'
import { UserMenu } from './UserMenu'

function BrandMark() {
  return (
    <svg width="28" height="28" viewBox="0 0 64 64" aria-hidden="true">
      <rect width="64" height="64" rx="14" fill="#1D4E89" />
      <g fill="none" stroke="#7FD3DB" strokeWidth="4" strokeLinejoin="round">
        <rect x="19" y="19" width="26" height="26" />
        <rect x="19" y="19" width="26" height="26" transform="rotate(45 32 32)" />
      </g>
    </svg>
  )
}

/** Signed-in shell: collapsible sidebar (drawer on mobile) and a header with search and context. */
export function AppLayout() {
  const { t } = useTranslation(['nav', 'common'])
  const [mobileOpened, { toggle: toggleMobile, close: closeMobile }] = useDisclosure(false)
  const collapsed = useUiStore((s) => s.sidebarCollapsed)

  return (
    <AppShell
      header={{ height: 60 }}
      navbar={{ width: collapsed ? 76 : 256, breakpoint: 'sm', collapsed: { mobile: !mobileOpened } }}
      padding="lg"
    >
      <AppShell.Header px="md">
        <Group h="100%" justify="space-between" wrap="nowrap" gap="md">
          <Group gap="md" wrap="nowrap" style={{ minWidth: 0 }}>
            <Burger
              opened={mobileOpened}
              onClick={toggleMobile}
              hiddenFrom="sm"
              size="sm"
              aria-label={t('nav:header.toggleNavigation')}
            />
            <Link to="/" className={classes.mark} aria-label={t('common:app.name')}>
              <BrandMark />
              <span>{t('common:app.name')}</span>
            </Link>
            <AppBreadcrumbs />
          </Group>
          <Group gap="sm" wrap="nowrap">
            <GlobalSearch />
            <OrgContextSwitcher />
            <NotificationsMenu />
            <UserMenu />
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar>
        <Sidebar collapsed={collapsed && !mobileOpened} onNavigate={closeMobile} />
      </AppShell.Navbar>

      <AppShell.Main className={classes.main}>
        <Outlet />
      </AppShell.Main>
    </AppShell>
  )
}
