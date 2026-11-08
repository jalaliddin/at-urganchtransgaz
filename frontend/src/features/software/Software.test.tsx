import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { makeMe } from '@/test/fixtures'
import { page } from '@/test/handlers'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { AssetSoftwareTab } from './AssetSoftwareTab'
import { CompliancePage } from './CompliancePage'
import { ImportPage } from './ImportPage'
import { LicensesPage } from './LicensesPage'
import type { DeviceSoftware, License } from './types'

const ts = (key: string, options?: Record<string, unknown>) => i18n.t(`software:${key}`, options)

const device: DeviceSoftware = {
  installations: [
    {
      id: 1,
      name: 'AnyDesk',
      vendor: null,
      version: '8.0',
      software: null,
      source: 'agent',
      license: null,
      installed_at: null,
      last_seen_at: '2026-11-08T05:00:00Z',
    },
  ],
  antivirus: {
    effective: 'updated',
    state: 'installed',
    product: 'Kaspersky Endpoint Security',
    software_id: 3,
    definitions_updated_at: '2026-11-08T03:00:00Z',
    last_scan_at: null,
    reported_at: '2026-11-08T04:00:00Z',
    source: 'agent',
  },
  inventoried_at: '2026-11-08T04:00:00Z',
  agent: null,
}

const license: License = {
  id: 4,
  number: 'L-2026-01',
  software: { id: 2, name: 'Microsoft Office 2021' },
  organization: { id: 5, name: 'Xorazm GTS' },
  license_key_masked: '••••••••2345',
  has_key: true,
  seats: 10,
  used: 3,
  free: 7,
  valid_from: null,
  expires_at: '2026-12-01',
  supplier: null,
  cost: null,
  note: null,
}

describe('AssetSoftwareTab', () => {
  it('shows antivirus and software, registers an agent and shows its token once', async () => {
    let added: unknown
    server.use(
      http.get('*/api/v1/assets/9/software', () => HttpResponse.json({ data: device })),
      http.get('*/api/v1/software', () => HttpResponse.json(page([]))),
      http.post('*/api/v1/assets/9/agent', () =>
        HttpResponse.json(
          {
            data: {
              id: 1,
              name: 'Agent',
              platform: null,
              agent_version: null,
              is_active: true,
              last_checkin_at: null,
              last_ip: null,
              token: 'atg_secretvalue123',
            },
          },
          { status: 201 },
        ),
      ),
      http.post('*/api/v1/assets/9/software', async ({ request }) => {
        added = await request.json()
        return HttpResponse.json({ data: device.installations[0] }, { status: 201 })
      }),
    )
    renderWithProviders(<AssetSoftwareTab assetId={9} />, {
      me: makeMe({ permissions: ['software.view', 'software.install', 'software.agents'] }),
    })

    expect(await screen.findByText(ts('antivirus.status.updated'))).toBeInTheDocument()
    expect(screen.getByText('AnyDesk')).toBeInTheDocument()
    expect(screen.getByText(ts('installations.unrecognized'))).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: ts('agents.register') }))
    expect(within(await screen.findByRole('dialog')).getByTestId('agent-token')).toHaveTextContent(
      'atg_secretvalue123',
    )
    await userEvent.keyboard('{Escape}')

    await userEvent.click(screen.getByRole('button', { name: ts('installations.add') }))
    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.type(
      dialog.getByRole('textbox', { name: new RegExp(ts('installations.otherName')) }),
      '1C Buxgalteriya',
    )
    await userEvent.click(dialog.getByRole('button', { name: i18n.t('common:actions.add') }))
    await waitFor(() => expect(added).toMatchObject({ name: '1C Buxgalteriya', software_id: null }))
  })
})

describe('LicensesPage', () => {
  it('shows only the masked key until a manager reveals it', async () => {
    server.use(
      http.get('*/api/v1/software-licenses', () => HttpResponse.json(page([license]))),
      http.post('*/api/v1/software-licenses/4/reveal', () =>
        HttpResponse.json({ data: { license_key: 'ABCDE-12345' } }),
      ),
    )
    renderWithProviders(<LicensesPage />, {
      me: makeMe({ permissions: ['software.view', 'software.manage'] }),
      path: '/software/licenses',
    })

    expect(await screen.findByText('••••••••2345')).toBeInTheDocument()
    expect(screen.queryByText('ABCDE-12345')).not.toBeInTheDocument()
    await userEvent.click(
      screen.getByRole('button', { name: `${ts('licenses.reveal')}: Microsoft Office 2021` }),
    )
    expect(within(await screen.findByRole('dialog')).getByTestId('license-key')).toHaveTextContent(
      'ABCDE-12345',
    )
  })
})

describe('CompliancePage', () => {
  it('shows counts per organization and filters problem devices through the URL', async () => {
    const requests: URLSearchParams[] = []
    server.use(
      http.get('*/api/v1/software-compliance', () =>
        HttpResponse.json({
          data: {
            organizations: [
              {
                organization: { id: 5, name: 'Xorazm GTS' },
                devices: 12,
                antivirus: { updated: 9, installed: 0, outdated: 1, not_installed: 1, unknown: 1 },
                protected: 10,
                missing_required: 3,
                inventoried: 8,
              },
            ],
          },
        }),
      ),
      http.get('*/api/v1/software-compliance/devices', ({ request }) => {
        requests.push(new URL(request.url).searchParams)
        return HttpResponse.json(
          page([
            {
              id: 7,
              inventory_number: 'AT-000007',
              name: 'PC-7',
              organization: { id: 5, name: 'Xorazm GTS' },
              antivirus: 'not_installed',
              product: null,
              missing: ['Microsoft Office'],
              inventoried_at: null,
            },
          ]),
        )
      }),
    )
    renderWithProviders(<CompliancePage />, {
      me: makeMe({ permissions: ['software.view'] }),
      path: '/software/compliance',
    })

    expect(await screen.findByText('12')).toBeInTheDocument()
    expect(await screen.findByText('AT-000007')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('radio', { name: ts('compliance.problems.unprotected') }))
    await waitFor(() => expect(requests.at(-1)?.get('filter[problem]')).toBe('unprotected'))
  })
})

describe('ImportPage', () => {
  it('uploads a CSV and lists the lines that failed', async () => {
    server.use(
      http.post('*/api/v1/software-imports', () =>
        HttpResponse.json({
          data: { total: 3, imported: 2, errors: [{ line: 4, message: 'Inventar raqami topilmadi' }] },
        }),
      ),
    )
    renderWithProviders(<ImportPage />, { me: makeMe({ permissions: ['software.install'] }) })

    const input = document.querySelector<HTMLInputElement>('input[type="file"]')
    if (!input) throw new Error('file input missing')
    await userEvent.upload(
      input,
      new File(['inventory_number,name\nAT-1,X\n'], 'a.csv', { type: 'text/csv' }),
    )
    await userEvent.click(screen.getByRole('button', { name: ts('import.run') }))
    expect(await screen.findByText(ts('import.result', { imported: 2, total: 3 }))).toBeInTheDocument()
    expect(screen.getByText('Inventar raqami topilmadi')).toBeInTheDocument()
  })
})
