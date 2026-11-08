import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { CountPage } from './CountPage'
import { StockDocumentDrawer } from './StockDocumentDrawer'
import { StockDocumentPage } from './StockDocumentPage'
import { makeCount, makeDocument, warehouseCatalogHandlers } from './testData'

const tw = (key: string) => i18n.t(`warehouse:${key}`)

describe('warehouse review fixes', () => {
  it('saves unsaved counted quantities before closing the count', async () => {
    const calls: string[] = []
    server.use(
      http.get('*/api/v1/stock-counts/7', () => HttpResponse.json({ data: makeCount() })),
      http.patch('*/api/v1/stock-counts/7/lines', async ({ request }) => {
        calls.push(`patch ${JSON.stringify(await request.json())}`)
        return HttpResponse.json({ data: makeCount() })
      }),
      http.post('*/api/v1/stock-counts/7/close', () => {
        calls.push('close')
        return HttpResponse.json({ data: makeCount({ status: 'closed', can: { manage: false } }) })
      }),
    )
    renderWithProviders(<CountPage />, {
      me: makeMe({ permissions: ['warehouse.inventory'] }),
      path: '/warehouse/counts/:id',
      route: '/warehouse/counts/7',
    })

    await userEvent.type(
      await screen.findByRole('textbox', { name: `${tw('counts.counted')}: HP 85A kartrij` }),
      '3',
    )
    await userEvent.click(screen.getByRole('button', { name: tw('counts.close') }))
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', { name: tw('counts.close') }),
    )

    await waitFor(() =>
      expect(calls).toEqual(['patch {"lines":[{"id":101,"counted_quantity":"3"}]}', 'close']),
    )
  })

  it('labels the add-line button in Uzbek', async () => {
    server.use(
      http.get('*/api/v1/stock-counts/7', () => HttpResponse.json({ data: makeCount() })),
      ...warehouseCatalogHandlers,
    )
    renderWithProviders(<CountPage />, {
      me: makeMe({ permissions: ['warehouse.inventory'] }),
      path: '/warehouse/counts/:id',
      route: '/warehouse/counts/7',
    })

    await userEvent.click(await screen.findByRole('button', { name: tw('counts.addLine') }))
    expect(
      within(await screen.findByRole('dialog')).getByRole('button', { name: "Qo'shish" }),
    ).toBeInTheDocument()
  })

  it('flags an empty line where it is instead of dropping it', async () => {
    const post = vi.fn()
    server.use(
      ...warehouseCatalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/employees', () => HttpResponse.json(page([]))),
      http.post('*/api/v1/stock-documents', () => {
        post()
        return HttpResponse.json({ data: makeDocument() }, { status: 201 })
      }),
    )
    renderWithProviders(
      <StockDocumentDrawer type="receipt" defaults={{ warehouse_id: 3 }} onClose={vi.fn()} />,
      { me: makeMe({ permissions: ['warehouse.receive'] }) },
    )
    const drawer = within(await screen.findByRole('dialog'))

    await userEvent.click(drawer.getByRole('combobox', { name: new RegExp(`^${tw('lines.material')}`) }))
    await userEvent.click(await screen.findByRole('option', { name: /HP 85A kartrij/ }))
    await userEvent.type(drawer.getByRole('textbox', { name: new RegExp(`^${tw('lines.quantity')}`) }), '2')
    await userEvent.click(drawer.getByRole('button', { name: tw('lines.add') }))
    await userEvent.click(drawer.getByRole('button', { name: tw('actions.post') }))

    const materials = drawer.getAllByRole('combobox', { name: new RegExp(`^${tw('lines.material')}`) })
    expect(materials[1]).toHaveAttribute('aria-invalid', 'true')
    expect(materials[0]).not.toHaveAttribute('aria-invalid', 'true')
    expect(post).not.toHaveBeenCalled()
  })

  it('offers a return only when the server allows it', async () => {
    server.use(
      http.get('*/api/v1/stock-documents/21', () =>
        HttpResponse.json({ data: makeDocument({ can: { attach_file: false, return: false } }) }),
      ),
    )
    renderWithProviders(<StockDocumentPage />, {
      me: makeMe({ permissions: ['warehouse.view', 'warehouse.receive'] }),
      path: '/warehouse/documents/:id',
      route: '/warehouse/documents/21',
    })

    expect(await screen.findByText('IS-2026-00001')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: tw('actions.return') })).not.toBeInTheDocument()
  })
})
