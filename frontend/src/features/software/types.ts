export type AntivirusEffective = 'updated' | 'installed' | 'outdated' | 'not_installed' | 'unknown'
export const AV_STATES: AntivirusEffective[] = [
  'updated',
  'installed',
  'outdated',
  'not_installed',
  'unknown',
]
export type InstallationSource = 'manual' | 'csv' | 'agent'
export const SOFTWARE_CATEGORIES = [
  'os',
  'office',
  'antivirus',
  'browser',
  'security',
  'utility',
  'development',
  'business',
  'other',
] as const
export const LICENSE_TYPES = ['freeware', 'open_source', 'commercial', 'subscription', 'oem'] as const
export type Problem = 'missing_required' | 'unprotected' | 'outdated'

type Ref = { id: number; name: string }

export interface Software {
  id: number
  name: string
  vendor: string | null
  category: (typeof SOFTWARE_CATEGORIES)[number]
  license_type: (typeof LICENSE_TYPES)[number]
  is_antivirus: boolean
  description: string | null
  is_active: boolean
  installations_count: number | null
}

export interface License {
  id: number
  number: string | null
  software: Ref
  organization: Ref
  license_key_masked: string | null
  has_key: boolean
  seats: number
  used: number
  free: number
  valid_from: string | null
  expires_at: string | null
  supplier: string | null
  cost: string | null
  note: string | null
}

export interface SoftwareDetail extends Software {
  versions: { version: string | null; devices: number }[]
  licenses: License[]
}

export interface Installation {
  id: number
  name: string
  vendor: string | null
  version: string | null
  software: (Ref & { is_antivirus: boolean }) | null
  source: InstallationSource
  license: { id: number; number: string | null } | null
  installed_at: string | null
  last_seen_at: string | null
}

export interface Antivirus {
  effective: AntivirusEffective
  state: 'installed' | 'not_installed' | null
  product: string | null
  software_id: number | null
  definitions_updated_at: string | null
  last_scan_at: string | null
  reported_at: string | null
  source: InstallationSource | null
}

export interface Agent {
  id: number
  name: string
  platform: string | null
  agent_version: string | null
  is_active: boolean
  last_checkin_at: string | null
  last_ip: string | null
  asset?: { id: number; inventory_number: string; name: string; organization: Ref }
}

export interface DeviceSoftware {
  installations: Installation[]
  antivirus: Antivirus
  inventoried_at: string | null
  agent: Agent | null
}

export interface Policy {
  id: number
  software: Ref
  organization: Ref | null
  asset_category: Ref | null
  note: string | null
}

export interface ComplianceRow {
  organization: Ref
  devices: number
  antivirus: Record<AntivirusEffective, number>
  protected: number
  missing_required: number
  inventoried: number
}

export interface ComplianceDevice {
  id: number
  inventory_number: string
  name: string
  organization: Ref
  antivirus: AntivirusEffective
  product: string | null
  missing: string[]
  inventoried_at: string | null
}

export interface Unrecognized {
  name: string
  normalized_name: string
  vendor: string | null
  devices: number
}

export interface ImportResult {
  total: number
  imported: number
  errors: { line: number; message: string }[]
}
