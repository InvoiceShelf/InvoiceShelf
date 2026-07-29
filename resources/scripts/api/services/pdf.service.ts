import { client } from '../client'
import { API } from '../endpoints'

export type PdfDriver = string

/**
 * Page geometry, applied whichever driver renders. Sizes and margins are CSS
 * lengths (e.g. "210mm"), the only notation both drivers accept without loss.
 */
export interface PdfPageSetup {
  pdf_paper_width: string
  pdf_paper_height: string
  pdf_orientation: 'portrait' | 'landscape'
  pdf_margin_top: string
  pdf_margin_right: string
  pdf_margin_bottom: string
  pdf_margin_left: string
}

export interface DomPdfConfig extends PdfPageSetup {
  pdf_driver: string
}

export interface GotenbergConfig extends PdfPageSetup {
  pdf_driver: string
  gotenberg_host: string
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
