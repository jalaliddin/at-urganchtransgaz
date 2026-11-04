import { z } from 'zod'
import type { TicketInput } from './api'
import { TICKET_PRIORITIES } from './types'

export const ticketSchema = z
  .object({
    organization_id: z.number().nullable(),
    requester_employee_id: z.number().nullable(),
    category_id: z.number().nullable(),
    priority: z.enum(TICKET_PRIORITIES),
    title: z.string().trim().min(5, { error: 'titleMin' }).max(200, { error: 'tooLong' }),
    description: z.string().trim().min(10, { error: 'descriptionMin' }).max(5000, { error: 'tooLong' }),
    location: z.string().trim().max(150, { error: 'tooLong' }),
    contact_phone: z
      .string()
      .trim()
      .regex(/^([0-9+()\-\s]{5,30})?$/, { error: 'phone' }),
    contact_email: z.union([z.literal(''), z.email({ error: 'email' })]),
  })
  .superRefine((value, ctx) => {
    if (value.category_id === null)
      ctx.addIssue({ code: 'custom', path: ['category_id'], message: 'required' })
    if (value.organization_id === null)
      ctx.addIssue({ code: 'custom', path: ['organization_id'], message: 'required' })
  })

export type TicketFormValues = z.infer<typeof ticketSchema>

const blankToNull = (value: string) => (value.trim() === '' ? null : value.trim())

export function toTicketInput(values: TicketFormValues): TicketInput {
  return {
    organization_id: values.organization_id,
    requester_employee_id: values.requester_employee_id,
    category_id: values.category_id ?? 0,
    priority: values.priority,
    title: values.title.trim(),
    description: values.description.trim(),
    location: blankToNull(values.location),
    contact_phone: blankToNull(values.contact_phone),
    contact_email: blankToNull(values.contact_email),
  }
}
