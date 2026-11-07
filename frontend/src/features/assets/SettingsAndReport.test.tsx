import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { AssetReportPage } from '@/features/assets/AssetReportPage'
import { AssetSettingsPage } from '@/features/assets/AssetSettingsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { catalogHandlers } from './testData'

describe('Asset settings', () => {
  it('adds a manufacturer', async () => {
    let body: unknown
    server.use(
      ...catalogHandlers,
      http.post('*/api/v1/manufacturers', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { id: 2, name: 'Dell', website: null } }, { status: 201 })
      }),
    )
    renderWithProviders(<AssetSettingsPage />, {
      me: makeMe({ permissions: ['assets.manage_catalog', 'assets.update', 'assets.view'] }),
    })

    await userEvent.click(
      await screen.findByRole('tab', { name: i18n.t('assets:settings.tabs.manufacturers') }),
    )
    await userEvent.click(await screen.findByRole('button', { name: i18n.t('assets:settings.add') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(i18n.t('assets:settings.name')) }),
      'Dell',
    )
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(body).toEqual({ name: 'Dell', website: null }))
  })

  it('shows only locations to users without the catalog permission', async () => {
    server.use(...catalogHandlers)
    renderWithProviders(<AssetSettingsPage />, {
      me: makeMe({ permissions: ['assets.update', 'assets.view'] }),
    })

    expect(
      await screen.findByRole('tab', { name: i18n.t('assets:settings.tabs.locations') }),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('tab', { name: i18n.t('assets:settings.tabs.categories') }),
    ).not.toBeInTheDocument()
  })
})

describe('Asset report', () => {
  it('shows register figures from the API', async () => {
    server.use(
      organizationOptionsHandler,
      http.get('*/api/v1/assets/stats', () =>
        HttpResponse.json({
          data: {
            total: 4,
            active: 3,
            by_status: { in_stock: 1, in_use: 1, in_repair: 1, reserve: 0, written_off: 1, lost: 0 },
            by_category: [{ id: 2, code: 'computer', name: 'Kompyuter', total: 2 }],
            by_organization: [{ id: 1, name: 'Urganch GTS', total: 3, in_use: 1, in_repair: 1 }],
            total_cost: '4000.50',
            warranty_expiring: 1,
            warranty_expired: 0,
          },
        }),
      ),
    )
    renderWithProviders(<AssetReportPage />, { me: makeMe({ permissions: ['assets.view'] }) })

    expect(await screen.findByText('Urganch GTS')).toBeInTheDocument()
    expect(screen.getAllByText(i18n.t('assets:report.active')).length).toBeGreaterThan(0)
    expect(screen.getByRole('columnheader', { name: i18n.t('assets:report.inRepair') })).toBeInTheDocument()
  })
})
