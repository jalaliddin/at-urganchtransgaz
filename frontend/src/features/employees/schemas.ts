import { z } from 'zod'
import type { EmployeeInput } from './api'

const optional = (max: number) => z.string().trim().max(max, { error: 'tooLong' })

export const EMPLOYEE_STATUSES = ['active', 'on_leave', 'business_trip', 'dismissed'] as const

export const employeeSchema = z
  .object({
    organization_id: z.number().nullable(),
    department_id: z.number().nullable(),
    last_name: z.string().trim().min(1, { error: 'required' }).max(100, { error: 'tooLong' }),
    first_name: z.string().trim().min(1, { error: 'required' }).max(100, { error: 'tooLong' }),
    middle_name: optional(100),
    position: optional(255),
    personnel_number: optional(50),
    phone: z.string().trim().regex(/^(\+?[0-9\s\-()]{5,20})?$/, { error: 'phone' }),
    internal_phone: optional(20),
    email: z.union([z.literal(''), z.email({ error: 'email' })]),
    status: z.enum(EMPLOYEE_STATUSES),
    hired_at: z.string().nullable(),
    dismissed_at: z.string().nullable(),
    notes: optional(2000),
  })
  .superRefine((value, ctx) => {
    if (value.organization_id === null) ctx.addIssue({ code: 'custom', path: ['organization_id'], message: 'required' })
    if (value.status === 'dismissed' && !value.dismissed_at) {
      ctx.addIssue({ code: 'custom', path: ['dismissed_at'], message: 'dismissedAt' })
    }
  })

export type EmployeeFormValues = z.infer<typeof employeeSchema>

const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function toEmployeeInput(values: EmployeeFormValues): EmployeeInput {
  return {
    organization_id: values.organization_id ?? undefined,
    department_id: values.department_id,
    last_name: values.last_name.trim(),
    first_name: values.first_name.trim(),
    middle_name: blankToNull(values.middle_name),
    position: blankToNull(values.position),
    personnel_number: blankToNull(values.personnel_number),
    phone: blankToNull(values.phone),
    internal_phone: blankToNull(values.internal_phone),
    email: blankToNull(values.email),
    status: values.status,
    hired_at: values.hired_at,
    dismissed_at: values.status === 'dismissed' ? values.dismissed_at : null,
    notes: blankToNull(values.notes),
  }
}
