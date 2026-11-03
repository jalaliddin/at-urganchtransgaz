import { act, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { useEffect } from 'react'
import { describe, expect, it } from 'vitest'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { meQueryKey } from '@/features/auth/queries'
import { resetClientSession } from '@/features/auth/session'
import { EmployeeFormDrawer } from '@/features/employees/EmployeeFormDrawer'
import { OrganizationFormDrawer } from '@/features/organizations/OrganizationFormDrawer'
import type { Organization } from '@/features/organizations/types'
import { useTableParams, type TableParams } from '@/hooks/useTableParams'
import i18n from '@/i18n'
import { useUiStore } from '@/stores/ui'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { makeQueryClient, renderWithProviders } from '@/test/utils'

const rootOrganization: Organization = {
  id: 1,
  parent_id: null,
  parent: null,
  depth: 0,
  type: 'head_office',
  type_label: 'Markaziy apparat',
  name: 'Markaziy apparat',
  short_name: null,
  code: 'HQ',
  tin: null,
  address: null,
  phone: null,
  email: null,
  head_name: null,
  is_active: true,
  sort_order: 0,
  created_at: null,
  updated_at: null,
  can: { update: true, delete: false, manage_it_staff: true },
}

describe('final review regressions', () => {
  // I5
  it('applies several filters from one handler', async () => {
    let captured: TableParams | null = null
    function Probe() {
      const params = useTableParams({ defaultSort: 'name' })
      useEffect(() => {
        captured = params
      })
      return <div>{JSON.stringify(params.filters)}</div>
    }
    renderWithProviders(<Probe />, { me: makeMe(), route: '/?filter[department]=4' })
    await screen.findByText(/department/)

    act(() => captured?.setFilters({ organization: '7', department: null }))

    await waitFor(() => expect(captured?.filters).toEqual({ organization: '7' }))
    act(() => captured?.setFilters({ created_from: '2026-11-01', created_to: '2026-11-03' }))
    await waitFor(() =>
      expect(captured?.filters).toEqual({
        organization: '7',
        created_from: '2026-11-01',
        created_to: '2026-11-03',
      }),
    )
  })

  // I2 (frontend)
  it('edits a root organization without re-sending or requiring its parent', async () => {
    let body: Record<string, unknown> | null = null
    server.use(
      organizationOptionsHandler,
      http.get('*/api/v1/organizations/1', () => HttpResponse.json({ data: rootOrganization })),
      http.put('*/api/v1/organizations/1', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: rootOrganization })
      }),
    )
    renderWithProviders(
      <OrganizationFormDrawer opened organization={rootOrganization} onClose={() => {}} />,
      { me: makeMe() },
    )

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).not.toHaveProperty('parent_id')
  })

  it('edits an employee without re-sending an unchanged organization', async () => {
    let body: Record<string, unknown> | null = null
    server.use(
      organizationOptionsHandler,
      http.get('*/api/v1/departments', () => HttpResponse.json(page([]))),
      http.put('*/api/v1/employees/7', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { id: 7 } })
      }),
    )
    const employee = {
      id: 7,
      full_name: 'Karimov Aziz',
      last_name: 'Karimov',
      first_name: 'Aziz',
      middle_name: null,
      position: null,
      personnel_number: null,
      phone: null,
      internal_phone: null,
      email: null,
      status: 'active' as const,
      status_label: 'Faol',
      hired_at: null,
      dismissed_at: null,
      notes: null,
      organization: { id: 1, name: 'Xorazm GTS' },
      department: null,
      user: null,
      created_at: null,
      updated_at: null,
      can: { update: true, delete: true },
    }
    renderWithProviders(<EmployeeFormDrawer opened employee={employee} onClose={() => {}} />, {
      me: makeMe(),
    })

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(body).not.toBeNull())
    expect(body).not.toHaveProperty('organization_id')
  })

  // I7
  it('ignores an organization context chosen by another user', async () => {
    const urls: URL[] = []
    server.use(
      http.get('*/api/v1/dashboard', ({ request }) => {
        urls.push(new URL(request.url))
        return HttpResponse.json({
          data: { profile: { name: 'X', username: 'x', organization: null, roles: [] } },
        })
      }),
    )
    act(() => useUiStore.setState({ orgContext: { userId: 1, organizationId: 5 } }))

    renderWithProviders(<DashboardPage />, { me: makeMe({ id: 2 }) })

    await waitFor(() => expect(urls).toHaveLength(1))
    expect(urls[0]?.searchParams.has('organization_id')).toBe(false)
  })

  it("applies the signed-in user's own organization context", async () => {
    const urls: URL[] = []
    server.use(
      http.get('*/api/v1/dashboard', ({ request }) => {
        urls.push(new URL(request.url))
        return HttpResponse.json({
          data: { profile: { name: 'X', username: 'x', organization: null, roles: [] } },
        })
      }),
    )
    act(() => useUiStore.setState({ orgContext: { userId: 2, organizationId: 5 } }))

    renderWithProviders(<DashboardPage />, { me: makeMe({ id: 2 }) })

    await waitFor(() => expect(urls).toHaveLength(1))
    expect(urls[0]?.searchParams.get('organization_id')).toBe('5')
  })

  it('reset of the client session drops cached data and the organization context', () => {
    const queryClient = makeQueryClient()
    queryClient.setQueryData(['employees', 'list', {}], { data: [] })
    queryClient.setQueryData(meQueryKey, makeMe())
    useUiStore.setState({ orgContext: { userId: 1, organizationId: 5 } })

    resetClientSession(queryClient)

    expect(queryClient.getQueryData(['employees', 'list', {}])).toBeUndefined()
    expect(queryClient.getQueryData(meQueryKey)).toBeNull()
    expect(useUiStore.getState().orgContext).toBeNull()
  })
})
