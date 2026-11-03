import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useEffect, useState } from 'react'
import { describe, expect, it } from 'vitest'
import { PermissionMatrix } from '@/features/roles/PermissionMatrix'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

const modules = [
  {
    module: 'employees',
    permissions: ['employees.view', 'employees.create', 'employees.update', 'employees.delete'],
  },
  { module: 'audit', permissions: ['audit.view'] },
]

let latest: string[] = []

function Harness() {
  const [value, setValue] = useState<string[]>(['employees.view'])
  useEffect(() => {
    latest = value
  })
  return <PermissionMatrix modules={modules} value={value} onChange={setValue} />
}

describe('PermissionMatrix', () => {
  it('selects every permission of a module from its header checkbox', async () => {
    renderWithProviders(<Harness />, { me: makeMe() })

    const header = await screen.findByRole('checkbox', { name: i18n.t('permissions:modules.employees') })
    expect(header).toHaveAttribute('data-indeterminate', 'true')

    await userEvent.click(header)

    await waitFor(() => expect(latest).toHaveLength(4))
    expect([...latest].sort()).toEqual([
      'employees.create',
      'employees.delete',
      'employees.update',
      'employees.view',
    ])
    await userEvent.click(header)
    await waitFor(() => expect(latest).toEqual([]))
  })

  it('toggles a single permission', async () => {
    renderWithProviders(<Harness />, { me: makeMe() })

    await userEvent.click(
      await screen.findByRole('checkbox', {
        name: `${i18n.t('permissions:modules.audit')}: ${i18n.t('permissions:actions.view')}`,
      }),
    )

    await waitFor(() => expect(latest).toContain('audit.view'))
  })
})
