import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { BalancesPage } from './BalancesPage'
import { CountPage } from './CountPage'
import { StockDocumentDrawer } from './StockDocumentDrawer'
import { makeBalance, makeCount, makeDocument, warehouseCatalogHandlers } from './testData'

const tw = (key: string, options?: Record<string, unknown>) => i18n.t(`warehouse:${key}`, options)

describe('BalancesPage', () => {
  it('flags low stock and filters through the URL', async () => {
    const requests: URLSearchParams[] = []
    server.use(
      ...warehouseCatalogHandlers,
      http.get('*/api/v1/stock-balances', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(page([makeBalance()]))
      }),
    )
    renderWithProviders(<BalancesPage />, {
      me: makeMe({ permissions: ['warehouse.view', 'warehouse.receive'] }),
      path: '/warehouse',
    })

    expect(await screen.findByText('HP 85A kartrij')).toBeInTheDocument()
    expect(within(screen.getByRole('table')).getByText(tw('balances.low'))).toBeInTheDocument()
    await userEvent.click(screen.getByRole('checkbox', { name: tw('filters.low') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[low]')).toBe('1'))
    expect(screen.getByRole('button', { name: new RegExp(tw('actions.newDocument')) })).toBeInTheDocument()
  })
})

describe('StockDocumentDrawer', () => {
  it('shows live availability and the shortage the server reports, then posts', async () => {
    const sent: Record<string, unknown>[] = []
    let fail = true
    server.use(
      ...warehouseCatalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/employees', () => HttpResponse.json(page([]))),
      http.get('*/api/v1/assets', () => HttpResponse.json(page([]))),
      http.get('*/api/v1/stock-availability', () =>
        HttpResponse.json({ data: { total: '2.000', lots: [] } }),
      ),
      http.post('*/api/v1/stock-documents', async ({ request }) => {
        sent.push((await request.json()) as Record<string, unknown>)
        if (fail) {
          fail = false
          return HttpResponse.json(
            {
              message: 'x',
              code: 'insufficient_stock',
              shortages: [
                {
                  material_id: 11,
                  material: 'HP 85A kartrij',
                  batch: null,
                  available: '2.000',
                  requested: '3.000',
                },
              ],
            },
            { status: 422 },
          )
        }
        return HttpResponse.json({ data: makeDocument() }, { status: 201 })
      }),
    )
    const onClose = vi.fn()
    renderWithProviders(<StockDocumentDrawer type="issue" onClose={onClose} />, {
      me: makeMe({ permissions: ['warehouse.issue'] }),
    })
    const drawer = within(await screen.findByRole('dialog'))

    await userEvent.click(
      drawer.getByRole('combobox', { name: new RegExp(tw('fields.fromWarehouse').replace(/[()]/g, '.')) }),
    )
    await userEvent.click(await screen.findByRole('option', { name: /Markaziy ombor/ }))
    await userEvent.click(drawer.getByRole('combobox', { name: new RegExp(`^${tw('lines.material')}`) }))
    await userEvent.click(await screen.findByRole('option', { name: /HP 85A kartrij/ }))
    expect(
      await drawer.findByText(tw('lines.available', { quantity: '2', unit: 'dona' })),
    ).toBeInTheDocument()

    await userEvent.type(drawer.getByRole('textbox', { name: new RegExp(`^${tw('lines.quantity')}`) }), '3')
    await userEvent.click(drawer.getByRole('button', { name: tw('actions.post') }))
    expect(await drawer.findByText(tw('fields.reason'), { exact: false })).toBeInTheDocument()
    expect(sent).toHaveLength(0)

    await userEvent.type(drawer.getByRole('textbox', { name: new RegExp(tw('fields.reason')) }), 'Printer')
    await userEvent.click(drawer.getByRole('button', { name: tw('actions.post') }))
    expect(await drawer.findByText(tw('errors.insufficient'))).toBeInTheDocument()
    expect(sent[0]).toMatchObject({
      type: 'issue',
      warehouse_id: 3,
      reason: 'Printer',
      lines: [{ material_id: 11, quantity: '3' }],
    })

    await userEvent.click(drawer.getByRole('button', { name: tw('actions.post') }))
    await waitFor(() =>
      expect(onClose).toHaveBeenCalledWith(expect.objectContaining({ number: 'IS-2026-00001' })),
    )
  })
})

describe('CountPage', () => {
  it('saves counted quantities and closes the count', async () => {
    let recorded: unknown
    let closed = false
    server.use(
      http.get('*/api/v1/stock-counts/7', () => HttpResponse.json({ data: makeCount() })),
      http.patch('*/api/v1/stock-counts/7/lines', async ({ request }) => {
        recorded = await request.json()
        return HttpResponse.json({ data: makeCount() })
      }),
      http.post('*/api/v1/stock-counts/7/close', () => {
        closed = true
        return HttpResponse.json({ data: makeCount({ status: 'closed', can: { manage: false } }) })
      }),
    )
    renderWithProviders(<CountPage />, {
      me: makeMe({ permissions: ['warehouse.view', 'warehouse.inventory'] }),
      path: '/warehouse/counts/:id',
      route: '/warehouse/counts/7',
    })

    expect(await screen.findByText(tw('counts.frozen'))).toBeInTheDocument()
    await userEvent.type(
      screen.getByRole('textbox', { name: `${tw('counts.counted')}: HP 85A kartrij` }),
      '4',
    )
    expect(screen.getByText('-1')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: tw('counts.save') }))
    await waitFor(() => expect(recorded).toEqual({ lines: [{ id: 101, counted_quantity: '4' }] }))

    await userEvent.click(screen.getByRole('button', { name: tw('counts.close') }))
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: tw('counts.close') }),
    )
    await waitFor(() => expect(closed).toBe(true))
  })
})
