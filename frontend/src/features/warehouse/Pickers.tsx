import { Select, type SelectProps } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { useMemo, useState } from 'react'
import { useMaterials, useWarehouses } from './queries'
import type { Material } from './types'

type Base = Omit<SelectProps, 'data' | 'value' | 'onChange' | 'searchValue' | 'onSearchChange'>

/** Server-searched material picker (name or code). */
export function MaterialSelect({
  value,
  onChange,
  initial,
  ...props
}: Base & {
  value: number | null
  onChange: (material: Material | null) => void
  /** Already chosen material, so its label shows before any search. */
  initial?: Pick<Material, 'id' | 'code' | 'name'> | null
}) {
  const [search, setSearch] = useState('')
  const [debounced] = useDebouncedValue(search, 300)
  const params: Record<string, string | number> = { per_page: 30, 'filter[active]': 1 }
  if (debounced.trim()) params.search = debounced.trim()
  const list = useMaterials(params)
  const rows = useMemo(() => list.data?.data ?? [], [list.data])
  const data = useMemo(() => {
    const options = rows.map((m) => ({ value: String(m.id), label: `${m.code} — ${m.name}` }))
    if (initial && !rows.some((m) => m.id === initial.id))
      options.unshift({ value: String(initial.id), label: `${initial.code} — ${initial.name}` })
    return options
  }, [rows, initial])

  return (
    <Select
      searchable
      clearable
      filter={({ options }) => options}
      searchValue={search}
      onSearchChange={setSearch}
      data={data}
      value={value ? String(value) : null}
      onChange={(v) => onChange(rows.find((m) => String(m.id) === v) ?? null)}
      {...props}
    />
  )
}

/** Warehouses in the user's organizations (active by default). */
export function WarehouseSelect({
  value,
  onChange,
  excludeId,
  organizationId,
  ...props
}: Base & {
  value: number | null
  onChange: (id: number | null) => void
  excludeId?: number | null
  organizationId?: number | null
}) {
  const list = useWarehouses({ 'filter[active]': 1 })
  const data = (list.data ?? [])
    .filter((w) => w.id !== excludeId && (!organizationId || w.organization.id === organizationId))
    .map((w) => ({ value: String(w.id), label: `${w.name} — ${w.organization.name}` }))

  return (
    <Select
      searchable
      data={data}
      value={value ? String(value) : null}
      onChange={(v) => onChange(v ? Number(v) : null)}
      {...props}
    />
  )
}
