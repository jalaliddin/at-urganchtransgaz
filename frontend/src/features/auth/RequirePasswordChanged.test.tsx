import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { RequirePasswordChanged } from '@/features/auth/RequirePasswordChanged'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

const routes = [
  { path: '/change-password', element: <div>Parolni almashtirish</div> },
]

describe('RequirePasswordChanged', () => {
  it('redirects to /change-password when a change is required', async () => {
    renderWithProviders(<RequirePasswordChanged />, { me: makeMe({ must_change_password: true }), routes })

    expect(await screen.findByText('Parolni almashtirish')).toBeInTheDocument()
  })

  it('redirects when the password expired', async () => {
    renderWithProviders(<RequirePasswordChanged />, { me: makeMe({ password_expired: true }), routes })

    expect(await screen.findByText('Parolni almashtirish')).toBeInTheDocument()
  })

  it('renders the protected content otherwise', async () => {
    renderWithProviders(<RequirePasswordChanged />, {
      me: makeMe(),
      routes,
      path: '/',
    })

    expect(screen.queryByText('Parolni almashtirish')).not.toBeInTheDocument()
  })
})
