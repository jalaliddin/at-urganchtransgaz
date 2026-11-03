import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { TemporaryPasswordModal } from '@/features/users/TemporaryPasswordModal'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

function Harness() {
  const [password, setPassword] = useState<string | null>('Xk7pQ2mN9vRt4w')
  return <TemporaryPasswordModal password={password} username="a.karimov" onClose={() => setPassword(null)} />
}

describe('TemporaryPasswordModal', () => {
  it('shows the password once with a copy button and forgets it on close', async () => {
    renderWithProviders(<Harness />, { me: makeMe() })

    expect(await screen.findByText('Xk7pQ2mN9vRt4w')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: i18n.t('common:actions.copy') })).toBeInTheDocument()
    expect(screen.getByText(i18n.t('users:temporary.warning'))).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: i18n.t('users:temporary.done') }))

    expect(screen.queryByText('Xk7pQ2mN9vRt4w')).not.toBeInTheDocument()
  })
})
