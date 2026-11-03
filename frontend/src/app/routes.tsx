import type { RouteObject } from 'react-router'
import { ChangePasswordPage } from '@/features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { RequirePasswordChanged } from '@/features/auth/RequirePasswordChanged'
import { RequirePermission } from '@/features/auth/RequirePermission'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
import { OrganizationDetailPage } from '@/features/organizations/OrganizationDetailPage'
import { OrganizationsPage } from '@/features/organizations/OrganizationsPage'
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
                path: 'organizations',
                element: <RequirePermission permission="organizations.view" />,
                handle: { crumb: 'nav:items.organizations' },
                children: [
                  { index: true, element: <OrganizationsPage /> },
                  { path: ':id', element: <OrganizationDetailPage />, handle: { crumb: 'organizations:crumb' } },
                ],
              },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
]
