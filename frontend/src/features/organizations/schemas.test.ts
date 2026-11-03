import { describe, expect, it } from 'vitest'
import { organizationSchema } from '@/features/organizations/schemas'

const valid = {
  parent_id: 1,
  type: 'subsidiary' as const,
  name: 'Xiva filiali',
  short_name: '',
  code: 'XIVA-1',
  tin: '',
  address: '',
  phone: '',
  email: '',
  head_name: '',
  is_active: true,
  sort_order: 0,
}

function messages(isSuperAdmin: boolean, values: object) {
  const result = organizationSchema(isSuperAdmin).safeParse(values)
  if (result.success) return {}
  // Like react-hook-form's resolver: the first issue per field is the one shown.
  const first: Record<string, string> = {}
  for (const issue of result.error.issues) first[issue.path.join('.')] ??= issue.message
  return first
}

describe('organizationSchema', () => {
  it('accepts a valid organization', () => {
    expect(messages(false, valid)).toEqual({})
  })

  it('rejects a TIN that is not 9 digits', () => {
    expect(messages(false, { ...valid, tin: '12345678' })).toEqual({ tin: 'tin' })
    expect(messages(false, { ...valid, tin: '123456789' })).toEqual({})
  })

  it('requires a parent unless the user is a super admin', () => {
    expect(messages(false, { ...valid, parent_id: null })).toEqual({ parent_id: 'required' })
    expect(messages(true, { ...valid, parent_id: null })).toEqual({})
  })

  it('requires a code with safe characters', () => {
    expect(messages(false, { ...valid, code: '' })).toEqual({ code: 'required' })
    expect(messages(false, { ...valid, code: 'bad code!' })).toEqual({ code: 'code' })
  })

  it('validates optional email and phone', () => {
    expect(messages(false, { ...valid, email: 'not-mail', phone: 'call' })).toEqual({ email: 'email', phone: 'phone' })
  })
})
