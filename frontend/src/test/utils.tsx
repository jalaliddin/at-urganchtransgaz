import { MantineProvider } from '@mantine/core'
import { ModalsProvider } from '@mantine/modals'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router'
import { theme } from '@/app/theme'
import { meQueryKey } from '@/features/auth/queries'
import type { Me } from '@/features/auth/types'

export function makeQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } } })
}

interface Options {
  /** Initial URL, e.g. "/login?redirect=/x". */
  route?: string
  /** Extra routes rendered next to the element under test. */
  routes?: RouteObject[]
  /** Path the element is mounted at (default "/"). */
  path?: string
  /** Pre-populates the signed-in user (null = signed out). */
  me?: Me | null
  queryClient?: QueryClient
}

export function renderWithProviders(element: ReactElement, options: Options = {}) {
  const queryClient = options.queryClient ?? makeQueryClient()
  if (options.me !== undefined) {
    queryClient.setQueryData(meQueryKey, options.me)
  }
  const router = createMemoryRouter(
    [{ path: options.path ?? '/', element }, ...(options.routes ?? [])],
    { initialEntries: [options.route ?? options.path ?? '/'] },
  )

  const result = render(
    <MantineProvider theme={theme}>
      <ModalsProvider>
        <QueryClientProvider client={queryClient}>
          <RouterProvider router={router} />
        </QueryClientProvider>
      </ModalsProvider>
    </MantineProvider>,
  )

  return { ...result, router, queryClient }
}
