import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { LoginPage } from '@/features/auth/LoginPage'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

function renderLogin() {
  return renderWithProviders(<LoginPage />, {
    path: '/login',
    route: '/login?redirect=/organizations',
    routes: [{ path: '/organizations', element: <div>Tashkilotlar sahifasi</div> }],
  })
}

describe('LoginPage', () => {
  it('shows required errors in Uzbek on empty submit', async () => {
    renderLogin()

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('auth:login.submit') }))

    expect(await screen.findAllByText(i18n.t('validation:required'))).toHaveLength(2)
  })

  it('shows the server credential error under the username field', async () => {
    server.use(
      http.post('*/api/v1/auth/login', () =>
        HttpResponse.json(
          { message: 'x', code: 'validation_error', errors: { username: ["Login yoki parol noto'g'ri."] } },
          { status: 422 },
        ),
      ),
    )
    renderLogin()

    await userEvent.type(await screen.findByLabelText(i18n.t('auth:login.username')), 'a.karimov')
    await userEvent.type(screen.getByLabelText(i18n.t('auth:login.password')), 'wrong-pass1')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('auth:login.submit') }))

    expect(await screen.findByText("Login yoki parol noto'g'ri.")).toBeInTheDocument()
  })

  it('navigates to the redirect target after signing in', async () => {
    server.use(http.post('*/api/v1/auth/login', () => HttpResponse.json({ data: makeMe() })))
    renderLogin()

    await userEvent.type(await screen.findByLabelText(i18n.t('auth:login.username')), 'a.karimov')
    await userEvent.type(screen.getByLabelText(i18n.t('auth:login.password')), 'Secret12345')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('auth:login.submit') }))

    await waitFor(() => expect(screen.getByText('Tashkilotlar sahifasi')).toBeInTheDocument())
  })

  it('shows the organization name from public settings', async () => {
    renderLogin()

    expect(await screen.findByText('"Urganchtransgaz" MCHJ')).toBeInTheDocument()
  })
})
