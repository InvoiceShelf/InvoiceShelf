export interface Currency {
  id: number
  name: string
  code: string
  symbol: string
  precision: number
  thousand_separator: string
  decimal_separator: string
  swap_currency_symbol: boolean
  exchange_rate: number
}

export interface ExchangeRateLog {
  id: number
  company_id: number
  base_currency_id: number
  currency_id: number
  exchange_rate: number
  created_at: string
  updated_at: string
}

export interface ExchangeRateProvider {
  id: number
  key: string
  driver: string
  currencies: string[]
  driver_config: Record<string, string>
  company_id: number
  active: boolean
  company?: import('./company').Company
}
