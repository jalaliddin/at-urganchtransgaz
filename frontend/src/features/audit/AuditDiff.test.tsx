import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { AuditDiff } from '@/features/audit/AuditDiff'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

describe('AuditDiff', () => {
  it('lists every changed field with old and new values', async () => {
    renderWithProviders(
      <AuditDiff
        oldValues={{ position: 'Muhandis', roles: ['employee'] }}
        newValues={{ position: 'Bosh muhandis', roles: ['employee', 'auditor'], is_active: false }}
      />,
      { me: makeMe() },
    )

    const position = (await screen.findByText('position')).closest('tr') as HTMLElement
    expect(within(position).getByText('Muhandis')).toBeInTheDocument()
    expect(within(position).getByText('Bosh muhandis')).toBeInTheDocument()

    const roles = screen.getByText('roles').closest('tr') as HTMLElement
    expect(within(roles).getByText('employee, auditor')).toBeInTheDocument()

    const active = screen.getByText('is_active').closest('tr') as HTMLElement
    expect(within(active).getByText('—')).toBeInTheDocument()
    expect(within(active).getByText('false')).toBeInTheDocument()
  })

  it('says so when there is nothing to compare', async () => {
    renderWithProviders(<AuditDiff oldValues={null} newValues={null} />, { me: makeMe() })

    expect(await screen.findByText(/o'zgarish/i)).toBeInTheDocument()
  })
})
