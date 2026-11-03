import { describe, expect, it } from 'vitest'
import { visibleNav } from '@/layouts/AppLayout/navigation'
import { makeMe } from '@/test/fixtures'

describe('visibleNav', () => {
  it('shows only the dashboard to an ordinary employee', () => {
    const keys = visibleNav(makeMe({ permissions: ['dashboard.view'] })).flatMap((g) =>
      g.items.map((i) => i.key),
    )

    expect(keys).toEqual(['dashboard'])
  })

  it('drops empty groups and keeps permitted items', () => {
    const groups = visibleNav(makeMe({ permissions: ['dashboard.view', 'employees.view', 'audit.view'] }))

    expect(groups.map((g) => g.key)).toEqual(['main', 'structure', 'administration'])
    expect(groups.flatMap((g) => g.items.map((i) => i.key))).toEqual(['dashboard', 'employees', 'audit'])
  })

  it('shows everything to a super admin', () => {
    const keys = visibleNav(makeMe({ is_super_admin: true, permissions: [] })).flatMap((g) =>
      g.items.map((i) => i.key),
    )

    expect(keys).toContain('settings')
    expect(keys).toContain('roles')
  })
})
