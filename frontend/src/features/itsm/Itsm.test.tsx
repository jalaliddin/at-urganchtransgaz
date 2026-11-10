import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { ChangePage } from '@/features/changes/ChangePage'
import type { Change } from '@/features/changes/types'
import { categoriesHandler, makeTicket } from '@/features/helpdesk/testData'
import { ArticleEditorPage } from '@/features/knowledge/ArticleEditorPage'
import { ArticlePage } from '@/features/knowledge/ArticlePage'
import { KbSuggestions } from '@/features/knowledge/KbSuggestions'
import type { Article } from '@/features/knowledge/types'
import { MaintenancePage } from '@/features/maintenance/MaintenancePage'
import type { WorkItem } from '@/features/maintenance/types'
import { ProblemPage } from '@/features/problems/ProblemPage'
import type { Problem } from '@/features/problems/types'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'

const tr = (ns: string) => (key: string, o?: Record<string, unknown>) => i18n.t(`${ns}:${key}`, o)

const article: Article = {
  id: 3,
  number: 'KB-00003',
  title: "Printerda qog'oz tiqilsa",
  type: 'guide',
  category: { id: 2, name: 'Printer' },
  summary: "Qog'ozni xavfsiz chiqarish",
  audience: 'all',
  status: 'published',
  published_at: '2026-11-10T05:00:00Z',
  updated_at: '2026-11-10T05:00:00Z',
  author: { id: 1, name: 'Admin' },
  views: 12,
  helpful: 4,
  not_helpful: 1,
  body: "## Qadamlar\n1. Printerni **o'chiring**",
  attachments: [],
  source_ticket: null,
  my_vote: null,
  can: { edit: false, delete: false },
}

describe('Knowledge base', () => {
  it('lets a reader vote on an article rendered from Markdown, without edit actions', async () => {
    const k = tr('knowledge')
    let body: unknown
    server.use(
      http.get('*/api/v1/kb-articles/3', () => HttpResponse.json({ data: article })),
      http.post('*/api/v1/kb-articles/3/vote', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...article, my_vote: true, helpful: 5 } })
      }),
    )
    renderWithProviders(<ArticlePage />, {
      me: makeMe({ permissions: ['kb.view'] }),
      path: '/kb/:id',
      route: '/kb/3',
    })

    expect(await screen.findByRole('heading', { name: 'Qadamlar' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: k('actions.publish') })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: `${k('vote.yes')} (4)` }))
    await waitFor(() => expect(body).toEqual({ helpful: true }))
  })

  it('starts an article from a resolved ticket and publishes it', async () => {
    const k = tr('knowledge')
    let body: Record<string, unknown> | undefined
    server.use(
      categoriesHandler,
      http.get('*/api/v1/helpdesk/tickets/7', () =>
        HttpResponse.json({
          data: makeTicket({
            id: 7,
            title: 'VPN ulanmayapti',
            description: 'Xato 809',
            resolution: 'Portlar ochildi',
          }),
        }),
      ),
      http.post('*/api/v1/kb-articles', async ({ request }) => {
        body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { ...article, id: 9 } }, { status: 201 })
      }),
    )
    renderWithProviders(<ArticleEditorPage />, {
      me: makeMe({ permissions: ['kb.view', 'kb.write'] }),
      path: '/kb/new',
      route: '/kb/new?ticket=7',
      routes: [{ path: '/kb/:id', element: <div>article page</div> }],
    })

    expect(await screen.findByDisplayValue('VPN ulanmayapti')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: k('actions.publish') }))
    await waitFor(() =>
      expect(body).toMatchObject({
        title: 'VPN ulanmayapti',
        type: 'solution',
        publish: true,
        source_ticket_id: 7,
      }),
    )
    expect(String(body?.body)).toContain('Xato 809')
    expect(String(body?.body)).toContain('Portlar ochildi')
    expect(await screen.findByText('article page')).toBeInTheDocument()
  })

  it('suggests articles for the ticket title', async () => {
    server.use(
      http.get('*/api/v1/kb-articles/suggest', ({ request }) => {
        expect(new URL(request.url).searchParams.get('category')).toBe('2')
        return HttpResponse.json({
          data: [
            { id: 3, number: 'KB-00003', title: article.title, summary: article.summary, type: 'guide' },
          ],
        })
      }),
    )
    renderWithProviders(<KbSuggestions title="Printer qog'oz olmayapti" categoryId={2} />, {
      me: makeMe({ permissions: ['kb.view'] }),
    })

    expect(await screen.findByRole('link', { name: article.title }, { timeout: 2000 })).toHaveAttribute(
      'href',
      '/kb/3',
    )
  })
})

const problem: Problem = {
  id: 5,
  number: 'PRB-2026-00005',
  title: "Printerlar qog'oz tiqadi",
  organization: { id: 6, name: 'Xiva GTS' },
  category: null,
  priority: 'high',
  status: 'investigating',
  assignee: null,
  tickets_count: 0,
  created_at: '2026-11-10T05:00:00Z',
  resolved_at: null,
  description: null,
  root_cause: null,
  workaround: null,
  resolution: null,
  close_reason: null,
  closed_at: null,
  creator: null,
  article: null,
  tickets: [],
  changes: [],
  history: [],
  can: { manage: true, transitions: ['known_error', 'resolved', 'closed'] },
}

describe('ProblemPage', () => {
  it('asks for the root cause and resolution when resolving', async () => {
    const p = tr('problems')
    let body: unknown
    server.use(
      http.get('*/api/v1/problems/5', () => HttpResponse.json({ data: problem })),
      http.post('*/api/v1/problems/5/transition', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...problem, status: 'resolved' } })
      }),
    )
    renderWithProviders(<ProblemPage />, {
      me: makeMe({ permissions: ['problems.view', 'problems.manage'] }),
      path: '/problems/:id',
      route: '/problems/5',
    })

    await userEvent.click(await screen.findByRole('button', { name: p('transition.resolved') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(p('fields.root_cause')) }),
      "Nam qog'oz",
    )
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(`^${p('fields.resolution')}`) }),
      'Ombor quruq xonaga',
    )
    await userEvent.click(dialog.getByRole('button', { name: p('transition.resolved') }))
    await waitFor(() =>
      expect(body).toMatchObject({
        status: 'resolved',
        root_cause: "Nam qog'oz",
        resolution: 'Ombor quruq xonaga',
      }),
    )
  })
})

const change: Change = {
  id: 8,
  number: 'CHG-2026-00008',
  title: 'Router proshivkasi',
  organization: { id: 6, name: 'Xiva GTS' },
  type: 'normal',
  category: 'network',
  risk: 'medium',
  status: 'pending_approval',
  planned_start: '2026-11-21T17:00:00Z',
  planned_end: '2026-11-21T18:00:00Z',
  implementer: null,
  creator: { id: 2, name: 'Staff' },
  assets_count: 1,
  description: null,
  impact: null,
  plan: '1. Zaxira',
  backout_plan: 'Qaytarish',
  actual_start: null,
  actual_end: null,
  submitted_at: '2026-11-20T05:00:00Z',
  decided_by: null,
  decided_at: null,
  decision_comment: null,
  result: null,
  problem: null,
  assets: [{ id: 4, inventory_number: 'AT-000004', name: 'MikroTik', model: null }],
  conflicts: [
    {
      id: 9,
      number: 'CHG-2026-00009',
      title: 'VLAN',
      status: 'approved',
      planned_start: '2026-11-21T17:30:00Z',
    },
  ],
  history: [],
  can: {
    edit: false,
    submit: false,
    decide: true,
    start: false,
    complete: false,
    cancel: false,
    revise: false,
    comment: true,
  },
}

describe('ChangePage', () => {
  it('shows conflicts and needs a reason to reject', async () => {
    const c = tr('changes')
    let body: unknown
    server.use(
      http.get('*/api/v1/changes/8', () => HttpResponse.json({ data: change })),
      http.post('*/api/v1/changes/8/reject', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...change, status: 'rejected' } })
      }),
    )
    renderWithProviders(<ChangePage />, {
      me: makeMe({ permissions: ['changes.view', 'changes.approve'] }),
      path: '/changes/:id',
      route: '/changes/8',
    })

    expect(await screen.findByText(c('conflicts.title'))).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: c('actions.reject') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: c('actions.reject') }))
    expect(await dialog.findByText(i18n.t('validation:required'))).toBeInTheDocument()
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(c('fields.comment')) }),
      'Ish vaqtida emas',
    )
    await userEvent.click(dialog.getByRole('button', { name: c('actions.reject') }))
    await waitFor(() => expect(body).toEqual({ comment: 'Ish vaqtida emas' }))
  })
})

const work: WorkItem = {
  id: 11,
  plan: {
    id: 2,
    title: 'UPS profilaktikasi',
    checklist: ['Akkumulyatorni tekshirish', 'Changni tozalash'],
    assignee: null,
  },
  asset: { id: 4, inventory_number: 'AT-000010', name: 'APC Smart-UPS', category: 'UPS' },
  organization: { id: 6, name: 'Xiva GTS' },
  due_on: '2026-11-25',
  status: 'planned',
  overdue: false,
  completed_on: null,
  completed_by: null,
  result: null,
  checklist: null,
  note: null,
  can: { perform: true },
}

describe('MaintenancePage', () => {
  it('records a service with each checklist step', async () => {
    const m = tr('maintenance')
    let body: unknown
    server.use(
      http.get('*/api/v1/maintenance-tasks', () => HttpResponse.json(page([work]))),
      http.post('*/api/v1/maintenance-tasks/11/complete', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { ...work, status: 'done' } })
      }),
    )
    renderWithProviders(<MaintenancePage />, {
      me: makeMe({ permissions: ['maintenance.view', 'maintenance.perform'] }),
    })

    await userEvent.click(await screen.findByRole('button', { name: m('complete.action') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('checkbox', { name: 'Changni tozalash' }))
    await userEvent.type(
      dialog.getByRole('textbox', { name: m('complete.stepNote', { step: 'Akkumulyatorni tekshirish' }) }),
      '60%',
    )
    await userEvent.click(dialog.getByRole('radio', { name: m('result.issues') }))
    await userEvent.click(dialog.getByRole('button', { name: m('complete.submit') }))
    await waitFor(() =>
      expect(body).toMatchObject({
        result: 'issues',
        checklist: [
          { done: true, note: '60%' },
          { done: false, note: null },
        ],
      }),
    )
  })
})
