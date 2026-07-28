import { client } from '../client'
import { API } from '../endpoints'

export type PdfDriver = string

export interface DomPdfConfig {
  pdf_driver: string
}

export interface GotenbergConfig {
  pdf_driver: string
  gotenberg_host: string
  gotenberg_papersize: string
}

export type PdfConfig = DomPdfConfig | GotenbergConfig

export interface PdfConfigResponse {
  pdf_driver: string
  [key: string]: unknown
}

export const pdfService = {
  async getDrivers(): Promise<PdfDriver[]> {
    const { data } = await client.get(API.PDF_DRIVERS)
    return data
  },

  async getConfig(): Promise<PdfConfigResponse> {
    const { data } = await client.get(API.PDF_CONFIG)
    return data
  },

  async saveConfig(payload: PdfConfig): Promise<{ success?: string; error?: string }> {
    const { data } = await client.post(API.PDF_CONFIG, payload)
    return data
  },
}
