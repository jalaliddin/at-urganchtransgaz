import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { delay, http, HttpResponse } from 'msw'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AssetsPage } from '@/features/assets/AssetsPage'
import { InventorySessionPage } from '@/features/assets/InventorySessionPage'
import { LabelsPage } from '@/features/assets/LabelsPage'
import { DocumentDetailPage } from '@/features/documents/DocumentDetailPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { catalogHandlers, makeAsset } from './testData'
import type { InventorySession } from './types'

const session: InventorySession = {
  id: 4,
  name: '2026',
  status: 'open',
  note: null,
  organization: { id: 5, name: 'Xorazm GTS' },
  include_descendants: true,
  started_by: null,
  started_at: '2026-11-07T05:00:00Z',
  closed_at: null,
  expected_count: 120,
  found_count: 0,
  missing_count: 120,
  unexpected_count: 0,
}
const scanMe = makeMe({ permissions: ['assets.view', 'assets.inventory'] })

function serveSession(itemRequests: URLSearchParams[] = []) {
  server.use(
    http.get('*/api/v1/inventory-sessions/4', () => HttpResponse.json({ data: session })),
    http.get('*/api/v1/inventory-sessions/4/items', ({ request }) => {
      itemRequests.push(new URL(request.url).searchParams)
      return HttpResponse.json({
        data: [],
        meta: { current_page: 1, per_page: 50, total: 120, last_page: 3, from: 1, to: 50 },
      })
    }),
    http.post('*/api/v1/inventory-sessions/4/scan', async () => {
      await delay(300)
      return HttpResponse.json({
        data: {
          result: 'found',
          item: {
            id: 1,
            inventory_number: 'A-1',
            expected: true,
            state: 'found',
            found_at: null,
            scanned_by: null,
            note: null,
            asset: null,
          },
          session,
        },
      })
    }),
  )
}

const renderScan = () =>
  renderWithProviders(<InventorySessionPage />, {
    me: scanMe,
    path: '/assets/inventory/:id',
    route: '/assets/inventory/4',
  })

afterEach(() => {
  delete (globalThis as { BarcodeDetector?: unknown }).BarcodeDetector
})

describe('Inventory scan screen (review fixes)', () => {
  it('focuses the scan field and clears it before the server answers', async () => {
    serveSession()
    renderScan()

    const field = await screen.findByRole('textbox', { name: i18n.t('assets:inventory.scan') })
    await waitFor(() => expect(field).toHaveFocus())
    await userEvent.type(field, 'A-1{Enter}')
    expect(field).toHaveValue('')
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
    expect(await screen.findByRole('status')).toBeInTheDocument()
  })

  it('pages through long result lists', async () => {
    const requests: URLSearchParams[] = []
    serveSession(requests)
    renderScan()

    await userEvent.click(await screen.findByRole('button', { name: '2' }))
    await waitFor(() => expect(requests.at(-1)?.get('page')).toBe('2'))
    expect(
      screen.getByRole('columnheader', { name: i18n.t('assets:fields.inventory_number') }),
    ).toBeInTheDocument()
  })

  it('opens the camera once, not on every re-render', async () => {
    serveSession()
    const getUserMedia = vi.fn().mockResolvedValue({ getTracks: () => [] })
    Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia }, configurable: true })
    ;(globalThis as { BarcodeDetector?: unknown }).BarcodeDetector = class {
      detect() {
        return Promise.resolve([])
      }
    }
    vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue(undefined)
    renderScan()

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('assets:inventory.camera') }))
    await userEvent.type(screen.getByRole('textbox', { name: i18n.t('assets:inventory.scan') }), 'ABC')
    await waitFor(() => expect(getUserMedia).toHaveBeenCalled())
    expect(getUserMedia).toHaveBeenCalledTimes(1)
  })
})

describe('QR labels beyond one page', () => {
  it('fetches every selected asset in chunks of 100', async () => {
    const chunks: number[] = []
    server.use(
      http.get('*/api/v1/assets', ({ request }) => {
        const ids = (new URL(request.url).searchParams.get('filter[id]') ?? '').split(',').map(Number)
        chunks.push(ids.length)
        return HttpResponse.json(page(ids.map((id) => makeAsset({ id, inventory_number: `AT-${id}` }))))
      }),
    )
    const ids = Array.from({ length: 120 }, (_, i) => i + 1).join(',')
    renderWithProviders(<LabelsPage />, {
      me: scanMe,
      path: '/assets/labels',
      route: `/assets/labels?ids=${ids}`,
    })

    expect(await screen.findAllByTestId('asset-label')).toHaveLength(120)
    expect(chunks.sort()).toEqual([100, 20])
  })
})

describe('Asset settings access', () => {
  it('shows the settings link to asset editors without the catalog permission', async () => {
    server.use(
      ...catalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/assets', () => HttpResponse.json(page([]))),
    )
    renderWithProviders(<AssetsPage />, {
      me: makeMe({ permissions: ['assets.view', 'assets.update'] }),
      path: '/assets',
    })

    expect(await screen.findByRole('link', { name: i18n.t('assets:actions.settings') })).toHaveAttribute(
      'href',
      '/assets/settings',
    )
  })
})

describe('Document links to assets', () => {
  it('links a document to an asset and opens asset links on the asset card', async () => {
    let body: unknown
    const doc = {
      id: 1,
      organization: { id: 5, name: 'Xorazm GTS' },
      type: 'invoice',
      number: '15',
      title: 'Hisob-faktura',
      document_date: null,
      description: null,
      current_version: null,
      versions: [],
      can_manage: true,
      created_at: null,
      updated_at: null,
      links: [{ id: 3, type: 'asset', target: { id: 9, number: 'AT-000009', title: 'Printer' } }],
    }
    server.use(
      http.get('*/api/v1/documents/1', () => HttpResponse.json({ data: doc })),
      http.get('*/api/v1/assets', () =>
        HttpResponse.json(page([makeAsset({ id: 7, inventory_number: 'AT-000007' })])),
      ),
      http.post('*/api/v1/documents/1/links', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json(
          { data: { id: 4, type: 'asset', target: { id: 7, number: 'AT-000007' } } },
          { status: 201 },
        )
      }),
    )
    renderWithProviders(<DocumentDetailPage />, {
      me: makeMe({ permissions: ['documents.view', 'documents.manage', 'assets.view', 'helpdesk.view'] }),
      path: '/documents/:id',
      route: '/documents/1',
    })

    expect(await screen.findByRole('link', { name: /AT-000009/ })).toHaveAttribute('href', '/assets/9')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('documents:actions.link') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('radio', { name: i18n.t('documents:linkTypes.asset') }))
    await userEvent.type(dialog.getByRole('combobox', { name: i18n.t('documents:linkTarget') }), 'AT-0')
    await userEvent.click(await screen.findByRole('option', { name: /AT-000007/ }))
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(body).toEqual({ type: 'asset', id: 7 }))
  })
})
