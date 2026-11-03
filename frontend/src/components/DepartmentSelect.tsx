import { Select, type SelectProps } from '@mantine/core'
import { useMemo } from 'react'
import { useDepartments } from '@/features/departments/queries'

interface Props extends Omit<SelectProps, 'data' | 'value' | 'onChange'> {
  organizationId: number | null
  value: number | null
  onChange: (value: number | null) => void
  excludeId?: number
}

/** Departments of one organization (exact, not its subtree). Disabled until an organization is chosen. */
export function DepartmentSelect({ organizationId, value, onChange, excludeId, ...props }: Props) {
  const params = { 'filter[organization]': organizationId ?? 0, per_page: 100, sort: 'name' }
  const list = useDepartments(params, organizationId !== null)

  const data = useMemo(
    () =>
      (list.data?.data ?? [])
        .filter((d) => d.organization?.id === organizationId && d.id !== excludeId)
        .map((d) => ({ value: String(d.id), label: d.name })),
    [list.data, organizationId, excludeId],
  )

  return (
    <Select
      searchable
      clearable
      data={data}
      value={value === null ? null : String(value)}
      onChange={(next) => onChange(next ? Number(next) : null)}
      disabled={organizationId === null}
      comboboxProps={{ withinPortal: true }}
      {...props}
    />
  )
}
