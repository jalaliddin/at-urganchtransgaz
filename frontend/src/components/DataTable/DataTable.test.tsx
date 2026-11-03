import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/errors'
import { DataTable } from '@/components/DataTable'
import { useTableParams } from '@/hooks/useTableParams'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { renderWithProviders } from '@/test/utils'

interface Row {
  id: number
  name: string
}

function Harness(props: { records?: Row[]; isLoading?: boolean; error?: unknown; onRetry?: () => void }) {
  const params = useTableParams({ defaultSort: 'name' })
  return (
    <DataTable<Row>
      storeKey="test-table"
      params={params}
      records={props.records}
      meta={
        props.records
          ? { current_page: 1, per_page: 25, total: props.records.length, last_page: 1, from: 1, to: 1 }
          : undefined
      }
      isLoading={props.isLoading ?? false}
      error={props.error}
      onRetry={props.onRetry ?? (() => {})}
      emptyTitle="Xodimlar topilmadi"
      columns={[{ accessor: 'name', title: 'Nomi', sortable: true }]}
    />
  )
}

describe('DataTable', () => {
  it('shows a skeleton while the first page loads', async () => {
    renderWithProviders(<Harness isLoading />, { me: makeMe() })

    expect(await screen.findByTestId('table-skeleton')).toBeInTheDocument()
  })

  it('shows the empty state when there are no records', async () => {
    renderWithProviders(<Harness records={[]} />, { me: makeMe() })

    expect(await screen.findByText('Xodimlar topilmadi')).toBeInTheDocument()
  })

  it('renders rows', async () => {
    renderWithProviders(<Harness records={[{ id: 1, name: 'Karimov Aziz' }]} />, { me: makeMe() })

    expect(await screen.findByText('Karimov Aziz')).toBeInTheDocument()
  })

  it('shows the error with a working retry button', async () => {
    const onRetry = vi.fn()
    const error = new ApiError({
      status: 500,
      code: 'server_error',
      message: 'Tizimda xatolik',
      requestId: 'abc-123',
    })
    renderWithProviders(<Harness error={error} onRetry={onRetry} />, { me: makeMe() })

    expect(await screen.findByText('Tizimda xatolik')).toBeInTheDocument()
    expect(screen.getByText(i18n.t('common:errors.requestId', { id: 'abc-123' }))).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: i18n.t('common:actions.retry') }))
    expect(onRetry).toHaveBeenCalledTimes(1)
  })
})
