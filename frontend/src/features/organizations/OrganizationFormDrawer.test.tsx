import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it, vi } from 'vitest'
import { OrganizationFormDrawer } from '@/features/organizations/OrganizationFormDrawer'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

describe('OrganizationFormDrawer', () => {
  it('maps a 422 code error onto the code field', async () => {
    server.use(
      http.get('*/api/v1/organizations', () =>
        HttpResponse.json({
          data: [],
          meta: { current_page: 1, per_page: 50, total: 0, last_page: 1, from: null, to: null },
        }),
      ),
      http.post('*/api/v1/organizations', () =>
        HttpResponse.json(
          { message: 'x', code: 'validation_error', errors: { code: ['Bunday kodi allaqachon mavjud.'] } },
          { status: 422 },
        ),
      ),
    )
    const onClose = vi.fn()
    renderWithProviders(<OrganizationFormDrawer opened onClose={onClose} />, {
      me: makeMe({ is_super_admin: true }),
    })

    await userEvent.type(
      await screen.findByLabelText(new RegExp(i18n.t('organizations:fields.name'))),
      'Xiva filiali',
    )
    await userEvent.type(screen.getByLabelText(new RegExp(i18n.t('organizations:fields.code'))), 'XIVA-1')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('common:actions.save') }))

    expect(await screen.findByText('Bunday kodi allaqachon mavjud.')).toBeInTheDocument()
    expect(onClose).not.toHaveBeenCalled()
  })
})
