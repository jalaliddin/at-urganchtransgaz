import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { CartridgesPage } from './CartridgesPage'
import { ConsumptionPage } from './ConsumptionPage'
import { PrinterPage } from './PrinterPage'
import type { ConsumptionReport, PrinterDetail, Replacement } from './types'

const t = (key: string, options?: Record<string, unknown>) => i18n.t(`printers:${key}`, options)

const printer: PrinterDetail = {
  id: 5,
  inventory_number: 'AT-000042',
  name: 'HP LaserJet Pro M404',
  model: { id: 1, name: 'LaserJet Pro M404', manufacturer: 'HP' },
  serial_number: 'PHB123',
  ip_address: '10.10.1.25',
  hostname: null,
  status: 'in_use',
  organization: { id: 6, name: 'Xiva GTS' },
  location: null,
  room: '12-xona',
  holder: { id: 8, full_name: 'Karimov Alisher' },
  connection_type: 'network',
  is_color: false,
  note: null,
  last_counter: { read_at: '2026-11-01', total: 12000 },
  last_replacement: null,
  department: null,
  compatibility_defined: true,
  cartridges: [
    {
      id: 3,
      name: 'HP 59A',
      part_number: 'CF259A',
      kind: 'toner',
      color: 'black',
      page_yield: 3000,
      is_active: true,
      material: { id: 11, code: 'MT-00011', name: 'HP 59A (CF259A)', unit: 'dona' },
      printer_models: [],
      stock: '3.000',
      can_delete: null,
      warehouses: [
        { warehouse: { id: 7, name: 'Xiva ombori', organization: 'Xiva GTS' }, quantity: '3.000' },
      ],
    },
  ],
  monthly_pages: [
    { month: '2026-10', pages: 900 },
    { month: '2026-11', pages: null },
  ],
  maintenances: [],
  can: { service: true, issue_from_warehouse: true, view_asset: true },
}

const replacement: Replacement = {
  id: 9,
  replaced_at: '2026-11-10',
  printer: { id: 5, inventory_number: 'AT-000042', name: 'HP LaserJet Pro M404' },
  organization: { id: 6, name: 'Xiva GTS' },
  cartridge: { id: 3, name: 'HP 59A', part_number: 'CF259A', color: 'black', page_yield: 3000 },
  source: 'warehouse',
  warehouse: { id: 7, name: 'Xiva ombori' },
  stock_document: { id: 40, number: 'IS-2026-00004' },
  cost: '250000.00',
  counter: { id: 2, total: 15000 },
  pages_since_previous: 2800,
  note: null,
  performed_by: { id: 1, name: 'Admin' },
  created_at: '2026-11-10T05:00:00Z',
  cancelled_at: null,
  cancelled_by: null,
  cancel_reason: null,
  return_document: null,
  can_cancel: true,
}

function printerPage(replacements: Replacement[] = []) {
  server.use(
    http.get('*/api/v1/printers/5', () => HttpResponse.json({ data: printer })),
    http.get('*/api/v1/printers/5/replacements', () => HttpResponse.json(page(replacements))),
    http.get('*/api/v1/printers/5/counters', () => HttpResponse.json(page([]))),
  )
  return renderWithProviders(<PrinterPage />, {
    me: makeMe({ permissions: ['printers.view', 'printers.service', 'warehouse.issue', 'assets.view'] }),
    path: '/printers/:id',
    route: '/printers/5',
  })
}

describe('PrinterPage', () => {
  it('replaces a cartridge from the warehouse that holds it, with a counter reading', async () => {
    let body: unknown
    server.use(
      http.post('*/api/v1/printers/5/replacements', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: replacement }, { status: 201 })
      }),
    )
    printerPage()

    expect(await screen.findByRole('heading', { name: 'HP LaserJet Pro M404' })).toBeInTheDocument()
    expect(screen.getByText('Xiva ombori: 3')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: new RegExp(t('detail.asset')) })).toHaveAttribute(
      'href',
      '/assets/5',
    )

    await userEvent.click(screen.getByRole('button', { name: t('replace.title') }))
    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.queryByText(t('replace.noCompatibility'))).not.toBeInTheDocument()
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(`^${t('fields.counter')}`) }),
      '15000',
    )
    await userEvent.click(dialog.getByRole('button', { name: t('replace.submit') }))

    await waitFor(() =>
      expect(body).toMatchObject({
        cartridge_model_id: 3,
        source: 'warehouse',
        warehouse_id: 7,
        counter: { total: 15000, is_reset: false },
      }),
    )
  })

  it('cancels a replacement only with a reason', async () => {
    let body: unknown
    server.use(
      http.post('*/api/v1/cartridge-replacements/9/cancel', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({
          data: { ...replacement, cancelled_at: '2026-11-11T05:00:00Z', can_cancel: false },
        })
      }),
    )
    printerPage([replacement])

    expect(await screen.findByText('2 800')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: t('cancel.action') }))
    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByText(t('cancel.stockBack', { warehouse: 'Xiva ombori' }))).toBeInTheDocument()
    await userEvent.click(dialog.getByRole('button', { name: t('cancel.submit') }))
    expect(await dialog.findByText(i18n.t('validation:required'))).toBeInTheDocument()
    expect(body).toBeUndefined()

    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(t('cancel.reason')) }),
      'Xato kiritildi',
    )
    await userEvent.click(dialog.getByRole('button', { name: t('cancel.submit') }))
    await waitFor(() => expect(body).toEqual({ reason: 'Xato kiritildi' }))
  })

  it('records a reset counter and shows the server error on the field', async () => {
    const bodies: unknown[] = []
    server.use(
      http.post('*/api/v1/printers/5/counters', async ({ request }) => {
        bodies.push(await request.json())
        return HttpResponse.json(
          { message: 'Xato', errors: { total: ["Hisoblagich keyingi ko'rsatkichdan oshmasligi kerak"] } },
          { status: 422 },
        )
      }),
    )
    printerPage()

    await userEvent.click(await screen.findByRole('button', { name: t('counter.title') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByRole('textbox', { name: new RegExp(`^${t('fields.total')}`) }), '50')
    await userEvent.click(dialog.getByRole('checkbox', { name: new RegExp(t('counter.reset')) }))
    await userEvent.click(dialog.getByRole('button', { name: t('counter.submit') }))

    expect(await dialog.findByText("Hisoblagich keyingi ko'rsatkichdan oshmasligi kerak")).toBeInTheDocument()
    expect(bodies[0]).toMatchObject({ total: 50, is_reset: true })
  })
})

describe('CartridgesPage', () => {
  it('adds a cartridge for chosen printer models without picking a material', async () => {
    let body: unknown
    server.use(
      http.get('*/api/v1/cartridge-models', () => HttpResponse.json(page([]))),
      http.get('*/api/v1/printer-models', () =>
        HttpResponse.json({ data: [{ id: 1, name: 'LaserJet Pro M404', manufacturer: 'HP' }] }),
      ),
      http.post('*/api/v1/cartridge-models', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...printer.cartridges[0] } }, { status: 201 })
      }),
    )
    renderWithProviders(<CartridgesPage />, {
      me: makeMe({ permissions: ['printers.view', 'printers.manage'] }),
    })

    await userEvent.click(await screen.findByRole('button', { name: t('catalog.add') }))
    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.queryByRole('textbox', { name: new RegExp(t('fields.material')) })).not.toBeInTheDocument()
    await userEvent.type(dialog.getByRole('textbox', { name: new RegExp(`^${t('fields.name')}`) }), 'HP 59A')
    await userEvent.type(dialog.getByRole('textbox', { name: t('fields.partNumber') }), 'CF259A')
    await userEvent.click(dialog.getAllByLabelText(t('fields.printerModels'))[0]!)
    await userEvent.click(await screen.findByRole('option', { name: 'HP LaserJet Pro M404' }))
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() =>
      expect(body).toEqual({
        name: 'HP 59A',
        part_number: 'CF259A',
        kind: 'toner',
        color: 'black',
        page_yield: null,
        is_active: true,
        material_id: null,
        printer_model_ids: [1],
      }),
    )
  })
})

describe('ConsumptionPage', () => {
  it('shows consumption per organization and regroups by cartridge', async () => {
    const groups: (string | null)[] = []
    const report = (group: 'organization' | 'cartridge'): ConsumptionReport => ({
      period: { from: '2026-01-01', to: '2026-12-31' },
      group_by: group,
      rows: [
        {
          key:
            group === 'organization'
              ? { id: 6, name: 'Xiva GTS', code: null }
              : { id: 3, name: 'HP 59A', code: 'CF259A' },
          replacements: 2,
          cost: '250000.00',
          pages: group === 'organization' ? 8000 : null,
          cost_per_page: group === 'organization' ? '31.25' : null,
        },
      ],
      total: { replacements: 2, cost: '250000.00', pages: 8000, cost_per_page: '31.25' },
      monthly: [{ month: '2026-03', replacements: 1, cost: '250000.00', pages: 5000 }],
    })
    server.use(
      http.get('*/api/v1/printer-report', ({ request }) => {
        const group = new URL(request.url).searchParams.get('group_by')
        groups.push(group)
        return HttpResponse.json({ data: report(group === 'cartridge' ? 'cartridge' : 'organization') })
      }),
    )
    renderWithProviders(<ConsumptionPage />, { me: makeMe({ permissions: ['printers.view'] }) })

    expect(await screen.findByText('Xiva GTS')).toBeInTheDocument()
    expect(screen.getAllByText(/^31,25\s+so'm$/)).toHaveLength(2)
    await userEvent.click(screen.getByRole('radio', { name: t('report.groups.cartridge') }))
    expect(await screen.findByText('CF259A, HP 59A')).toBeInTheDocument()
    expect(groups).toEqual(['organization', 'cartridge'])
  })
})
