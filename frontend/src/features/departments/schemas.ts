import { z } from 'zod'
import type { DepartmentInput } from './api'

export const departmentSchema = z
  .object({
    organization_id: z.number().nullable(),
    parent_id: z.number().nullable(),
    name: z.string().trim().min(1, { error: 'required' }).max(255, { error: 'tooLong' }),
    code: z.string().trim().max(50, { error: 'tooLong' }),
    phone: z
      .string()
      .trim()
      .regex(/^(\+?[0-9\s\-()]{3,50})?$/, { error: 'phone' }),
    is_active: z.boolean(),
    sort_order: z.number({ error: 'number' }).int().min(0).max(100000),
  })
  .superRefine((value, ctx) => {
    if (value.organization_id === null)
      ctx.addIssue({ code: 'custom', path: ['organization_id'], message: 'required' })
  })

export type DepartmentFormValues = z.infer<typeof departmentSchema>

export function toDepartmentInput(values: DepartmentFormValues): DepartmentInput {
  return {
    organization_id: values.organization_id ?? undefined,
    parent_id: values.parent_id,
    name: values.name.trim(),
    code: values.code.trim() || null,
    phone: values.phone.trim() || null,
    is_active: values.is_active,
    sort_order: values.sort_order,
  }
}
