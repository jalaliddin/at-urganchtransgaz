import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { DocumentDetailPage } from '@/features/documents/DocumentDetailPage'
import { pollInterval, POLL_MS } from '@/features/exports/ExportsPage'
import type { ReportExport } from '@/features/exports/api'
import { HelpdeskReportPage } from '@/features/helpdesk/HelpdeskReportPage'
import { HelpdeskSettingsPage } from '@/features/helpdesk/HelpdeskSettingsPage'
import { NotificationPreferences } from '@/features/profile/NotificationPreferences'
import { SettingsPage } from '@/features/settings/SettingsPage'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { organizationOptionsHandler } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { categoriesHandler } from './testData'

const calendar = {
  work_days: [1, 2, 3, 4, 5],
  work_start: '09:00',
  work_end: '18:00',
  lunch_start: '13:00',
  lunch_end: '14:00',
  holidays: ['2026-12-31'],
  auto_close_days: 3,
  reopen_days: 7,
  due_soon_minutes: 60,
  timezone: 'Asia/Tashkent',
}

describe('Helpdesk settings', () => {
  it('saves the business calendar without the read-only time zone', async () => {
    let saved: unknown
    server.use(
      categoriesHandler,
      http.get('*/api/v1/helpdesk/calendar', () => HttpResponse.json({ data: calendar })),
      http.put('*/api/v1/helpdesk/calendar', async ({ request }) => {
        saved = await request.json()
        return HttpResponse.json({ data: calendar })
      }),
    )
    renderWithProviders(<HelpdeskSettingsPage />, { me: makeMe({ permissions: ['helpdesk.manage'] }) })

    await userEvent.click(await screen.findByRole('tab', { name: i18n.t('helpdesk:settings.tabs.calendar') }))
    await userEvent.click(await screen.findByRole('checkbox', { name: 'Sh' }))
    await userEvent.click(screen.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() =>
      expect(saved).toEqual({
        work_days: [1, 2, 3, 4, 5, 6],
        work_start: '09:00',
        work_end: '18:00',
        lunch_start: '13:00',
        lunch_end: '14:00',
        holidays: ['2026-12-31'],
        auto_close_days: 3,
        reopen_days: 7,
        due_soon_minutes: 60,
      }),
    )
  })
})

describe('Helpdesk report', () => {
  it('shows SLA compliance and organization figures from the API', async () => {
    server.use(
      categoriesHandler,
      organizationOptionsHandler,
      http.get('*/api/v1/helpdesk/stats', () =>
        HttpResponse.json({
          data: {
            total: 6,
            open: 2,
            overdue: 1,
            by_status: {
              new: 1,
              in_review: 0,
              assigned: 0,
              in_progress: 1,
              waiting_user: 0,
              resolved: 2,
              closed: 1,
              rejected: 1,
            },
            by_priority: { low: 0, normal: 3, high: 0, critical: 3 },
            by_category: [{ id: 2, name: 'Printer', total: 5 }],
            by_organization: [{ id: 1, name: 'Urganch filiali', total: 4, open: 1, overdue: 1 }],
            sla: { response_met: 2, response_total: 3, resolution_met: 2, resolution_total: 3 },
            avg_resolution_minutes: 200,
          },
        }),
      ),
    )
    renderWithProviders(<HelpdeskReportPage />, { me: makeMe({ permissions: ['helpdesk.view'] }) })

    expect(await screen.findByText('Urganch filiali')).toBeInTheDocument()
    expect(screen.getAllByText('67%')).toHaveLength(2)
    expect(screen.getByText(i18n.t('helpdesk:sla.hours', { count: 3 }))).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: i18n.t('helpdesk:reports.table') }))
    expect(await screen.findByRole('cell', { name: 'Printer' })).toBeInTheDocument()
  })
})

describe('Document versions', () => {
  it('uploads a new version as multipart', async () => {
    let uploaded: FormData | undefined
    const doc = {
      id: 1,
      organization: { id: 5, name: 'Xorazm GTS' },
      type: 'contract',
      number: 'SH-15',
      title: 'Internet shartnomasi',
      document_date: '2026-10-01',
      description: null,
      current_version: null,
      versions: [],
      links: [],
      can_manage: true,
      created_at: null,
      updated_at: null,
    }
    server.use(
      http.get('*/api/v1/documents/1', () => HttpResponse.json({ data: doc })),
      http.post('*/api/v1/documents/1/versions', async ({ request }) => {
        uploaded = await request.formData()
        return HttpResponse.json(
          {
            data: {
              id: 3,
              version: 2,
              note: 'Ilova',
              file: { uuid: 'f', name: 'v2.pdf', mime: 'application/pdf', size: 3 },
              uploaded_by: null,
              created_at: null,
            },
          },
          { status: 201 },
        )
      }),
    )
    renderWithProviders(<DocumentDetailPage />, {
      me: makeMe({ permissions: ['documents.view', 'documents.manage'] }),
      path: '/documents/:id',
      route: '/documents/1',
    })

    await userEvent.click(await screen.findByRole('button', { name: i18n.t('documents:actions.newVersion') }))
    const input = document.querySelector<HTMLInputElement>('.mantine-Modal-content input[type="file"]')
    if (!input) throw new Error('file input missing')
    await userEvent.upload(input, new File(['%PDF'], 'v2.pdf', { type: 'application/pdf' }))
    await userEvent.type(screen.getByRole('textbox', { name: i18n.t('documents:fields.note') }), 'Ilova')
    await userEvent.click(screen.getByRole('button', { name: i18n.t('common:actions.save') }))

    await waitFor(() => expect(uploaded?.get('note')).toBe('Ilova'))
    expect(uploaded?.get('file')).toBeTruthy()
  })
})

describe('Exports polling', () => {
  const row = (status: ReportExport['status']) => ({ status }) as ReportExport

  it('polls only while an export is still being generated', () => {
    expect(pollInterval([row('done'), row('pending')])).toBe(POLL_MS)
    expect(pollInterval([row('processing')])).toBe(POLL_MS)
    expect(pollInterval([row('done'), row('failed')])).toBe(false)
    expect(pollInterval(undefined)).toBe(false)
  })
})

describe('Notification preferences', () => {
  it('mutes a type for e-mail', async () => {
    let body: unknown
    server.use(
      http.patch('*/api/v1/auth/me', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({
          data: makeMe({ notification_preferences: { mail_muted: ['ticket_commented'] } }),
        })
      }),
    )
    renderWithProviders(<NotificationPreferences me={makeMe()} />, { me: makeMe() })

    await userEvent.click(
      screen.getByRole('checkbox', { name: i18n.t('profile:notifications.types.ticket_commented') }),
    )

    await waitFor(() =>
      expect(body).toEqual({ notification_preferences: { mail_muted: ['ticket_commented'] } }),
    )
  })
})

describe('General settings', () => {
  it('hides Helpdesk calendar keys and renders boolean settings as switches', async () => {
    server.use(
      http.get('*/api/v1/settings', () =>
        HttpResponse.json({
          data: [
            {
              key: 'general.system_name',
              type: 'string',
              value: 'AT.urtg.uz',
              default: 'AT.urtg.uz',
              public: true,
            },
            {
              key: 'helpdesk.work_days',
              type: 'array',
              value: [1, 2, 3, 4, 5],
              default: [1, 2, 3, 4, 5],
              public: false,
            },
            { key: 'notifications.mail_enabled', type: 'boolean', value: true, default: true, public: false },
          ],
        }),
      ),
    )
    renderWithProviders(<SettingsPage />, {
      me: makeMe({ permissions: ['settings.view', 'settings.update'] }),
    })

    expect(
      await screen.findByRole('switch', {
        name: new RegExp(i18n.t('settings:keys.notifications.mail_enabled.label')),
      }),
    ).toBeChecked()
    expect(screen.queryByText('helpdesk.work_days')).not.toBeInTheDocument()
  })
})
