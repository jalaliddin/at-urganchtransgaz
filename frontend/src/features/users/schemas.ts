import { z } from 'zod'

const email = z.union([z.literal(''), z.email({ error: 'email' })])

export function createUserSchema(isSuperAdmin: boolean) {
  return z
    .object({
      username: z
        .string()
        .trim()
        .regex(/^[a-z0-9._-]{3,64}$/, { error: 'username' }),
      name: z.string().trim().min(1, { error: 'required' }).max(255, { error: 'tooLong' }),
      email,
      employee_id: z.number().nullable(),
      organization_id: z.number().nullable(),
      roles: z.array(z.string()).min(1, { error: 'roles' }),
    })
    .superRefine((value, ctx) => {
      // Accounts without an organization are system accounts: Super Admin only.
      if (!isSuperAdmin && value.organization_id === null && value.employee_id === null) {
        ctx.addIssue({ code: 'custom', path: ['organization_id'], message: 'required' })
      }
    })
}

export type CreateUserValues = z.infer<ReturnType<typeof createUserSchema>>

export const updateUserSchema = z.object({
  name: z.string().trim().min(1, { error: 'required' }).max(255, { error: 'tooLong' }),
  email,
  locale: z.enum(['uz', 'ru']),
  employee_id: z.number().nullable(),
  organization_id: z.number().nullable(),
})

export type UpdateUserValues = z.infer<typeof updateUserSchema>
