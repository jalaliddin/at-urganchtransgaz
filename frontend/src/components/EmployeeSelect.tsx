import { Select, type SelectProps } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { listEmployees } from '@/features/employees/api'

interface Props extends Omit<SelectProps, 'data' | 'value' | 'onChange' | 'searchValue' | 'onSearchChange'> {
  value: number | null
  onChange: (value: number | null) => void
  /** Limit to this organization's subtree. */
  organizationId?: number | null
}

/** Server-searched employee picker (name, personnel number or phone); scoped by the API. */
export function EmployeeSelect({ value, onChange, organizationId, ...props }: Props) {
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const params: Record<string, string | number> = {
    per_page: 30,
    'filter[status]': 'active,on_leave,business_trip',
  }
  if (debounced.trim()) params.search = debounced.trim()
  if (organizationId) params['filter[organization]'] = organizationId

  const list = useQuery({
    queryKey: ['employees', 'options', params],
    queryFn: () => listEmployees(params),
    placeholderData: keepPreviousData,
  })

  const data = useMemo(
    () =>
      (list.data?.data ?? []).map((e) => ({
        value: String(e.id),
        label: [e.full_name, e.organization?.name].filter(Boolean).join(' — '),
      })),
    [list.data],
  )

  return (
    <Select
      searchable
      clearable
      data={data}
      value={value === null ? null : String(value)}
      onChange={(next) => onChange(next ? Number(next) : null)}
      onSearchChange={setSearch}
      filter={({ options }) => options}
      comboboxProps={{ withinPortal: true }}
      {...props}
    />
  )
}
