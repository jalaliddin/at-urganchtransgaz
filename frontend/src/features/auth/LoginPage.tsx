import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Anchor, Button, Checkbox, Group, PasswordInput, Stack, TextInput } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { applyApiErrors, fieldError } from '@/lib/forms'
import { AuthLayout } from '@/layouts/AuthLayout'
import { useLogin } from './queries'
import { loginSchema, type LoginValues } from './schemas'

/** Only same-site paths are accepted as a redirect target (no open redirects). */
function safeRedirect(value: string | null): string {
  return value && value.startsWith('/') && !value.startsWith('//') ? value : '/'
}

export function LoginPage() {
  const { t } = useTranslation('auth')
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const login = useLogin()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { username: '', password: '', remember: false },
  })

  const onSubmit = handleSubmit(async (values) => {
    try {
      await login.mutateAsync(values)
      navigate(safeRedirect(params.get('redirect')), { replace: true })
    } catch (error) {
      applyApiErrors(error, setError)
    }
  })

  const generalError = login.error && !Object.keys(errors).length ? login.error.message : null

  return (
    <AuthLayout title={t('login.title')}>
      <form onSubmit={onSubmit} noValidate>
        <Stack gap="md">
          {generalError && (
            <Alert color="red" variant="light">
              {generalError}
            </Alert>
          )}
          <TextInput
            label={t('login.username')}
            autoComplete="username"
            autoCapitalize="none"
            spellCheck={false}
            error={fieldError(t, errors.username)}
            {...register('username')}
          />
          <PasswordInput
            label={t('login.password')}
            autoComplete="current-password"
            error={fieldError(t, errors.password)}
            {...register('password')}
          />
          <Group justify="space-between" wrap="nowrap">
            <Checkbox label={t('login.remember')} {...register('remember')} />
            <Anchor component={Link} to="/forgot-password" size="sm">
              {t('login.forgot')}
            </Anchor>
          </Group>
          <Button type="submit" size="md" loading={login.isPending} fullWidth>
            {t('login.submit')}
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  )
}
