import { z } from 'zod'
import type { OrganizationInput } from './api'

const optional = (max: number) => z.string().trim().max(max, { error: 'tooLong' })

/** Messages are keys of the "validation" namespace. */
/** `isCreate`: a new organization needs a parent unless a Super Admin creates a root. */
export function organizationSchema(isSuperAdmin: boolean, isCreate = true) {
  return z
    .object({
      parent_id: z.number().nullable(),
      type: z.enum(['head_office', 'subsidiary', 'branch', 'other']),
      name: z.string().trim().min(1, { error: 'required' }).max(255, { error: 'tooLong' }),
      short_name: optional(100),
      code: z
        .string()
        .trim()
        .min(1, { error: 'required' })
        .max(50, { error: 'tooLong' })
        .regex(/^[A-Za-z0-9._-]+$/, { error: 'code' }),
      tin: z
        .string()
        .trim()
        .regex(/^(\d{9})?$/, { error: 'tin' }),
      address: optional(500),
      phone: z
        .string()
        .trim()
        .regex(/^(\+?[0-9\s\-()]{5,50})?$/, { error: 'phone' }),
      email: z.union([z.literal(''), z.email({ error: 'email' })]),
      head_name: optional(255),
      is_active: z.boolean(),
      sort_order: z.number({ error: 'number' }).int().min(0).max(100000),
    })
    .superRefine((value, ctx) => {
      // Only unrestricted users may create a new root (the API enforces the same rule).
      if (isCreate && !isSuperAdmin && value.parent_id === null) {
        ctx.addIssue({ code: 'custom', path: ['parent_id'], message: 'required' })
      }
    })
}

export type OrganizationFormValues = z.infer<ReturnType<typeof organizationSchema>>

const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function toOrganizationInput(values: OrganizationFormValues): OrganizationInput {
  return {
    parent_id: values.parent_id,
    type: values.type,
    name: values.name.trim(),
    short_name: blankToNull(values.short_name),
    code: values.code.trim(),
    tin: blankToNull(values.tin),
    address: blankToNull(values.address),
    phone: blankToNull(values.phone),
    email: blankToNull(values.email),
    head_name: blankToNull(values.head_name),
    is_active: values.is_active,
    sort_order: values.sort_order,
  }
}
