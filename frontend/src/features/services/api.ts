import { api } from '@/api/client'
import type { MessageResponse, Paginated, Resource } from '@/api/types'
import type { LinkedDocument } from '@/components/LinkedDocuments'
import type {
  Contract,
  CostReport,
  Invoice,
  InvoiceDetail,
  InvoiceSummary,
  ItService,
  Provider,
} from './types'

type Params = Record<string, string | number>
type Values = Record<string, unknown>

const data = <T>(p: Promise<{ data: Resource<T> }>) => p.then((r) => r.data.data)
const message = (p: Promise<{ data: MessageResponse }>) => p.then((r) => r.data.message)
const upload = (url: string, file: File) => {
  const body = new FormData()
  body.append('file', file)
  return data<InvoiceDetail>(api.post(url, body))
}

export const listProviders = (params: Params = {}) =>
  data<Provider[]>(api.get('/service-providers', { params }))
export const saveProvider = (values: Values, id?: number) =>
  data<Provider>(id ? api.put(`/service-providers/${id}`, values) : api.post('/service-providers', values))
export const deleteProvider = (id: number) => message(api.delete(`/service-providers/${id}`))

export const listContracts = (params: Params) =>
  api.get<Paginated<Contract>>('/service-contracts', { params }).then((r) => r.data)
export const getContract = (id: number) => data<Contract>(api.get(`/service-contracts/${id}`))
export const saveContract = (values: Values, id?: number) =>
  data<Contract>(id ? api.put(`/service-contracts/${id}`, values) : api.post('/service-contracts', values))
export const deleteContract = (id: number) => message(api.delete(`/service-contracts/${id}`))
export const contractDocuments = (id: number) =>
  data<LinkedDocument[]>(api.get(`/service-contracts/${id}/documents`))

export const listServices = (params: Params) =>
  api.get<Paginated<ItService>>('/it-services', { params }).then((r) => r.data)
export const getService = (id: number) => data<ItService>(api.get(`/it-services/${id}`))
export const saveService = (values: Values, id?: number) =>
  data<ItService>(id ? api.put(`/it-services/${id}`, values) : api.post('/it-services', values))
export const deleteService = (id: number) => message(api.delete(`/it-services/${id}`))
export const addExtraInvoice = (serviceId: number, values: Values) =>
  data<InvoiceDetail>(api.post(`/it-services/${serviceId}/invoices`, values))

export const listInvoices = (params: Params) =>
  api
    .get<Paginated<Invoice> & { summary: InvoiceSummary }>('/service-invoices', { params })
    .then((r) => r.data)
export const getInvoice = (id: number) => data<InvoiceDetail>(api.get(`/service-invoices/${id}`))
export const enterInvoice = (id: number, values: Values) =>
  data<InvoiceDetail>(api.put(`/service-invoices/${id}`, values))
export const approveInvoice = (id: number) => data<InvoiceDetail>(api.post(`/service-invoices/${id}/approve`))
export const addPayment = (id: number, values: Values) =>
  data<InvoiceDetail>(api.post(`/service-invoices/${id}/payments`, values))
export const deletePayment = (id: number) => data<InvoiceDetail>(api.delete(`/service-payments/${id}`))
export const deleteInvoice = (id: number) => message(api.delete(`/service-invoices/${id}`))
export const uploadInvoiceFile = (id: number, file: File) => upload(`/service-invoices/${id}/file`, file)
export const uploadPaymentFile = (id: number, file: File) => upload(`/service-payments/${id}/file`, file)

export const serviceCosts = (params: Params) => data<CostReport>(api.get('/service-costs', { params }))
