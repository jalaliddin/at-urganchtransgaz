import type { AssetStatus } from '@/features/assets/types'

interface Ref {
  id: number
  name: string
}

export const PRINTER_CONNECTIONS = ['usb', 'network', 'wifi', 'shared'] as const
export type PrinterConnection = (typeof PRINTER_CONNECTIONS)[number]
export const CARTRIDGE_KINDS = ['toner', 'ink', 'drum', 'ribbon', 'other'] as const
export type CartridgeKind = (typeof CARTRIDGE_KINDS)[number]
export const CARTRIDGE_COLORS = ['black', 'cyan', 'magenta', 'yellow', 'tricolor', 'other'] as const
export type CartridgeColor = (typeof CARTRIDGE_COLORS)[number]
export type ReplacementSource = 'warehouse' | 'other'
export const REPORT_GROUPS = ['organization', 'cartridge', 'printer'] as const
export type ReportGroup = (typeof REPORT_GROUPS)[number]

export interface PrinterModelRef {
  id: number
  name: string
  manufacturer: string | null
}

export interface Cartridge {
  id: number
  name: string
  part_number: string | null
  kind: CartridgeKind
  color: CartridgeColor
  page_yield: number | null
  is_active: boolean
  material: { id: number; code: string; name: string; unit: string | null } | null
  printer_models: PrinterModelRef[]
  /** Stock in the warehouses the user can see. */
  stock: string | null
  can_delete: boolean | null
}

export interface WarehouseStock {
  warehouse: { id: number; name: string; organization: string }
  quantity: string
}

export interface CartridgeOption extends Cartridge {
  warehouses: WarehouseStock[]
}

export interface PrinterRow {
  id: number
  inventory_number: string
  name: string
  model: PrinterModelRef | null
  serial_number: string | null
  ip_address: string | null
  hostname: string | null
  status: AssetStatus
  organization: Ref
  location: Ref | null
  room: string | null
  holder: { id: number; full_name: string } | null
  connection_type: PrinterConnection | null
  is_color: boolean
  note: string | null
  last_counter: { read_at: string; total: number } | null
  last_replacement: { replaced_at: string; cartridge: string } | null
}

export interface Maintenance {
  id: number
  status: 'open' | 'closed'
  started_at: string
  finished_at: string | null
  vendor: string | null
  description: string
  result: string | null
  cost: string | null
  ticket: { id: number; number: string } | null
}

export interface PrinterDetail extends PrinterRow {
  department: Ref | null
  /** False when the printer's model has no compatibility list: every active cartridge is offered. */
  compatibility_defined: boolean
  cartridges: CartridgeOption[]
  monthly_pages: { month: string; pages: number | null }[]
  maintenances: Maintenance[]
  can: { service: boolean; issue_from_warehouse: boolean; view_asset: boolean }
}

export interface CounterReading {
  id: number
  read_at: string
  total: number
  is_reset: boolean
  /** Pages since the previous reading; null for the first one. */
  pages: number | null
  source: 'manual' | 'replacement'
  replacement_id: number | null
  note: string | null
  created_by: Ref | null
  created_at: string
  can_delete: boolean
}

export interface Replacement {
  id: number
  replaced_at: string
  printer: { id: number; inventory_number: string; name: string }
  organization: Ref
  cartridge: {
    id: number
    name: string
    part_number: string | null
    color: CartridgeColor
    page_yield: number | null
  }
  source: ReplacementSource
  warehouse: Ref | null
  stock_document: { id: number; number: string } | null
  cost: string | null
  counter: { id: number; total: number } | null
  pages_since_previous: number | null
  note: string | null
  performed_by: Ref | null
  created_at: string
  cancelled_at: string | null
  cancelled_by: Ref | null
  cancel_reason: string | null
  return_document: { id: number; number: string } | null
  can_cancel: boolean
}

export interface ConsumptionRow {
  key: { id: number; name: string; code: string | null }
  replacements: number
  cost: string
  pages: number | null
  cost_per_page: string | null
}

export interface ConsumptionReport {
  period: { from: string; to: string }
  group_by: ReportGroup
  rows: ConsumptionRow[]
  total: { replacements: number; cost: string; pages: number; cost_per_page: string | null }
  monthly: { month: string; replacements: number; cost: string; pages: number | null }[]
}
