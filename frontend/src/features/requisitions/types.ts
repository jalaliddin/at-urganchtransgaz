export type RequisitionStatus =
  | 'draft'
  | 'submitted'
  | 'org_approval'
  | 'central_review'
  | 'info_requested'
  | 'approved'
  | 'rejected'
  | 'ready_for_allocation'
  | 'partially_fulfilled'
  | 'fulfilled'
  | 'received'
  | 'closed'

export const REQUISITION_STATUSES: RequisitionStatus[] = [
  'draft',
  'submitted',
  'org_approval',
  'central_review',
  'info_requested',
  'approved',
  'rejected',
  'ready_for_allocation',
  'partially_fulfilled',
  'fulfilled',
  'received',
  'closed',
]

export type ItemKind =
  'equipment' | 'network' | 'consumable' | 'sim' | 'internet' | 'software' | 'repair' | 'other'
export const ITEM_KINDS: ItemKind[] = [
  'equipment',
  'network',
  'consumable',
  'sim',
  'internet',
  'software',
  'repair',
  'other',
]

export type Decision = 'approved' | 'rejected' | 'returned'
export type FulfillmentType = 'stock' | 'asset' | 'note' | 'sim'

export type RequisitionAction =
  | 'edit'
  | 'delete'
  | 'submit'
  | 'org_decide'
  | 'take'
  | 'request_info'
  | 'provide_info'
  | 'decide'
  | 'ready'
  | 'allocate'
  | 'complete'
  | 'receive'
  | 'close'
  | 'comment'

type Ref = { id: number; name: string }

export interface RequisitionRow {
  id: number
  number: string
  title: string
  status: RequisitionStatus
  organization: Ref
  requester: Ref | null
  needed_by: string | null
  submitted_at: string | null
  created_at: string
  updated_at: string
}

export interface RequisitionItem {
  id: number
  kind: ItemKind
  name: string
  material: { id: number; code: string; name: string } | null
  asset_category: Ref | null
  quantity: string
  approved_quantity: string | null
  fulfilled_quantity: string
  remaining: string
  unit: Ref | null
  note: string | null
}

export interface Requisition extends RequisitionRow {
  description: string | null
  recipient_employee: Ref | null
  reviewer: Ref | null
  approved_by: Ref | null
  approved_at: string | null
  rejection_reason: string | null
  received_at: string | null
  closed_at: string | null
  items: RequisitionItem[]
  approvals: {
    id: number
    stage: 'org_head' | 'central_head'
    decision: Decision
    comment: string | null
    user: Ref | null
    created_at: string
  }[]
  history: {
    id: number
    action: string
    from_status: RequisitionStatus | null
    to_status: RequisitionStatus | null
    comment: string | null
    payload: Record<string, unknown> | null
    user: Ref | null
    created_at: string
  }[]
  comments: { id: number; body: string; user: Ref | null; created_at: string }[]
  attachments: {
    id: number
    file: { uuid: string; name: string; mime: string; size: number }
    uploaded_by: Ref | null
    created_at: string
  }[]
  fulfillments: {
    id: number
    item_id: number
    type: FulfillmentType
    quantity: string
    note: string | null
    stock_document: { id: number; number: string; type: string } | null
    asset: { id: number; inventory_number: string; name: string } | null
    asset_transfer: { id: number; number: string; status: string } | null
    sim_card: { id: number; phone_number: string } | null
    user: Ref | null
    created_at: string
  }[]
  can: Record<RequisitionAction, boolean>
}

export interface RequisitionItemInput {
  kind: ItemKind
  material_id?: number | null
  asset_category_id?: number | null
  name: string
  quantity: string
  unit_id?: number | null
  note?: string | null
}

export interface RequisitionInput {
  organization_id?: number
  title: string
  description?: string | null
  needed_by?: string | null
  recipient_employee_id?: number | null
  items: RequisitionItemInput[]
}
