import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { AssetsPage } from '@/features/assets/AssetsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { catalogHandlers, makeAsset } from './testData'

const me = makeMe({ permissions: ['assets.view', 'assets.create', 'assets.export'] })

describe('AssetsPage', () => {
  it('filters through the URL and sends selected assets to the label page', async () => {
    const requests: URLSearchParams[] = []
    server.use(
      ...catalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/assets', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(
          page([
            makeAsset(),
            makeAsset({ id: 2, inventory_number: 'AT-000002', holder: null, status: 'in_stock' }),
          ]),
        )
      }),
    )
    const { router } = renderWithProviders(<AssetsPage />, {
      me,
      path: '/assets',
      routes: [{ path: '/assets/labels', element: <p>labels</p> }],
    })

    expect(await screen.findByText('AT-000002')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('checkbox', { name: i18n.t('assets:filters.unassigned') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[holder]')).toBe('none'))

    await userEvent.click(
      screen.getByRole('checkbox', { name: `${i18n.t('assets:actions.selectToLabel')}: AT-000001` }),
    )
    await userEvent.click(
      screen.getByRole('checkbox', { name: `${i18n.t('assets:actions.selectToLabel')}: AT-000002` }),
    )
    await userEvent.click(screen.getByRole('button', { name: new RegExp(i18n.t('assets:actions.labels')) }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/assets/labels'))
    expect(router.state.location.search).toBe('?ids=1,2')
  })

  it('validates and creates an asset', async () => {
    let sent: Record<string, unknown> | undefined
    server.use(
      ...catalogHandlers,
      organizationOptionsHandler,
      http.get('*/api/v1/assets', () => HttpResponse.json(page([]))),
      http.post('*/api/v1/assets', async ({ request }) => {
        sent = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: makeAsset({ id: 7 }) }, { status: 201 })
      }),
    )
    const { router } = renderWithProviders(<AssetsPage />, {
      me: makeMe({
        permissions: ['assets.view', 'assets.create'],
        organization: { id: 5, name: 'Xorazm GTS' },
      }),
      path: '/assets',
      routes: [{ path: '/assets/:id', element: <p>card</p> }],
    })

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('assets:actions.create') }))
    const drawer = within(await screen.findByRole('dialog'))
    await userEvent.type(drawer.getByRole('textbox', { name: /^Nomi/ }), 'Dell OptiPlex 7090')
    await userEvent.click(drawer.getByRole('combobox', { name: /Toifa/ }))
    await userEvent.click(await screen.findByRole('option', { name: 'Kompyuter, monoblok, noutbuk' }))
    await userEvent.type(drawer.getByRole('textbox', { name: 'IP manzil' }), '10.20.1.999')
    await userEvent.click(drawer.getByRole('button', { name: i18n.t('common:actions.save') }))
    expect(await screen.findByText(i18n.t('validation:ip'))).toBeInTheDocument()

    await userEvent.clear(drawer.getByRole('textbox', { name: 'IP manzil' }))
    await userEvent.type(drawer.getByRole('textbox', { name: 'IP manzil' }), '10.20.1.99')
    await userEvent.click(drawer.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(router.state.location.pathname).toBe('/assets/7'))
    expect(sent).toMatchObject({
      organization_id: 5,
      name: 'Dell OptiPlex 7090',
      category_id: 2,
      ip_address: '10.20.1.99',
      hostname: null,
      cost: null,
    })
  })
})
