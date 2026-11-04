import type { RouteObject } from 'react-router'
import { ChangePasswordPage } from '@/features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { RequirePasswordChanged } from '@/features/auth/RequirePasswordChanged'
import { RequirePermission } from '@/features/auth/RequirePermission'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { DepartmentsPage } from '@/features/departments/DepartmentsPage'
import { EmployeeDetailPage } from '@/features/employees/EmployeeDetailPage'
import { EmployeesPage } from '@/features/employees/EmployeesPage'
import { NewTicketPage } from '@/features/helpdesk/NewTicketPage'
import { TicketsPage } from '@/features/helpdesk/TicketsPage'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { OrganizationDetailPage } from '@/features/organizations/OrganizationDetailPage'
import { OrganizationsPage } from '@/features/organizations/OrganizationsPage'
import { RoleEditorPage } from '@/features/roles/RoleEditorPage'
import { RolesPage } from '@/features/roles/RolesPage'
import { UserDetailPage } from '@/features/users/UserDetailPage'
import { UsersPage } from '@/features/users/UsersPage'
import { AuditLogPage } from '@/features/audit/AuditLogPage'
import { NotificationsPage } from '@/features/notifications/NotificationsPage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { SettingsPage } from '@/features/settings/SettingsPage'
import { AppLayout } from '@/layouts/AppLayout/AppLayout'

export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },
  {
    element: <RequireAuth />,
    children: [
      { path: '/change-password', element: <ChangePasswordPage /> },
      {
        element: <RequirePasswordChanged />,
        children: [
          {
            element: <AppLayout />,
            handle: { crumb: 'nav:header.home' },
            children: [
              { index: true, element: <DashboardPage /> },
              {
                path: 'helpdesk',
                element: <RequirePermission permission={['helpdesk.view', 'helpdesk.create']} />,
                handle: { crumb: 'nav:items.helpdesk' },
                children: [
                  { index: true, element: <TicketsPage /> },
                  {
                    path: 'new',
                    element: <RequirePermission permission="helpdesk.create" />,
                    handle: { crumb: 'helpdesk:new.title' },
                    children: [{ index: true, element: <NewTicketPage /> }],
                  },
                ],
              },
              {
                path: 'organizations',
                element: <RequirePermission permission="organizations.view" />,
                handle: { crumb: 'nav:items.organizations' },
                children: [
                  { index: true, element: <OrganizationsPage /> },
                  {
                    path: ':id',
                    element: <OrganizationDetailPage />,
                    handle: { crumb: 'organizations:crumb' },
                  },
                ],
              },
              {
                path: 'departments',
                element: <RequirePermission permission="departments.view" />,
                handle: { crumb: 'nav:items.departments' },
                children: [{ index: true, element: <DepartmentsPage /> }],
              },
              {
                path: 'employees',
                element: <RequirePermission permission="employees.view" />,
                handle: { crumb: 'nav:items.employees' },
                children: [
                  { index: true, element: <EmployeesPage /> },
                  { path: ':id', element: <EmployeeDetailPage />, handle: { crumb: 'employees:crumb' } },
                ],
              },
              {
                path: 'users',
                element: <RequirePermission permission="users.view" />,
                handle: { crumb: 'nav:items.users' },
                children: [
                  { index: true, element: <UsersPage /> },
                  { path: ':id', element: <UserDetailPage />, handle: { crumb: 'users:crumb' } },
                ],
              },
              {
                path: 'roles',
                element: <RequirePermission permission="roles.view" />,
                handle: { crumb: 'nav:items.roles' },
                children: [
                  { index: true, element: <RolesPage /> },
                  { path: 'new', element: <RoleEditorPage />, handle: { crumb: 'roles:form.createTitle' } },
                  { path: ':id', element: <RoleEditorPage />, handle: { crumb: 'roles:crumb' } },
                ],
              },
              {
                path: 'audit',
                element: <RequirePermission permission="audit.view" />,
                handle: { crumb: 'nav:items.audit' },
                children: [{ index: true, element: <AuditLogPage /> }],
              },
              {
                path: 'settings',
                element: <RequirePermission permission="settings.view" />,
                handle: { crumb: 'nav:items.settings' },
                children: [{ index: true, element: <SettingsPage /> }],
              },
              { path: 'profile', element: <ProfilePage />, handle: { crumb: 'nav:items.profile' } },
              {
                path: 'notifications',
                element: <NotificationsPage />,
                handle: { crumb: 'nav:items.notifications' },
              },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
]
