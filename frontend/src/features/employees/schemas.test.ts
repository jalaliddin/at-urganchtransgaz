import { describe, expect, it } from 'vitest'
import { employeeSchema } from '@/features/employees/schemas'

const valid = {
  organization_id: 1,
  department_id: null,
  last_name: 'Karimov',
  first_name: 'Aziz',
  middle_name: '',
  position: '',
  personnel_number: '',
  phone: '+998 90 123-45-67',
  internal_phone: '',
  email: '',
  status: 'active' as const,
  hired_at: null,
  dismissed_at: null,
  notes: '',
}

function firstErrors(values: object) {
  const result = employeeSchema.safeParse(values)
  if (result.success) return {}
  const first: Record<string, string> = {}
  for (const issue of result.error.issues) first[issue.path.join('.')] ??= issue.message
  return first
}

describe('employeeSchema', () => {
  it('accepts a valid employee', () => {
    expect(firstErrors(valid)).toEqual({})
  })

  it('requires dismissed_at when the status is dismissed', () => {
    expect(firstErrors({ ...valid, status: 'dismissed' })).toEqual({ dismissed_at: 'dismissedAt' })
    expect(firstErrors({ ...valid, status: 'dismissed', dismissed_at: '2026-11-02' })).toEqual({})
  })

  it('rejects a malformed phone', () => {
    expect(firstErrors({ ...valid, phone: 'call me' })).toEqual({ phone: 'phone' })
  })

  it('requires organization and names', () => {
    expect(firstErrors({ ...valid, organization_id: null, last_name: ' ', first_name: '' })).toEqual({
      organization_id: 'required',
      last_name: 'required',
      first_name: 'required',
    })
  })
})
