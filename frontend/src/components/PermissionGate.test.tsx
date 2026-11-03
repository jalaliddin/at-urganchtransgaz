import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { PermissionGate } from '@/components/PermissionGate'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

describe('PermissionGate', () => {
  it('renders children when the permission is held', async () => {
    renderWithProviders(<PermissionGate permission="employees.view">ichki</PermissionGate>, { me: makeMe() })

    expect(await screen.findByText('ichki')).toBeInTheDocument()
  })

  it('renders the fallback when the permission is missing', async () => {
    renderWithProviders(
      <PermissionGate permission="users.create" fallback={<span>ruxsat yo'q</span>}>
        ichki
      </PermissionGate>,
      { me: makeMe() },
    )

    expect(await screen.findByText("ruxsat yo'q")).toBeInTheDocument()
    expect(screen.queryByText('ichki')).not.toBeInTheDocument()
  })

  it('accepts any of several permissions', async () => {
    renderWithProviders(
      <PermissionGate permission={['users.create', 'employees.view']} mode="any">
        ichki
      </PermissionGate>,
      { me: makeMe() },
    )

    expect(await screen.findByText('ichki')).toBeInTheDocument()
  })
})
