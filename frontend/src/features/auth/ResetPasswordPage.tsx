import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Anchor, Button, PasswordInput, Stack } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { ApiError } from '@/api/errors'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { AuthLayout } from '@/layouts/AuthLayout'
import { useResetPassword } from './queries'
import { resetPasswordSchema, type ResetPasswordValues } from './schemas'

/** Target of the e-mailed link: /reset-password?token=…&email=… */
export function ResetPasswordPage() {
  const { t } = useTranslation('auth')
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const reset = useResetPassword()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: '', password_confirmation: '' },
  })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await reset.mutateAsync({ token, email, ...values })
    } catch (error) {
      applyApiErrors(error, setError)
    }
  })

  // Token/email problems come back as an "email" error, which has no input on this page.
  const linkError = reset.error instanceof ApiError ? (reset.error.fieldErrors.email?.[0] ?? null) : null

  return (
    <AuthLayout title={t('reset.title')}>
      {!token || !email ? (
        <Alert color="red" variant="light">
          {t('reset.invalidLink')}
        </Alert>
      ) : reset.isSuccess ? (
        <Alert color="teal" variant="light">
          {t('reset.success')}
        </Alert>
      ) : (
        <form onSubmit={onSubmit} noValidate>
          <Stack gap="md">
            {linkError && (
              <Alert color="red" variant="light">
                {linkError}
              </Alert>
            )}
            <PasswordInput
              label={t('reset.password')}
              autoComplete="new-password"
              error={fieldError(t, errors.password)}
              {...register('password')}
            />
            <PasswordInput
              label={t('reset.confirm')}
              autoComplete="new-password"
              error={fieldError(t, errors.password_confirmation)}
              {...register('password_confirmation')}
            />
            <Button type="submit" loading={reset.isPending} fullWidth>
              {t('reset.submit')}
            </Button>
          </Stack>
        </form>
      )}
      <Anchor component={Link} to="/login" size="sm">
        {t('forgot.back')}
      </Anchor>
    </AuthLayout>
  )
}
