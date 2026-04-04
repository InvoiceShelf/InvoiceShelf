import { ref } from 'vue'
import type { Ref } from 'vue'
import { formatMoney as formatMoneyUtil } from '../utils/format-money'
import type { CurrencyConfig } from '../utils/format-money'

export interface Currency {
  id: number
  code: string
  name: string
  precision: number
  thousand_separator: string
  decimal_separator: string
  symbol: string
  swap_currency_symbol?: boolean
  exchange_rate?: number
  [key: string]: unknown
}

export interface UseCurrencyReturn {
  currencies: Ref<Currency[]>
  setCurrencies: (data: Currency[]) => void
  formatMoney: (amountInCents: number, currency?: CurrencyConfig) => string
  convertCurrency: (
    amountInCents: number,
    fromRate: number,
    toRate: number
  ) => number
  findCurrencyByCode: (code: string) => Currency | undefined
}

const currencies = ref<Currency[]>([])

/**
 * Default currency configuration matching the v1 behavior.
 */
const DEFAULT_CURRENCY_CONFIG: CurrencyConfig = {
  precision: 2,
  thousand_separator: ',',
  decimal_separator: '.',
  symbol: '$',
  swap_currency_symbol: false,
}

/**
 * Composable for currency formatting and conversion.
 * Maintains a shared list of available currencies.
 */
export function useCurrency(): UseCurrencyReturn {
  function setCurrencies(data: Currency[]): void {
    currencies.value = data
  }

  /**
   * Format an amount in cents using the provided or default currency config.
   *
   * @param amountInCents - Amount in cents
   * @param currency - Optional currency config override
   * @returns Formatted currency string
   */
  function formatMoney(
    amountInCents: number,
    currency?: CurrencyConfig
  ): string {
    return formatMoneyUtil(amountInCents, currency ?? DEFAULT_CURRENCY_CONFIG)
  }

  /**
   * Convert an amount from one currency to another using exchange rates.
   *
   * @param amountInCents - Amount in cents in the source currency
   * @param fromRate - Exchange rate of the source currency
   * @param toRate - Exchange rate of the target currency
   * @returns Converted amount in cents
   */
  function convertCurrency(
    amountInCents: number,
    fromRate: number,
    toRate: number
  ): number {
    if (fromRate === 0) {
      return 0
    }
    return Math.round((amountInCents / fromRate) * toRate)
  }

  /**
   * Find a currency by its ISO code.
   *
   * @param code - The ISO 4217 currency code (e.g. "USD")
   * @returns The matching Currency, or undefined
   */
  function findCurrencyByCode(code: string): Currency | undefined {
    return currencies.value.find(
      (c) => c.code.toUpperCase() === code.toUpperCase()
    )
  }

  return {
    currencies,
    setCurrencies,
    formatMoney,
    convertCurrency,
    findCurrencyByCode,
  }
}
