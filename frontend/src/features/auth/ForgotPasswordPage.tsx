import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Anchor, Button, Stack, Text, TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { AuthLayout } from '@/layouts/AuthLayout'
import { useRequestPasswordReset } from './queries'
import { forgotPasswordSchema, type ForgotPasswordValues } from './schemas'

export function ForgotPasswordPage() {
  const { t } = useTranslation('auth')
  const request = useRequestPasswordReset()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ForgotPasswordValues>({ resolver: zodResolver(forgotPasswordSchema), defaultValues: { login: '' } })

  const onSubmit = handleSubmit(async ({ login }) => {
    try {
      await request.mutateAsync(login)
    } catch (error) {
      applyApiErrors(error, setError)
    }
  })

  return (
    <AuthLayout title={t('forgot.title')} description={t('forgot.description')}>
      {request.isSuccess ? (
        <Alert color="teal" variant="light">
          {request.data}
        </Alert>
      ) : (
        <form onSubmit={onSubmit} noValidate>
          <Stack gap="md">
            {request.error && !errors.login && (
              <Alert color="red" variant="light">
                {request.error.message}
              </Alert>
            )}
            <TextInput
              label={t('forgot.login')}
              autoComplete="username"
              autoCapitalize="none"
              error={fieldError(t, errors.login)}
              {...register('login')}
            />
            <Button type="submit" loading={request.isPending} fullWidth>
              {t('forgot.submit')}
            </Button>
          </Stack>
        </form>
      )}
      <Text size="sm" c="dimmed">
        {t('forgot.noEmail')}
      </Text>
      <Anchor component={Link} to="/login" size="sm">
        {t('forgot.back')}
      </Anchor>
    </AuthLayout>
  )
}
