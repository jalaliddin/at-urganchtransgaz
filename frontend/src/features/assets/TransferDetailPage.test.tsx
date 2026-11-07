import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { TransferDetailPage } from '@/features/assets/TransferDetailPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import type { Transfer } from './types'

const transfer: Transfer = {
  id: 3,
  number: 'TR-2026-00001',
  status: 'sent',
  direction: 'incoming',
  from: { id: 1, name: 'Urganch GTS' },
  to: { id: 2, name: 'Xiva GTS' },
  note: null,
  items_count: 1,
  act: { uuid: 'act-uuid', name: 'TR-2026-00001.pdf' },
  created_by: 'Qodirov Nodira',
  created_at: '2026-11-07T05:00:00Z',
  sent_at: '2026-11-07T06:00:00Z',
  received_at: null,
  cancelled_at: null,
  can: { edit: false, send: false, cancel: false, receive: true },
  items: [
    {
      asset: {
        id: 9,
        inventory_number: 'A-PC',
        name: 'HP ProDesk',
        category: 'Kompyuter',
        serial_number: 'SN1',
      },
      condition_note: null,
    },
  ],
}

describe('TransferDetailPage', () => {
  it('lets the receiving side accept after confirmation and offers the act', async () => {
    const actions: string[] = []
    server.use(
      http.get('*/api/v1/asset-transfers/3', () => HttpResponse.json({ data: transfer })),
      http.post('*/api/v1/asset-transfers/3/:action', ({ params }) => {
        actions.push(String(params.action))
        return HttpResponse.json({
          data: { ...transfer, status: 'received', can: { ...transfer.can, receive: false } },
        })
      }),
    )
    renderWithProviders(<TransferDetailPage />, {
      me: makeMe({ permissions: ['assets.view', 'assets.transfer'] }),
      path: '/assets/transfers/:id',
      route: '/assets/transfers/3',
    })

    expect(await screen.findByText('A-PC')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: i18n.t('assets:transfers.act') })).toHaveAttribute(
      'href',
      '/api/v1/files/act-uuid',
    )
    expect(screen.queryByRole('button', { name: i18n.t('assets:transfers.send') })).not.toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: i18n.t('assets:transfers.receive') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('assets:transfers.receive') }))

    await waitFor(() => expect(actions).toEqual(['receive']))
  })
})
