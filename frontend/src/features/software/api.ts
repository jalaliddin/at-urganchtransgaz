import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type {
  Agent,
  Antivirus,
  ComplianceDevice,
  ComplianceRow,
  DeviceSoftware,
  ImportResult,
  Installation,
  License,
  Policy,
  Software,
  SoftwareDetail,
  Unrecognized,
} from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const message = (p: Promise<{ data: MessageResponse }>) => p.then((r) => r.data.message)

export const listSoftware = (params: Params) =>
  api.get<Paginated<Software>>('/software', { params }).then((r) => r.data)
export const getSoftware = (id: number) => data<SoftwareDetail>(api.get(`/software/${id}`))
export const saveSoftware = (values: Values, id?: number) =>
  data<Software>(id ? api.put(`/software/${id}`, values) : api.post('/software', values))
export const deleteSoftware = (id: number) => message(api.delete(`/software/${id}`))
export const listUnrecognized = () => data<Unrecognized[]>(api.get('/software-unrecognized'))
export const adoptName = (softwareId: number, name: string) =>
  data<{ linked: number }>(api.post(`/software/${softwareId}/adopt`, { name }))

export const listLicenses = (params: Params) =>
  api.get<Paginated<License>>('/software-licenses', { params }).then((r) => r.data)
export const saveLicense = (values: Values, id?: number) =>
  data<License>(id ? api.put(`/software-licenses/${id}`, values) : api.post('/software-licenses', values))
export const deleteLicense = (id: number) => message(api.delete(`/software-licenses/${id}`))
export const revealLicense = (id: number) =>
  data<{ license_key: string | null }>(api.post(`/software-licenses/${id}/reveal`))

export const deviceSoftware = (assetId: number) =>
  data<DeviceSoftware>(api.get(`/assets/${assetId}/software`))
export const addInstallation = (assetId: number, values: Values) =>
  data<Installation>(api.post(`/assets/${assetId}/software`, values))
export const updateInstallation = (id: number, values: Values) =>
  data<Installation>(api.patch(`/software-installations/${id}`, values))
export const removeInstallation = (id: number) => message(api.delete(`/software-installations/${id}`))
export const reportAntivirus = (assetId: number, values: Values) =>
  data<Antivirus>(api.put(`/assets/${assetId}/antivirus`, values))

export const listPolicies = () => data<Policy[]>(api.get('/software-policies'))
export const savePolicy = (values: Values, id?: number) =>
  data<Policy>(id ? api.put(`/software-policies/${id}`, values) : api.post('/software-policies', values))
export const deletePolicy = (id: number) => message(api.delete(`/software-policies/${id}`))

export const complianceSummary = (params: Params) =>
  data<{ organizations: ComplianceRow[] }>(api.get('/software-compliance', { params }))
export const complianceDevices = (params: Params) =>
  api.get<Paginated<ComplianceDevice>>('/software-compliance/devices', { params }).then((r) => r.data)

export const listAgents = (params: Params) =>
  api.get<Paginated<Agent>>('/device-agents', { params }).then((r) => r.data)
export const registerAgent = (assetId: number, name: string) =>
  data<Agent & { token: string }>(api.post(`/assets/${assetId}/agent`, { name }))
export const issueToken = (agentId: number) =>
  data<Agent & { token: string }>(api.post(`/device-agents/${agentId}/tokens`))
export const revokeAgent = (agentId: number) => data<Agent>(api.post(`/device-agents/${agentId}/revoke`))

export function importCsv(type: 'installations' | 'antivirus', file: File) {
  const form = new FormData()
  form.append('type', type)
  form.append('file', file)
  return data<ImportResult>(api.post('/software-imports', form))
}
