export interface CurrencyConfig {
  precision: number
  thousand_separator: string
  decimal_separator: string
  symbol: string
  swap_currency_symbol?: boolean
}

const DEFAULT_CURRENCY: CurrencyConfig = {
  precision: 2,
  thousand_separator: ',',
  decimal_separator: '.',
  symbol: '$',
  swap_currency_symbol: false,
}

/**
 * Format an amount in cents to a currency string with symbol and separators.
 *
 * @param amountInCents - The amount in cents (e.g. 10050 = $100.50)
 * @param currency - Currency configuration for formatting
 * @returns Formatted currency string (e.g. "$ 100.50")
 */
export function formatMoney(
  amountInCents: number,
  currency: CurrencyConfig = DEFAULT_CURRENCY
): string {
  let amount = amountInCents / 100

  const {
    symbol,
    swap_currency_symbol = false,
  } = currency

  let precision = Math.abs(currency.precision)
  if (Number.isNaN(precision)) {
    precision = 2
  }

  const negativeSign = amount < 0 ? '-' : ''
  amount = Math.abs(Number(amount) || 0)

  const fixedAmount = amount.toFixed(precision)
  const integerPart = parseInt(fixedAmount, 10).toString()
  const remainder = integerPart.length > 3 ? integerPart.length % 3 : 0

  const thousandText = remainder
    ? integerPart.substring(0, remainder) + currency.thousand_separator
    : ''

  const amountText = integerPart
    .substring(remainder)
    .replace(/(\d{3})(?=\d)/g, '$1' + currency.thousand_separator)

  const precisionText = precision
    ? currency.decimal_separator +
      Math.abs(amount - parseInt(fixedAmount, 10))
        .toFixed(precision)
        .slice(2)
    : ''

  const combinedAmountText =
    negativeSign + thousandText + amountText + precisionText

  const moneySymbol = `${symbol}`

  return swap_currency_symbol
    ? `${combinedAmountText} ${moneySymbol}`
    : `${moneySymbol} ${combinedAmountText}`
}

/**
 * Parse a formatted currency string back to cents.
 *
 * @param formattedAmount - The formatted string (e.g. "$ 1,234.56")
 * @param currency - Currency configuration used for parsing
 * @returns Amount in cents
 */
export function parseMoneyCents(
  formattedAmount: string,
  currency: CurrencyConfig = DEFAULT_CURRENCY
): number {
  const cleaned = formattedAmount
    .replace(currency.symbol, '')
    .replace(new RegExp(`\\${currency.thousand_separator}`, 'g'), '')
    .replace(currency.decimal_separator, '.')
    .trim()

  const parsed = parseFloat(cleaned)

  if (Number.isNaN(parsed)) {
    return 0
  }

  return Math.round(parsed * 100)
}
