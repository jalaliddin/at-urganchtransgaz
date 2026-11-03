import { useEffect, useState } from 'react'
import { createBrowserRouter } from 'react-router'
import { RouterProvider } from 'react-router/dom'
import { configureApiHandlers } from '@/api/client'
import { meQueryKey } from '@/features/auth/queries'
import { AppProviders } from './providers'
import { createQueryClient } from './queryClient'
import { routes } from './routes'

export function App() {
  const [queryClient] = useState(createQueryClient)
  const [router] = useState(() => createBrowserRouter(routes))

  useEffect(() => {
    configureApiHandlers({
      // RequireAuth reacts to the cleared user by sending the visitor to /login.
      onUnauthenticated: () => queryClient.setQueryData(meQueryKey, null),
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
