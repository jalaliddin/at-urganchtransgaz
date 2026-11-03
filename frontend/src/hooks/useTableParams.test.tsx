import { act, screen } from '@testing-library/react'
import { useEffect } from 'react'
import { describe, expect, it } from 'vitest'
import { useTableParams, type TableParams } from '@/hooks/useTableParams'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

let captured: TableParams | null = null

function Probe() {
  const params = useTableParams({ defaultSort: 'last_name', filterKeys: ['status', 'organization'] })
  useEffect(() => {
    captured = params
  })
  return <div>{JSON.stringify(params.apiParams)}</div>
}

describe('useTableParams', () => {
  it('reads state from the URL and builds API params', async () => {
    renderWithProviders(<Probe />, {
      me: makeMe(),
      route: '/?page=2&per_page=50&sort=-name&search=karim&filter[status]=active',
    })

    await screen.findByText(/"page":2/)
    expect(captured?.apiParams).toEqual({
      page: 2,
      per_page: 50,
      sort: '-name',
      search: 'karim',
      'filter[status]': 'active',
    })
    expect(captured?.sortStatus).toEqual({ columnAccessor: 'name', direction: 'desc' })
  })

  it('uses defaults and resets to page 1 when a filter changes', async () => {
    const { router } = renderWithProviders(<Probe />, { me: makeMe(), route: '/?page=3' })
    await screen.findByText(/"page":3/)

    act(() => captured?.setFilter('organization', '7'))

    await screen.findByText(/"page":1/)
    expect(router.state.location.search).toContain('filter%5Borganization%5D=7')
    expect(captured?.apiParams.sort).toBe('last_name')
    expect(captured?.apiParams.per_page).toBe(25)
  })
})
