import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { warehouseCatalogHandlers } from '@/features/warehouse/testData'
import { RequisitionDetailPage } from './RequisitionDetailPage'
import { RequisitionsPage } from './RequisitionsPage'
import { makeRequisition, makeRow } from './testData'

const tr = (key: string, options?: Record<string, unknown>) => i18n.t(`requisitions:${key}`, options)

describe('RequisitionsPage', () => {
  it('switches to the "waiting for me" tab through the URL and creates a draft', async () => {
    const requests: URLSearchParams[] = []
    let sent: Record<string, unknown> | undefined
    server.use(
      ...warehouseCatalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/asset-categories', () => HttpResponse.json({ data: [] })),
      http.get('*/api/v1/organizations/:id', ({ params }) =>
        HttpResponse.json({
          data: { id: Number(params.id), name: 'Urganch filiali', short_name: null, is_active: true },
        }),
      ),
      http.get('*/api/v1/employees', () => HttpResponse.json(page([]))),
      http.get('*/api/v1/requisitions', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(page([makeRow()]))
      }),
      http.post('*/api/v1/requisitions', async ({ request }) => {
        sent = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: makeRequisition({}, { id: 15, status: 'draft' }) }, { status: 201 })
      }),
    )
    const { router } = renderWithProviders(<RequisitionsPage />, {
      me: makeMe({
        permissions: ['requisitions.view', 'requisitions.create'],
        organization: { id: 6, name: 'Urganch filiali' },
      }),
      path: '/requisitions',
      routes: [{ path: '/requisitions/:id', element: <p>card</p> }],
    })

    expect(await screen.findByText('RQ-2026-00009')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: tr('tabs.action') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[tab]')).toBe('action'))

    await userEvent.click(screen.getByRole('button', { name: tr('actions.create') }))
    const drawer = within(await screen.findByRole('dialog'))
    await userEvent.click(drawer.getByRole('textbox', { name: new RegExp(`^${tr('fields.title')}`) }))
    await userEvent.paste('Printer kartrijlari')
    await userEvent.click(drawer.getByRole('textbox', { name: new RegExp(`^${tr('items.name')}`) }))
    await userEvent.paste('HP 85A')
    await userEvent.click(drawer.getByRole('button', { name: tr('actions.saveDraft') }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/requisitions/15'))
    expect(sent).toMatchObject({
      organization_id: 6,
      title: 'Printer kartrijlari',
      items: [{ kind: 'consumable', name: 'HP 85A', quantity: '1' }],
    })
  })
})

describe('RequisitionDetailPage', () => {
  it('lets the central head lower a quantity when approving', async () => {
    let decision: unknown
    server.use(
      http.get('*/api/v1/requisitions/9', () =>
        HttpResponse.json({ data: makeRequisition({ decide: true }) }),
      ),
      http.post('*/api/v1/requisitions/9/decision', async ({ request }) => {
        decision = await request.json()
        return HttpResponse.json({ data: makeRequisition({}, { status: 'approved' }) })
      }),
    )
    renderWithProviders(<RequisitionDetailPage />, {
      me: makeMe({ permissions: ['requisitions.view', 'requisitions.approve'] }),
      path: '/requisitions/:id',
      route: '/requisitions/9',
    })

    await userEvent.click(await screen.findByRole('button', { name: tr('actions.decide') }))
    const dialog = within(await screen.findByRole('dialog'))
    const field = dialog.getByRole('textbox', { name: `${tr('items.approved')}: HP 85A` })
    await userEvent.clear(field)
    await userEvent.type(field, '2')
    await userEvent.click(dialog.getByRole('button', { name: tr('decision.confirm.approved') }))

    await waitFor(() =>
      expect(decision).toEqual({
        decision: 'approved',
        items: [
          { id: 31, approved_quantity: '2' },
          { id: 32, approved_quantity: '1.000' },
        ],
      }),
    )
  })

  it('needs a reason to reject and shows only the actions the server allows', async () => {
    server.use(
      http.get('*/api/v1/requisitions/9', () =>
        HttpResponse.json({ data: makeRequisition({ decide: true }) }),
      ),
    )
    renderWithProviders(<RequisitionDetailPage />, {
      me: makeMe({ permissions: ['requisitions.view'] }),
      path: '/requisitions/:id',
      route: '/requisitions/9',
    })

    expect(screen.queryByRole('button', { name: tr('actions.submit') })).not.toBeInTheDocument()
    await userEvent.click(await screen.findByRole('button', { name: tr('actions.decide') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('radio', { name: tr('decision.rejected') }))
    await userEvent.click(dialog.getByRole('button', { name: tr('decision.confirm.rejected') }))
    expect(await dialog.findByText(i18n.t('validation:required'))).toBeInTheDocument()
  })

  it('allocates an item from a warehouse', async () => {
    let body: unknown
    const approved = makeRequisition({ allocate: true }, { status: 'approved' })
    approved.items[0] = { ...approved.items[0]!, approved_quantity: '4.000', remaining: '4.000' }
    server.use(
      ...warehouseCatalogHandlers,
      http.get('*/api/v1/requisitions/9', () => HttpResponse.json({ data: approved })),
      http.post('*/api/v1/requisitions/9/allocations', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: approved }, { status: 201 })
      }),
    )
    renderWithProviders(<RequisitionDetailPage />, {
      me: makeMe({ permissions: ['requisitions.view', 'requisitions.fulfill'] }),
      path: '/requisitions/:id',
      route: '/requisitions/9',
    })

    await userEvent.click(await screen.findByRole('button', { name: tr('actions.allocate') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('combobox', { name: new RegExp(`^${tr('allocate.warehouse')}`) }))
    await userEvent.click(await screen.findByRole('option', { name: /Markaziy ombor/ }))
    await userEvent.click(dialog.getByRole('button', { name: tr('allocate.confirm') }))

    await waitFor(() =>
      expect(body).toMatchObject({ item_id: 31, type: 'stock', quantity: '4.000', warehouse_id: 3 }),
    )
  })
})
