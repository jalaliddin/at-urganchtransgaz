import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

const profile = {
  name: 'Karimov Aziz',
  username: 'a.karimov',
  organization: { id: 5, name: 'Xorazm GTS' },
  roles: ['Oddiy xodim'],
}

describe('DashboardPage', () => {
  it('renders only the widgets returned by the API', async () => {
    server.use(
      http.get('*/api/v1/dashboard', () =>
        HttpResponse.json({
          data: {
            profile,
            organizations: { total: 4, active: 3 },
            employees: { total: 32, by_status: { active: 28, on_leave: 3, business_trip: 1, dismissed: 0 } },
          },
        }),
      ),
    )
    renderWithProviders(<DashboardPage />, { me: makeMe() })

    expect(await screen.findByText(i18n.t('dashboard:organizations.title'))).toBeInTheDocument()
    expect(screen.getByText(i18n.t('dashboard:employees.title'))).toBeInTheDocument()
    expect(screen.getByText('32')).toBeInTheDocument()
    expect(screen.queryByText(i18n.t('dashboard:users.title'))).not.toBeInTheDocument()
    expect(screen.queryByText(i18n.t('dashboard:audit.title'))).not.toBeInTheDocument()
  })

  it('shows the profile card for an ordinary employee', async () => {
    server.use(http.get('*/api/v1/dashboard', () => HttpResponse.json({ data: { profile } })))
    renderWithProviders(<DashboardPage />, { me: makeMe({ permissions: ['dashboard.view'] }) })

    expect(await screen.findByText('Oddiy xodim')).toBeInTheDocument()
    expect(screen.getByText('Xorazm GTS')).toBeInTheDocument()
    expect(screen.queryByText(i18n.t('dashboard:organizations.title'))).not.toBeInTheDocument()
  })

  it('warns when organizations have no IT responsible', async () => {
    server.use(
      http.get('*/api/v1/dashboard', () =>
        HttpResponse.json({
          data: { profile, it_coverage: { with_responsible: 2, without_responsible: 1 } },
        }),
      ),
    )
    renderWithProviders(<DashboardPage />, { me: makeMe() })

    expect(await screen.findByText(i18n.t('dashboard:coverage.missing', { count: 1 }))).toBeInTheDocument()
  })
})
