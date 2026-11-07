import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { AssetDetailPage } from '@/features/assets/AssetDetailPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { catalogHandlers, makeAsset } from './testData'
import type { Asset } from './types'

function serve(asset: Asset, posts: [string, unknown][] = []) {
  server.use(
    ...catalogHandlers,
    http.get('*/api/v1/assets/1', () => HttpResponse.json({ data: asset })),
    http.get('*/api/v1/assets/1/:part', () => HttpResponse.json({ data: [] })),
    http.post('*/api/v1/assets/1/:action', async ({ params, request }) => {
      posts.push([String(params.action), await request.json()])
      return HttpResponse.json({ data: asset }, { status: params.action === 'disposals' ? 201 : 200 })
    }),
  )
}

function render(me = makeMe({ permissions: ['assets.view', 'assets.update', 'assets.assign'] })) {
  return renderWithProviders(<AssetDetailPage />, { me, path: '/assets/:id', route: '/assets/1' })
}

describe('AssetDetailPage', () => {
  it('shows the card read-only without permissions', async () => {
    serve(
      makeAsset({ can: { update: false, delete: false, assign: false, transfer: false, dispose: false } }),
    )
    render(makeMe({ permissions: ['assets.view'] }))

    expect(await screen.findByText('urg-pc-214')).toBeInTheDocument()
    expect(screen.getByText('Karimov Aziz')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('assets:actions.assign') })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: i18n.t('assets:actions.status') })).not.toBeInTheDocument()
  })

  it('takes an asset back with a note', async () => {
    const posts: [string, unknown][] = []
    serve(makeAsset(), posts)
    render()

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('assets:actions.return') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(dialog.getByRole('textbox'), "Ishchi joy o'zgardi")
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(posts).toContainEqual(['return', { note: "Ishchi joy o'zgardi" }]))
  })

  it('requires a reason before requesting a write-off', async () => {
    const posts: [string, unknown][] = []
    serve(makeAsset({ holder: null, status: 'in_stock' }), posts)
    render()

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('assets:actions.status') }))
    await userEvent.click(await screen.findByRole('menuitem', { name: i18n.t('assets:actions.dispose') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))
    expect(await dialog.findByText(i18n.t('validation:required'))).toBeInTheDocument()
    expect(posts).toHaveLength(0)

    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(i18n.t('assets:disposal.reason')) }),
      "Ta'mirlab bo'lmaydi",
    )
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.save') }))
    await waitFor(() => expect(posts).toContainEqual(['disposals', { reason: "Ta'mirlab bo'lmaydi" }]))
  })
})
