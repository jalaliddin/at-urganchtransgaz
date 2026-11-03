import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { EmployeesPage } from '@/features/employees/EmployeesPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

describe('EmployeesPage', () => {
  it('sends search and status filter to the API and keeps them in the URL', async () => {
    const requests: URLSearchParams[] = []
    server.use(
      organizationOptionsHandler,
      http.get('*/api/v1/employees', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(
          page([
            {
              id: 7,
              full_name: 'Karimov Aziz',
              last_name: 'Karimov',
              first_name: 'Aziz',
              middle_name: null,
              position: 'Muhandis',
              personnel_number: '00412',
              phone: null,
              internal_phone: null,
              email: null,
              status: 'active',
              status_label: 'Faol',
              hired_at: null,
              dismissed_at: null,
              notes: null,
              organization: { id: 1, name: 'Xorazm GTS' },
              department: null,
              user: null,
              created_at: null,
              updated_at: null,
              can: { update: true, delete: true },
            },
          ]),
        )
      }),
    )
    const { router } = renderWithProviders(<EmployeesPage />, { me: makeMe(), path: '/employees' })

    expect(await screen.findByText('Karimov Aziz')).toBeInTheDocument()
    await userEvent.type(screen.getByRole('textbox', { name: i18n.t('employees:filters.search') }), 'karim')
    await waitFor(() => expect(requests.at(-1)?.get('search')).toBe('karim'))

    await userEvent.click(screen.getByRole('combobox', { name: i18n.t('employees:filters.status') }))
    await userEvent.click(await screen.findByRole('option', { name: i18n.t('common:status.dismissed') }))

    await waitFor(() => expect(requests.at(-1)?.get('filter[status]')).toBe('dismissed'))
    expect(router.state.location.search).toContain('search=karim')
    expect(decodeURIComponent(router.state.location.search)).toContain('filter[status]=dismissed')
  })
})
