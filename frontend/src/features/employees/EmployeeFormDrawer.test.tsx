import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { EmployeeFormDrawer } from '@/features/employees/EmployeeFormDrawer'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler, page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

const employee = {
  id: 7,
  full_name: 'Karimov Aziz',
  last_name: 'Karimov',
  first_name: 'Aziz',
  middle_name: null,
  position: null,
  personnel_number: null,
  phone: null,
  internal_phone: null,
  email: null,
  status: 'active' as const,
  status_label: 'Faol',
  hired_at: null,
  dismissed_at: null,
  notes: null,
  organization: { id: 1, name: 'Xorazm GTS' },
  department: { id: 10, name: "AT bo'limi" },
  user: null,
  created_at: null,
  updated_at: null,
  can: { update: true, delete: true },
}

describe('EmployeeFormDrawer', () => {
  it('clears the department when the organization changes', async () => {
    server.use(
      organizationOptionsHandler,
      http.get('*/api/v1/departments', ({ request }) => {
        const org = new URL(request.url).searchParams.get('filter[organization]')
        return HttpResponse.json(
          page(
            org === '1' ? [{ id: 10, name: "AT bo'limi", organization: { id: 1, name: 'Xorazm GTS' } }] : [],
          ),
        )
      }),
    )
    renderWithProviders(<EmployeeFormDrawer opened employee={employee} onClose={() => {}} />, {
      me: makeMe(),
    })

    const department = await screen.findByRole('combobox', { name: i18n.t('employees:fields.department') })
    await waitFor(() => expect(department).toHaveValue("AT bo'limi"))

    await userEvent.click(screen.getByRole('combobox', { name: new RegExp(i18n.t('employees:fields.organization')) }))
    await userEvent.click(await screen.findByRole('option', { name: 'Urganch MGQB' }))

    await waitFor(() => expect(department).toHaveValue(''))
  })
})
