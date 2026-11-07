import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { InventorySessionPage } from '@/features/assets/InventorySessionPage'
import { LabelsPage } from '@/features/assets/LabelsPage'
import { LookupPage } from '@/features/assets/LookupPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { makeAsset } from './testData'
import type { InventorySession } from './types'

const session: InventorySession = {
  id: 4,
  name: '2026 yillik',
  status: 'open',
  note: null,
  organization: { id: 5, name: 'Xorazm GTS' },
  include_descendants: true,
  started_by: 'Buxgalter',
  started_at: '2026-11-07T05:00:00Z',
  closed_at: null,
  expected_count: 2,
  found_count: 0,
  missing_count: 2,
  unexpected_count: 0,
}

describe('Inventory scan screen', () => {
  it('scans a code with Enter, shows the result and clears the field', async () => {
    const scanned: unknown[] = []
    server.use(
      http.get('*/api/v1/inventory-sessions/4', () => HttpResponse.json({ data: session })),
      http.get('*/api/v1/inventory-sessions/4/items', () => HttpResponse.json(page([]))),
      http.post('*/api/v1/inventory-sessions/4/scan', async ({ request }) => {
        scanned.push(await request.json())
        return HttpResponse.json({
          data: {
            result: 'found',
            item: {
              id: 1,
              inventory_number: 'A-1',
              expected: true,
              state: 'found',
              found_at: '2026-11-07T06:00:00Z',
              scanned_by: 'B',
              note: null,
              asset: {
                id: 1,
                inventory_number: 'A-1',
                name: 'HP ProDesk',
                category: null,
                status: 'in_use',
                location: null,
                room: null,
              },
            },
            session: { ...session, found_count: 1, missing_count: 1 },
          },
        })
      }),
    )
    renderWithProviders(<InventorySessionPage />, {
      me: makeMe({ permissions: ['assets.view', 'assets.inventory'] }),
      path: '/assets/inventory/:id',
      route: '/assets/inventory/4',
    })

    const field = await screen.findByRole('textbox', { name: i18n.t('assets:inventory.scan') })
    await userEvent.type(field, 'A-1{Enter}')

    expect(await screen.findByRole('status')).toHaveTextContent(
      i18n.t('assets:inventory.result.found', { number: 'A-1' }),
    )
    expect(scanned).toEqual([{ code: 'A-1' }])
    expect(field).toHaveValue('')
  })
})

describe('QR labels', () => {
  it('renders a QR label per selected asset', async () => {
    let query = ''
    server.use(
      http.get('*/api/v1/assets', ({ request }) => {
        query = new URL(request.url).searchParams.get('filter[id]') ?? ''
        return HttpResponse.json(page([makeAsset(), makeAsset({ id: 2, inventory_number: 'AT-000002' })]))
      }),
    )
    renderWithProviders(<LabelsPage />, {
      me: makeMe({ permissions: ['assets.view'] }),
      path: '/assets/labels',
      route: '/assets/labels?ids=1,2',
    })

    expect(await screen.findAllByTestId('asset-label')).toHaveLength(2)
    expect(query).toBe('1,2')
    await waitFor(() => expect(screen.getByRole('img', { name: 'AT-000002' }).innerHTML).toContain('<svg'))
    expect(screen.getByRole('button', { name: i18n.t('assets:labels.print') })).toBeEnabled()
  })
})

describe('QR lookup', () => {
  it('opens the card for a known inventory number and explains a miss', async () => {
    server.use(
      http.get('*/api/v1/assets/lookup/:inv', ({ params }) =>
        params.inv === 'AT-000001'
          ? HttpResponse.json({ data: makeAsset({ id: 7 }) })
          : HttpResponse.json({ message: 'Topilmadi', code: 'not_found' }, { status: 404 }),
      ),
    )
    const { router } = renderWithProviders(<LookupPage />, {
      me: makeMe({ permissions: ['assets.view'] }),
      path: '/assets/lookup/:inventory',
      route: '/assets/lookup/AT-000001',
      routes: [{ path: '/assets/:id', element: <p>card</p> }],
    })
    await waitFor(() => expect(router.state.location.pathname).toBe('/assets/7'))

    renderWithProviders(<LookupPage />, {
      me: makeMe({ permissions: ['assets.view'] }),
      path: '/assets/lookup/:inventory',
      route: '/assets/lookup/NOPE',
    })
    expect(await screen.findByText(i18n.t('assets:lookup.notFound'))).toBeInTheDocument()
  })
})
