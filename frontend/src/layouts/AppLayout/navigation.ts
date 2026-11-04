import {
  IconBuildingCommunity,
  IconClipboardList,
  IconFileDownload,
  IconFiles,
  IconLifebuoy,
  IconHierarchy2,
  IconLayoutDashboard,
  IconSettings,
  IconShieldLock,
  IconUserCircle,
  IconUsers,
  type Icon,
} from '@tabler/icons-react'
import type { Me } from '@/features/auth/types'
import { hasPermission } from '@/hooks/usePermissions'

export interface NavItem {
  key: string
  /** i18n key in the "nav" namespace */
  labelKey: string
  path: string
  icon: Icon
  /** Required permission; with a list, any one of them is enough. */
  permission?: string | string[]
}

export interface NavGroup {
  key: string
  labelKey: string
  items: NavItem[]
}

/** Modules by phase. Later phases add their groups/items here. */
export const navigation: NavGroup[] = [
  {
    key: 'main',
    labelKey: 'groups.main',
    items: [
      {
        key: 'dashboard',
        labelKey: 'items.dashboard',
        path: '/',
        icon: IconLayoutDashboard,
        permission: 'dashboard.view',
      },
    ],
  },
  {
    key: 'helpdesk',
    labelKey: 'groups.helpdesk',
    items: [
      {
        key: 'helpdesk',
        labelKey: 'items.helpdesk',
        path: '/helpdesk',
        icon: IconLifebuoy,
        permission: ['helpdesk.view', 'helpdesk.create'],
      },
      {
        key: 'documents',
        labelKey: 'items.documents',
        path: '/documents',
        icon: IconFiles,
        permission: 'documents.view',
      },
      {
        key: 'exports',
        labelKey: 'items.exports',
        path: '/exports',
        icon: IconFileDownload,
        permission: ['helpdesk.export'],
      },
    ],
  },
  {
    key: 'structure',
    labelKey: 'groups.structure',
    items: [
      {
        key: 'organizations',
        labelKey: 'items.organizations',
        path: '/organizations',
        icon: IconBuildingCommunity,
        permission: 'organizations.view',
      },
      {
        key: 'departments',
        labelKey: 'items.departments',
        path: '/departments',
        icon: IconHierarchy2,
        permission: 'departments.view',
      },
      {
        key: 'employees',
        labelKey: 'items.employees',
        path: '/employees',
        icon: IconUsers,
        permission: 'employees.view',
      },
    ],
  },
  {
    key: 'administration',
    labelKey: 'groups.administration',
    items: [
      {
        key: 'users',
        labelKey: 'items.users',
        path: '/users',
        icon: IconUserCircle,
        permission: 'users.view',
      },
      {
        key: 'roles',
        labelKey: 'items.roles',
        path: '/roles',
        icon: IconShieldLock,
        permission: 'roles.view',
      },
      {
        key: 'audit',
        labelKey: 'items.audit',
        path: '/audit',
        icon: IconClipboardList,
        permission: 'audit.view',
      },
      {
        key: 'settings',
        labelKey: 'items.settings',
        path: '/settings',
        icon: IconSettings,
        permission: 'settings.view',
      },
    ],
  },
]

/** Groups and items the user may open; empty groups are dropped. */
export function visibleNav(me: Me): NavGroup[] {
  return navigation
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.permission || hasPermission(me, item.permission, 'any')),
    }))
    .filter((group) => group.items.length > 0)
}

export function isActivePath(itemPath: string, pathname: string): boolean {
  return itemPath === '/' ? pathname === '/' : pathname === itemPath || pathname.startsWith(`${itemPath}/`)
}
