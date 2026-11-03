import { describe, expect, it } from 'vitest'
import { createUserSchema } from '@/features/users/schemas'

const valid = {
  username: 'a.karimov',
  name: 'Karimov Aziz',
  email: '',
  employee_id: null,
  organization_id: 5,
  roles: ['employee'],
}

function firstErrors(isSuperAdmin: boolean, values: object) {
  const result = createUserSchema(isSuperAdmin).safeParse(values)
  if (result.success) return {}
  const first: Record<string, string> = {}
  for (const issue of result.error.issues) first[issue.path.join('.')] ??= issue.message
  return first
}

describe('createUserSchema', () => {
  it('accepts a valid user', () => {
    expect(firstErrors(false, valid)).toEqual({})
  })

  it('rejects uppercase or spaced usernames', () => {
    expect(firstErrors(false, { ...valid, username: 'A.Karimov' })).toEqual({ username: 'username' })
    expect(firstErrors(false, { ...valid, username: 'a karimov' })).toEqual({ username: 'username' })
  })

  it('needs an organization or employee unless super admin', () => {
    expect(firstErrors(false, { ...valid, organization_id: null })).toEqual({ organization_id: 'required' })
    expect(firstErrors(false, { ...valid, organization_id: null, employee_id: 9 })).toEqual({})
    expect(firstErrors(true, { ...valid, organization_id: null })).toEqual({})
  })

  it('needs at least one role', () => {
    expect(firstErrors(false, { ...valid, roles: [] })).toEqual({ roles: 'roles' })
  })
})
