import type { Requisition, RequisitionAction, RequisitionRow } from './types'

const none = Object.fromEntries(
  [
    'edit',
    'delete',
    'submit',
    'org_decide',
    'take',
    'request_info',
    'provide_info',
    'decide',
    'ready',
    'allocate',
    'complete',
    'receive',
    'close',
    'comment',
  ].map((a) => [a, false]),
) as Record<RequisitionAction, boolean>

export function makeRow(overrides: Partial<RequisitionRow> = {}): RequisitionRow {
  return {
    id: 9,
    number: 'RQ-2026-00009',
    title: 'Kartrijlar va monitor',
    status: 'central_review',
    organization: { id: 6, name: 'Urganch filiali' },
    requester: { id: 4, name: 'Aziz Karimov' },
    needed_by: '2026-11-20',
    submitted_at: '2026-11-08T05:00:00Z',
    created_at: '2026-11-08T04:00:00Z',
    updated_at: '2026-11-08T05:00:00Z',
    ...overrides,
  }
}

export function makeRequisition(
  can: Partial<Record<RequisitionAction, boolean>> = {},
  overrides: Partial<Requisition> = {},
): Requisition {
  return {
    ...makeRow(),
    description: "Bo'lim uchun",
    recipient_employee: null,
    reviewer: { id: 2, name: 'Markaziy xodim' },
    approved_by: null,
    approved_at: null,
    rejection_reason: null,
    received_at: null,
    closed_at: null,
    items: [
      {
        id: 31,
        kind: 'consumable',
        name: 'HP 85A',
        material: { id: 11, code: 'CE285A', name: 'HP 85A kartrij' },
        asset_category: null,
        quantity: '4.000',
        approved_quantity: null,
        fulfilled_quantity: '0.000',
        remaining: '0.000',
        unit: { id: 1, name: 'dona' },
        note: null,
      },
      {
        id: 32,
        kind: 'equipment',
        name: 'Monitor 24"',
        material: null,
        asset_category: { id: 3, name: 'Monitor' },
        quantity: '1.000',
        approved_quantity: null,
        fulfilled_quantity: '0.000',
        remaining: '0.000',
        unit: null,
        note: null,
      },
    ],
    approvals: [],
    history: [
      {
        id: 1,
        action: 'created',
        from_status: null,
        to_status: 'draft',
        comment: null,
        payload: null,
        user: { id: 4, name: 'Aziz Karimov' },
        created_at: '2026-11-08T04:00:00Z',
      },
    ],
    comments: [],
    attachments: [],
    fulfillments: [],
    can: { ...none, comment: true, ...can },
    ...overrides,
  }
}
