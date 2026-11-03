import { ActionIcon, AppShell, Box, NavLink, ScrollArea, Tooltip } from '@mantine/core'
import { IconLayoutSidebarLeftCollapse, IconLayoutSidebarLeftExpand } from '@tabler/icons-react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router'
import { useCurrentUser } from '@/features/auth/queries'
import { useUiStore } from '@/stores/ui'
import classes from './AppLayout.module.css'
import { isActivePath, visibleNav } from './navigation'

interface Props {
  /** Icon-only rail (desktop). Never collapsed inside the mobile drawer. */
  collapsed: boolean
  onNavigate: () => void
}

export function Sidebar({ collapsed, onNavigate }: Props) {
  const { t } = useTranslation('nav')
  const me = useCurrentUser()
  const { pathname } = useLocation()
  const toggleSidebar = useUiStore((s) => s.toggleSidebar)

  return (
    <>
      <AppShell.Section grow component={ScrollArea} px="sm" py="xs">
        <nav aria-label={t('header.home')}>
          {visibleNav(me).map((group) => (
            <Box key={group.key}>
              {!collapsed && <div className={classes.navGroupLabel}>{t(group.labelKey)}</div>}
              {group.items.map((item) => {
                const Icon = item.icon
                const label = t(item.labelKey)
                const active = isActivePath(item.path, pathname)
                const link = (
                  <NavLink
                    key={item.key}
                    component={Link}
                    to={item.path}
                    onClick={onNavigate}
                    active={active}
                    aria-current={active ? 'page' : undefined}
                    aria-label={collapsed ? label : undefined}
                    className={classes.navLink}
                    label={collapsed ? null : label}
                    leftSection={<Icon size={20} stroke={1.6} />}
                    variant="light"
                    my={2}
                  />
                )
                return collapsed ? (
                  <Tooltip key={item.key} label={label} position="right">
                    <div className={classes.collapsedItem}>{link}</div>
                  </Tooltip>
                ) : (
                  link
                )
              })}
            </Box>
          ))}
        </nav>
      </AppShell.Section>
      <AppShell.Section p="sm" visibleFrom="sm">
        <Tooltip label={collapsed ? t('header.expand') : t('header.collapse')} position="right">
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            onClick={toggleSidebar}
            aria-label={collapsed ? t('header.expand') : t('header.collapse')}
          >
            {collapsed ? <IconLayoutSidebarLeftExpand size={20} /> : <IconLayoutSidebarLeftCollapse size={20} />}
          </ActionIcon>
        </Tooltip>
      </AppShell.Section>
    </>
  )
}
