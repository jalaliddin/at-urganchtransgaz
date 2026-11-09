import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { CostsPage } from './CostsPage'
import { InvoiceDetailPage } from './InvoiceDetailPage'
import type { InvoiceDetail } from './types'

const t = (key: string, options?: Record<string, unknown>) => i18n.t(`services:${key}`, options)

const invoice: InvoiceDetail = {
  id: 3,
  kind: 'regular',
  status: 'awaiting_invoice',
  service: {
    id: 4,
    name: 'Internet 100 Mbit/s',
    type: 'internet',
    provider: { id: 2, name: 'Sarkor Telecom' },
  },
  organization: { id: 6, name: 'Urganch filiali' },
  period_start: '2026-11-01',
  period_end: '2026-11-30',
  planned_amount: '1500000.00',
  invoice_number: null,
  invoice_date: null,
  amount: null,
  due_date: '2026-11-10',
  paid_amount: '0.00',
  approved_by: null,
  approved_at: null,
  file: null,
  description: null,
  note: null,
  payments: [],
}

describe('InvoiceDetailPage', () => {
  it('enters the invoice amount; approval and payment appear only for their roles and states', async () => {
    let entered: unknown
    server.use(
      http.get('*/api/v1/service-invoices/3', () => HttpResponse.json({ data: invoice })),
      http.put('*/api/v1/service-invoices/3', async ({ request }) => {
        entered = await request.json()
        return HttpResponse.json({ data: { ...invoice, amount: '1520000.00', status: 'due' } })
      }),
    )
    renderWithProviders(<InvoiceDetailPage />, {
      me: makeMe({ permissions: ['services.view', 'services.invoice'] }),
      path: '/services/invoices/:id',
      route: '/services/invoices/3',
    })

    await userEvent.click(await screen.findByRole('button', { name: t('invoices.enter') }))
    expect(screen.queryByRole('button', { name: t('invoices.approve') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: t('payments.add') })).not.toBeInTheDocument()
    const dialog = within(await screen.findByRole('dialog'))
    const amount = dialog.getByRole('textbox', { name: new RegExp(`^${t('invoices.amount')}`) })
    await userEvent.clear(amount)
    await userEvent.type(amount, '1520000')
    await userEvent.type(dialog.getByRole('textbox', { name: t('invoices.number') }), 'HF-1107')
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))
    await waitFor(() => expect(entered).toMatchObject({ amount: '1520000', invoice_number: 'HF-1107' }))
  })

  it('records a payment against an approved invoice, never more than what is left', async () => {
    let paid: unknown
    const approved = {
      ...invoice,
      status: 'partially_paid' as const,
      amount: '1520000.00',
      paid_amount: '520000.00',
      approved_at: '2026-11-07T05:00:00Z',
      approved_by: { id: 1, name: 'Markaz' },
    }
    server.use(
      http.get('*/api/v1/service-invoices/3', () => HttpResponse.json({ data: approved })),
      http.post('*/api/v1/service-invoices/3/payments', async ({ request }) => {
        paid = await request.json()
        return HttpResponse.json(
          { data: { ...approved, status: 'paid', paid_amount: '1520000.00' } },
          { status: 201 },
        )
      }),
    )
    renderWithProviders(<InvoiceDetailPage />, {
      me: makeMe({ permissions: ['services.view', 'services.pay', 'services.approve'] }),
      path: '/services/invoices/:id',
      route: '/services/invoices/3',
    })

    expect(await screen.findByRole('heading', { name: 'Internet 100 Mbit/s' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: t('invoices.approve') })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: t('payments.add') }))
    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByRole('textbox', { name: new RegExp(`^${t('payments.amount')}`) })).toHaveValue(
      '1 000 000.00',
    )
    await userEvent.click(dialog.getByRole('button', { name: t('payments.save') }))
    await waitFor(() => expect(paid).toMatchObject({ amount: '1000000' }))
  })
})

describe('CostsPage', () => {
  it('shows twelve months per group and regroups through the URL', async () => {
    const requests: URLSearchParams[] = []
    const cell = { planned: '0.00', invoiced: '0.00', paid: '0.00' }
    server.use(
      http.get('*/api/v1/service-costs', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        const months = Array.from({ length: 12 }, () => cell)
        months[10] = { planned: '1500000.00', invoiced: '1520000.00', paid: '1520000.00' }
        return HttpResponse.json({
          data: {
            year: 2026,
            rows: [
              {
                key: { id: 6, name: 'Urganch filiali' },
                months,
                total: { planned: '1500000.00', invoiced: '1520000.00', paid: '1520000.00' },
              },
            ],
            total: { planned: '1500000.00', invoiced: '1520000.00', paid: '1520000.00' },
          },
        })
      }),
    )
    renderWithProviders(<CostsPage />, {
      me: makeMe({ permissions: ['services.view'] }),
      path: '/services/costs',
    })

    expect(await screen.findByText('Urganch filiali')).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: t('months.11') })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: t('costs.groups.provider') }))
    await waitFor(() => expect(requests.at(-1)?.get('group_by')).toBe('provider'))
  })
})
