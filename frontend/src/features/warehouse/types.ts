export type StockDocumentType = 'receipt' | 'issue' | 'return' | 'transfer' | 'adjustment'
export type StockCountStatus = 'open' | 'closed' | 'cancelled'

export const DOCUMENT_TYPES: StockDocumentType[] = ['receipt', 'issue', 'return', 'transfer', 'adjustment']

/** Permission needed to post each type (mirrors StockDocumentType::permission()). */
export const DOCUMENT_PERMISSIONS: Record<StockDocumentType, string> = {
  receipt: 'warehouse.receive',
  issue: 'warehouse.issue',
  return: 'warehouse.receive',
  transfer: 'warehouse.transfer',
  adjustment: 'warehouse.manage',
}

export interface Unit {
  id: number
  code: string
  name: string
  name_ru: string | null
  label: string
  is_active: boolean
  sort_order: number
}

export interface MaterialCategory {
  id: number
  code: string | null
  name: string
  name_ru: string | null
  label: string
  is_active: boolean
  sort_order: number
}

export interface Material {
  id: number
  code: string
  name: string
  category: { id: number; name: string }
  unit: { id: number; name: string }
  track_batches: boolean
  track_expiry: boolean
  min_quantity: string
  description: string | null
  is_active: boolean
}

export interface MaterialRef {
  id: number
  code: string
  name: string
  unit: string
  track_batches: boolean
  track_expiry: boolean
}

export interface Warehouse {
  id: number
  name: string
  code: string | null
  description: string | null
  is_active: boolean
  organization: { id: number; name: string }
  location: { id: number; name: string } | null
  responsible: { id: number; name: string } | null
}

export interface Lot {
  id: number
  batch: string | null
  expires_at: string | null
  quantity: string
  unit_cost: string
  received_at: string | null
}

export interface BalanceRow {
  id: string
  warehouse: { id: number; name: string; organization: { id: number; name: string } }
  material: MaterialRef & { category: string }
  quantity: string
  value: string
  min_quantity: string
  is_low: boolean
  nearest_expiry: string | null
  lots: Lot[]
}

export interface Availability {
  total: string
  lots: Lot[]
}

type Ref = { id: number; name: string }

export interface StockDocument {
  id: number
  number: string
  type: StockDocumentType
  document_date: string
  organization: Ref | null
  warehouse: Ref
  target_warehouse: (Ref & { organization: Ref }) | null
  supplier: string | null
  supplier_document: string | null
  recipient_organization: Ref | null
  recipient_employee: Ref | null
  asset: { id: number; inventory_number: string; name: string } | null
  reason: string | null
  related_document: { id: number; number: string; type: StockDocumentType } | null
  requisition: { id: number; number: string } | null
  stock_count_id: number | null
  file: { uuid: string; name: string; mime: string; size: number } | null
  total_amount: string
  created_by: Ref | null
  created_at: string
  lines_count?: number
}

export interface StockDocumentLine {
  id: number
  material: MaterialRef
  batch: string | null
  expires_at: string | null
  quantity: string
  unit_cost: string | null
  amount: string
  note: string | null
}

export interface StockMovementRow {
  id: number
  warehouse: Ref
  material_id: number
  batch: string | null
  quantity: string
  unit_cost: string
  balance_after: string
}

export interface StockDocumentDetail extends StockDocument {
  lines: StockDocumentLine[]
  movements: StockMovementRow[]
  returns: { id: number; number: string; document_date: string }[]
  can: { attach_file: boolean }
}

export interface JournalRow {
  id: number
  created_at: string
  document: { id: number; number: string; type: StockDocumentType; document_date: string }
  warehouse: Ref
  material: MaterialRef
  batch: string | null
  quantity: string
  unit_cost: string
  balance_after: string
  user: Ref | null
}

export interface StockCount {
  id: number
  number: string
  status: StockCountStatus
  note: string | null
  warehouse: Ref
  started_by: Ref | null
  started_at: string | null
  closed_at: string | null
  progress: { total: number; counted: number }
}

export interface StockCountLine {
  id: number
  material: MaterialRef
  batch: string | null
  expires_at: string | null
  expected_quantity: string
  counted_quantity: string | null
  difference: string | null
}

export interface StockCountDetail extends StockCount {
  closed_by: Ref | null
  adjustment_document: { id: number; number: string } | null
  lines: StockCountLine[]
  can: { manage: boolean }
}

export interface Shortage {
  material_id: number
  material: string
  batch: string | null
  available: string
  requested: string
}

export interface StockLineInput {
  material_id: number
  quantity: string
  batch?: string | null
  expires_at?: string | null
  unit_cost?: string | null
  note?: string | null
}

export interface StockDocumentInput {
  type: StockDocumentType
  warehouse_id: number
  target_warehouse_id?: number | null
  document_date?: string | null
  supplier?: string | null
  supplier_document?: string | null
  recipient_organization_id?: number | null
  recipient_employee_id?: number | null
  asset_id?: number | null
  reason?: string | null
  related_document_id?: number | null
  lines: StockLineInput[]
}
