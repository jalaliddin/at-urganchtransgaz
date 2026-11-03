import { useEffect, useState } from 'react'
import { createBrowserRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { configureApiHandlers } from '@/api/client'
import { meQueryKey } from '@/features/auth/queries'
import { resetClientSession } from '@/features/auth/session'
import { AppProviders } from './providers'
import { createQueryClient } from './queryClient'
import { routes } from './routes'

export function App() {
  const [queryClient] = useState(createQueryClient)
  const [router] = useState(() => createBrowserRouter(routes))

  useEffect(() => {
    configureApiHandlers({
      // A session that existed has ended (expired, revoked, deactivated): drop its data;
      // RequireAuth then sends the visitor to /login. Anonymous 401s change nothing.
      onUnauthenticated: () => {
        if (queryClient.getQueryData(meQueryKey)) resetClientSession(queryClient)
      },
      onPasswordChangeRequired: () => {
        void queryClient.invalidateQueries({ queryKey: meQueryKey })
        void router.navigate('/change-password')
      },
    })
    return () => configureApiHandlers({})
  }, [queryClient, router])

  return (
    <AppProviders queryClient={queryClient}>
      <RouterProvider router={router} />
    </AppProviders>
  )
}
