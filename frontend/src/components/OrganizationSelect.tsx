import { Select, type SelectProps } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { getOrganization, listOrganizations } from '@/features/organizations/api'

interface Props extends Omit<SelectProps, 'data' | 'value' | 'onChange' | 'searchValue' | 'onSearchChange'> {
  value: number | null
  onChange: (value: number | null) => void
  /** Hide inactive organizations (for choosing where to create something). */
  onlyActive?: boolean
  /** Organization ids that may not be chosen (e.g. the node itself when picking a parent). */
  excludeIds?: number[]
}

/** Server-searched organization picker; only organizations in the user's scope are offered. */
export function OrganizationSelect({
  value,
  onChange,
  onlyActive = false,
  excludeIds = [],
  ...props
}: Props) {
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)

  const params: Record<string, string | number> = { per_page: 50, sort: 'name' }
  if (debounced.trim()) params.search = debounced.trim()
  if (onlyActive) params['filter[is_active]'] = 1

  const list = useQuery({
    queryKey: ['organizations', 'options', params],
    queryFn: () => listOrganizations(params),
    placeholderData: keepPreviousData,
    staleTime: 60_000,
  })
  // Fetch the selected organization only when the loaded options do not contain it.
  const missingSelected = value !== null && list.isSuccess && !list.data.data.some((o) => o.id === value)
  const selected = useQuery({
    queryKey: ['organizations', 'detail', value],
    queryFn: () => getOrganization(value as number),
    enabled: missingSelected,
    staleTime: 60_000,
  })

  const data = useMemo(() => {
    const options = new Map<string, string>()
    if (selected.data) options.set(String(selected.data.id), selected.data.name)
    for (const organization of list.data?.data ?? []) {
      if (!excludeIds.includes(organization.id)) options.set(String(organization.id), organization.name)
    }
    return [...options].map(([optionValue, label]) => ({ value: optionValue, label }))
  }, [list.data, selected.data, excludeIds])

  return (
    <Select
      searchable
      clearable
      data={data}
      value={value === null ? null : String(value)}
      onChange={(next) => onChange(next ? Number(next) : null)}
      onSearchChange={setSearch}
      filter={({ options }) => options}
      nothingFoundMessage={list.isFetching ? '…' : undefined}
      comboboxProps={{ withinPortal: true }}
      {...props}
    />
  )
}
