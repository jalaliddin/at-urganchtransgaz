import type { Ref } from '@/api/types'

export const ASSET_STATUSES = ['in_stock', 'in_use', 'in_repair', 'reserve', 'written_off', 'lost'] as const
export type AssetStatus = (typeof ASSET_STATUSES)[number]
export const MANUAL_STATUSES: AssetStatus[] = ['in_stock', 'reserve', 'lost']

export const RELATION_TYPES = ['connected_to', 'installed_in', 'part_of', 'depends_on'] as const
export type RelationType = (typeof RELATION_TYPES)[number]

export interface Asset {
  id: number
  inventory_number: string
  name: string
  status: AssetStatus
  category: { id: number; name: string; is_networked: boolean } | null
  manufacturer: Ref | null
  model: Ref | null
  organization: Ref | null
  department: Ref | null
  serial_number: string | null
  hostname: string | null
  ip_address: string | null
  mac_address: string | null
  vlan: string | null
  subnet: string | null
  holder: { id: number; name: string; status: string } | null
  responsible: Ref | null
  location: Ref | null
  room: string | null
  purchase_date: string | null
  warranty_until: string | null
  warranty_state: 'ok' | 'expiring' | 'expired' | null
  cost: string | null
  supplier: string | null
  specs: string | null
  notes: string | null
  in_transfer: boolean
  created_at: string | null
  updated_at: string | null
  can: { update: boolean; delete: boolean; assign: boolean; transfer: boolean; dispose: boolean } | null
}

export interface AssetCategory {
  id: number
  code: string | null
  name: string
  name_ru: string | null
  label: string
  is_networked: boolean
  is_active: boolean
  sort_order: number
}

export interface Manufacturer {
  id: number
  name: string
  website: string | null
}

export interface AssetModel {
  id: number
  name: string
  specs: string | null
  manufacturer: Ref
  category: Ref
}

export interface Location {
  id: number
  name: string
  full_name: string
  code: string | null
  description: string | null
  is_active: boolean
  organization: Ref | null
  parent: Ref | null
}

export interface AssetEvent {
  id: number
  action: string
  user: Ref | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  note: string | null
  created_at: string
}

export interface AssetAssignment {
  id: number
  employee: Ref | null
  assigned_at: string
  assigned_by: string | null
  note: string | null
  returned_at: string | null
  return_note: string | null
}

export interface AssetRelation {
  id: number
  type: RelationType
  direction: 'outgoing' | 'incoming'
  note: string | null
  asset: {
    id: number
    inventory_number: string
    name: string
    category: string | null
    hostname: string | null
    ip_address: string | null
    status: AssetStatus
  }
}

export interface AssetFile {
  id: number
  file: { uuid: string; name: string; mime: string; size: number; is_image: boolean }
  user: Ref | null
  created_at: string
}

export interface Maintenance {
  id: number
  status: 'open' | 'closed'
  description: string
  vendor: string | null
  result: string | null
  cost: string | null
  ticket: { id: number; number: string } | null
  started_at: string
  finished_at: string | null
  created_by: string | null
}

export interface Disposal {
  id: number
  status: 'pending' | 'approved' | 'rejected'
  reason: string
  act_number: string | null
  asset: { id: number; inventory_number: string; name: string; organization: string | null }
  requested_by: string | null
  requested_at: string
  decided_by: string | null
  decided_at: string | null
  decision_note: string | null
  can_decide: boolean
}

export type TransferStatus = 'draft' | 'sent' | 'received' | 'cancelled'

export interface Transfer {
  id: number
  number: string
  status: TransferStatus
  direction: 'outgoing' | 'incoming'
  from: Ref
  to: Ref
  note: string | null
  items_count: number
  act: { uuid: string; name: string } | null
  created_by: string | null
  created_at: string
  sent_at: string | null
  received_at: string | null
  cancelled_at: string | null
  can: { edit: boolean; send: boolean; cancel: boolean; receive: boolean }
  items?: {
    asset: {
      id: number
      inventory_number: string
      name: string
      category: string | null
      serial_number: string | null
    }
    condition_note: string | null
  }[]
}

export interface InventorySession {
  id: number
  name: string
  status: 'open' | 'closed'
  note: string | null
  organization: Ref
  include_descendants: boolean
  started_by: string | null
  started_at: string
  closed_at: string | null
  expected_count: number
  found_count: number
  missing_count: number
  unexpected_count: number
}

export interface InventoryItem {
  id: number
  inventory_number: string
  expected: boolean
  state: 'found' | 'missing' | 'unexpected'
  found_at: string | null
  scanned_by: string | null
  note: string | null
  asset: {
    id: number
    inventory_number: string
    name: string
    category: string | null
    status: AssetStatus
    location: string | null
    room: string | null
  } | null
}

export type ScanResult = 'found' | 'already_found' | 'unexpected' | 'already_scanned'

export interface AssetStats {
  total: number
  active: number
  by_status: Record<AssetStatus, number>
  by_category: { id: number; code: string | null; name: string; total: number }[]
  by_organization: { id: number; name: string; total: number; in_use: number; in_repair: number }[]
  total_cost: string
  warranty_expiring: number
  warranty_expired: number
}
