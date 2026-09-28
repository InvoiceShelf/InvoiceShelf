import { client } from '../client'
import { platform } from '@/scripts/platform'
import type { PurchaseKind, PurchaseRecord, PurchaseOptions, PurchaseReport, Allocation } from '@/scripts/types/domain/purchase'

export const purchaseService = {
  async list(kind: PurchaseKind, params: Record<string, unknown> = {}) {
    return (await client.get<{data: PurchaseRecord[]; meta: {current_page: number; last_page: number; total: number}}>(`/api/v1/${kind}`, { params })).data
  },
  async get(kind: PurchaseKind, id: number) { return (await client.get<{data: PurchaseRecord}>(`/api/v1/${kind}/${id}`)).data.data },
  async save(kind: PurchaseKind, data: unknown, id?: number) {
    return (await (id ? client.put<{data: PurchaseRecord}>(`/api/v1/${kind}/${id}`, data) : client.post<{data: PurchaseRecord}>(`/api/v1/${kind}`, data))).data.data
  },
  async action(kind: PurchaseKind, id: number, action: string, reason?: string) {
    return (await client.post<{data: PurchaseRecord}>(`/api/v1/${kind}/${id}/actions`, { action, reason })).data.data
  },
  async allocate(kind: PurchaseKind, id: number, allocations: Allocation[]) {
    return (await client.put<{data: PurchaseRecord}>(`/api/v1/${kind}/${id}/allocations`, { allocations: allocations.map(({bill_id, amount}) => ({bill_id, amount})) })).data.data
  },
  async options(customFieldModel?: 'Supplier' | 'Bill') { return (await client.get<{data: PurchaseOptions}>('/api/v1/purchase-options', {params: customFieldModel ? {custom_field_model: customFieldModel} : undefined})).data.data },
  async suppliers(search: string) { return (await this.list('suppliers', {search, limit: 100})).data },
  async upload(kind: PurchaseKind, id: number, file: File) {
    const body = new FormData(); body.append('file', file)
    await client.post(`/api/v1/${kind}/${id}/attachments`, body)
  },
  async download(url: string, name: string) {
    const response = await client.get(url, {responseType: 'blob'})
    await platform.saveFile(response.data, name)
  },
  async report(from_date: string, to_date: string, supplier_id?: number) {
    return (await client.get<{data: PurchaseReport}>('/api/v1/reports/purchases', {params: {from_date, to_date, supplier_id}})).data.data
  },
}
