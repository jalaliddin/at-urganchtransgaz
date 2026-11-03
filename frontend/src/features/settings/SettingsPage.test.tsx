import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { SettingsPage } from '@/features/settings/SettingsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

const settings = [
  { key: 'general.system_name', type: 'string', value: 'AT.urtg.uz', default: 'AT.urtg.uz', public: true },
  { key: 'general.support_phone', type: 'string', value: null, default: null, public: true },
  { key: 'security.password_min_length', type: 'integer', value: 10, default: 10, public: false },
]

describe('SettingsPage', () => {
  it('submits only the values that changed', async () => {
    let body: unknown = null
    server.use(
      http.get('*/api/v1/settings', () => HttpResponse.json({ data: settings })),
      http.put('*/api/v1/settings', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: settings })
      }),
    )
    renderWithProviders(<SettingsPage />, { me: makeMe({ is_super_admin: true }) })

    const phone = await screen.findByLabelText(i18n.t('settings:keys.general.support_phone.label'))
    await userEvent.type(phone, '+998 62 224-00-00')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(body).toEqual({ values: { 'general.support_phone': '+998 62 224-00-00' } }))
  })

  it('is read-only without settings.update', async () => {
    server.use(http.get('*/api/v1/settings', () => HttpResponse.json({ data: settings })))
    renderWithProviders(<SettingsPage />, { me: makeMe({ permissions: ['settings.view'] }) })

    expect(await screen.findByLabelText(i18n.t('settings:keys.general.system_name.label'))).toBeDisabled()
    expect(screen.queryByRole('button', { name: i18n.t('common:actions.save') })).not.toBeInTheDocument()
  })
})
