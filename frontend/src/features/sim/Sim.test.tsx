import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { RequisitionDetailPage } from '@/features/requisitions/RequisitionDetailPage'
import { makeRequisition } from '@/features/requisitions/testData'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { SimDetailPage } from './SimDetailPage'
import type { SimDetail } from './types'

const t = (key: string, options?: Record<string, unknown>) => i18n.t(`sim:${key}`, options)

const card: SimDetail = {
  id: 5,
  phone_number: '998901234567',
  provider: { id: 2, name: 'Beeline' },
  organization: { id: 6, name: 'Urganch filiali' },
  department: null,
  employee: null,
  status: 'in_stock',
  previous_status: null,
  tariff: 'Korporativ 50',
  account_number: 'K-778',
  monthly_fee: '45000.00',
  iccid_masked: '••••••••0123',
  has_iccid: true,
  allocated_at: null,
  returned_at: null,
  note: null,
  assignments: [],
  history: [
    {
      id: 1,
      event: 'registered',
      payload: { phone_number: '998901234567' },
      user: { id: 1, name: 'Admin' },
      requisition_id: null,
      created_at: '2026-11-08T05:00:00Z',
    },
  ],
}

describe('SimDetailPage', () => {
  it('shows only the masked ICCID, offers the actions the status allows and assigns a card', async () => {
    let body: unknown
    server.use(
      http.get('*/api/v1/sim-cards/5', () => HttpResponse.json({ data: card })),
      http.get('*/api/v1/sim-cards/5/documents', () => HttpResponse.json({ data: [] })),
      http.get('*/api/v1/employees', () =>
        HttpResponse.json(
          page([
            {
              id: 8,
              full_name: 'Karimov Alisher',
              position: 'Muhandis',
              organization: { id: 6, name: 'Urganch filiali' },
              department: null,
            },
          ]),
        ),
      ),
      http.get('*/api/v1/departments', () => HttpResponse.json(page([]))),
      http.post('*/api/v1/sim-cards/5/assign', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...card, status: 'active' } })
      }),
    )
    renderWithProviders(<SimDetailPage />, {
      me: makeMe({ permissions: ['sim.view', 'sim.assign'] }),
      path: '/sim/:id',
      route: '/sim/5',
    })

    expect(await screen.findByText('••••••••0123')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: '+998 90 123 45 67' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: t('reveal') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: t('actions.unblock') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: t('actions.transfer') })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: t('actions.assign') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: t('actions.assign') }))
    expect(await dialog.findByText(t('actions.holderRequired'))).toBeInTheDocument()

    await userEvent.click(dialog.getByRole('combobox', { name: new RegExp(`^${t('fields.employee')}`) }))
    await userEvent.click(await screen.findByRole('option', { name: /Karimov Alisher/ }))
    await userEvent.click(dialog.getByRole('button', { name: t('actions.assign') }))
    await waitFor(() => expect(body).toMatchObject({ employee_id: 8, department_id: null }))
  })

  it('lets a SIM manager reveal the ICCID', async () => {
    server.use(
      http.get('*/api/v1/sim-cards/5', () => HttpResponse.json({ data: card })),
      http.get('*/api/v1/sim-cards/5/documents', () => HttpResponse.json({ data: [] })),
      http.post('*/api/v1/sim-cards/5/reveal', () =>
        HttpResponse.json({ data: { iccid: '89998011234567890123' } }),
      ),
    )
    renderWithProviders(<SimDetailPage />, {
      me: makeMe({ permissions: ['sim.view', 'sim.manage'] }),
      path: '/sim/:id',
      route: '/sim/5',
    })

    await userEvent.click(await screen.findByRole('button', { name: t('reveal') }))
    expect(within(await screen.findByRole('dialog')).getByTestId('sim-iccid')).toHaveTextContent(
      '89998011234567890123',
    )
  })
})

describe('Requisition SIM allocation', () => {
  it('allocates a free SIM card to a SIM item', async () => {
    let body: unknown
    const requisition = makeRequisition({ allocate: true }, { status: 'approved' })
    requisition.items = [
      {
        ...requisition.items[0]!,
        id: 33,
        kind: 'sim',
        name: 'SIM-karta',
        material: null,
        approved_quantity: '1.000',
        remaining: '1.000',
        unit: null,
      },
    ]
    server.use(
      http.get('*/api/v1/requisitions/9', () => HttpResponse.json({ data: requisition })),
      http.get('*/api/v1/sim-cards', ({ request }) => {
        expect(new URL(request.url).searchParams.get('filter[status]')).toBe('in_stock,returned')
        return HttpResponse.json(page([{ ...card, id: 7 }]))
      }),
      http.post('*/api/v1/requisitions/9/allocations', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: requisition }, { status: 201 })
      }),
    )
    renderWithProviders(<RequisitionDetailPage />, {
      me: makeMe({ permissions: ['requisitions.view', 'requisitions.fulfill', 'sim.view'] }),
      path: '/requisitions/:id',
      route: '/requisitions/9',
    })

    await userEvent.click(
      await screen.findByRole('button', { name: i18n.t('requisitions:actions.allocate') }),
    )
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(
      dialog.getByRole('combobox', { name: new RegExp(`^${i18n.t('requisitions:allocate.sim')}`) }),
    )
    await userEvent.click(await screen.findByRole('option', { name: /\+998 90 123 45 67/ }))
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('requisitions:allocate.confirm') }))
    await waitFor(() => expect(body).toMatchObject({ item_id: 33, type: 'sim', sim_card_id: 7 }))
  })
})
