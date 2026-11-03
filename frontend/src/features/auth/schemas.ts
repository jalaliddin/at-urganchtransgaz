import { z } from 'zod'

// Messages are keys of the "validation" i18n namespace (see lib/forms.ts fieldError()).
const required = z.string().trim().min(1, { error: 'required' })

export const loginSchema = z.object({
  username: required.max(64, { error: 'tooLong' }),
  password: z.string().min(1, { error: 'required' }),
  remember: z.boolean(),
})
export type LoginValues = z.infer<typeof loginSchema>

export const forgotPasswordSchema = z.object({ login: required.max(255, { error: 'tooLong' }) })
export type ForgotPasswordValues = z.infer<typeof forgotPasswordSchema>

const newPassword = {
  password: z.string().min(1, { error: 'required' }).max(255, { error: 'tooLong' }),
  password_confirmation: z.string().min(1, { error: 'required' }),
}

export const resetPasswordSchema = z
  .object(newPassword)
  .refine((v) => v.password === v.password_confirmation, { error: 'passwordMismatch', path: ['password_confirmation'] })
export type ResetPasswordValues = z.infer<typeof resetPasswordSchema>

export const changePasswordSchema = z
  .object({ current_password: z.string().min(1, { error: 'required' }), ...newPassword })
  .refine((v) => v.password === v.password_confirmation, { error: 'passwordMismatch', path: ['password_confirmation'] })
export type ChangePasswordValues = z.infer<typeof changePasswordSchema>
