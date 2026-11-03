import type { RouteObject } from 'react-router'
import { ChangePasswordPage } from '@/features/auth/ChangePasswordPage'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { RequirePasswordChanged } from '@/features/auth/RequirePasswordChanged'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { NotFoundPage } from '@/features/errors/NotFoundPage'
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
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
]
