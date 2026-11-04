import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { TicketsPage } from '@/features/helpdesk/TicketsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { categoriesHandler, makeTicket } from './testData'

describe('TicketsPage', () => {
  it('shows an employee their tickets without tabs, organization column or export', async () => {
    server.use(
      categoriesHandler,
      http.get('*/api/v1/helpdesk/tickets', () => HttpResponse.json(page([makeTicket()]))),
    )
    renderWithProviders(<TicketsPage />, {
      me: makeMe({ permissions: ['dashboard.view', 'helpdesk.create'] }),
      path: '/helpdesk',
    })

    expect(await screen.findByText('HD-2026-000001')).toBeInTheDocument()
    expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('helpdesk:actions.export') })).not.toBeInTheDocument()
    expect(
      screen.queryByRole('columnheader', { name: i18n.t('helpdesk:fields.organization') }),
    ).not.toBeInTheDocument()
    expect(screen.getAllByRole('link', { name: i18n.t('helpdesk:actions.create') })[0]).toHaveAttribute(
      'href',
      '/helpdesk/new',
    )
  })

  it('switches tabs and filters through the URL and exports the same filters', async () => {
    const requests: URLSearchParams[] = []
    let exportBody: unknown
    server.use(
      categoriesHandler,
      organizationOptionsHandler,
      http.get('*/api/v1/helpdesk/tickets', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(
          page([makeTicket({ status: 'in_progress', organization: { id: 1, name: 'Xorazm GTS' } })]),
        )
      }),
      http.post('*/api/v1/exports', async ({ request }) => {
        exportBody = await request.json()
        return HttpResponse.json({ data: { uuid: 'u1', status: 'pending' } }, { status: 202 })
      }),
    )
    const { router } = renderWithProviders(<TicketsPage />, {
      me: makeMe({
        permissions: ['helpdesk.view', 'helpdesk.process', 'helpdesk.export', 'helpdesk.create'],
      }),
      path: '/helpdesk',
    })

    expect(await screen.findByRole('cell', { name: 'Xorazm GTS' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: i18n.t('helpdesk:tabs.assigned') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[assignee]')).toBe('me'))

    await userEvent.click(screen.getByRole('checkbox', { name: i18n.t('helpdesk:filters.overdue') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[overdue]')).toBe('1'))
    expect(decodeURIComponent(router.state.location.search)).toContain('filter[assignee]=me')

    await userEvent.click(screen.getByRole('button', { name: i18n.t('helpdesk:actions.export') }))
    await userEvent.click(
      await screen.findByRole('menuitem', { name: i18n.t('helpdesk:actions.exportXlsx') }),
    )
    await waitFor(() =>
      expect(exportBody).toEqual({
        type: 'helpdesk_tickets',
        format: 'xlsx',
        filters: { assignee: 'me', overdue: '1' },
      }),
    )
    const row = screen.getByText('HD-2026-000001').closest('tr')
    expect(row && within(row).getByText(i18n.t('helpdesk:status.in_progress'))).toBeInTheDocument()
  })
})
