/**
 * Golden values for App\Support\DocumentTaxes, produced by the document form's
 * own helpers rather than by a second port of them.
 *
 *   node tests/Fixtures/document-math/generate.ts
 *
 * The cases are drawn from a seeded generator, so a rerun only changes the
 * file when the form's arithmetic changes. DocumentTaxesTest replays it.
 */
import { writeFileSync } from 'node:fs'
import {
  calcItemDiscountVal,
  calcItemSubtotal,
  calcTaxAmount,
} from '../../../resources/scripts/features/shared/document-form/use-document-calculations.ts'

let seed = 20260923
const next = (): number => (seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648
const pick = <T>(values: T[]): T => values[Math.floor(next() * values.length)]
const int = (min: number, max: number): number => min + Math.floor(next() * (max - min + 1))

const quantities = [1, 2, 3, 0.5, 1.5, 0.25, 2.75, 3.333, 7.125, 10, 0.1, -1, -2.5, 0]
const percents = [0, 0.5, 5, 7.25, 8.5, 10, 12.345, 15, 19.6, 20, 21, 25, 33.33, 100]
const discounts = [0, 1, 2.5, 5, 7.77, 10, 12.5, 33.33, 50, 99.99, 100, 150]

const subtotals: unknown[] = []
for (let i = 0; i < 300; i++) {
  const price = int(-50000, 5000000)
  const quantity = pick(quantities)
  subtotals.push([price, quantity, calcItemSubtotal(price, quantity)])
}

const itemDiscounts: unknown[] = []
for (let i = 0; i < 300; i++) {
  const subtotal = int(-200000, 2000000)
  const discount = pick(discounts)
  const type = pick(['fixed', 'percentage'] as const)
  itemDiscounts.push([subtotal, discount, type, calcItemDiscountVal(subtotal, discount, type)])
}

const taxes: unknown[] = []
for (let i = 0; i < 600; i++) {
  const base = pick([0, int(-100000, -1), int(1, 999), int(1000, 10000000)])
  const calculationType = pick(['percentage', 'percentage', 'percentage', 'fixed'])
  const percent = calculationType === 'fixed' ? pick([null, 0, 10]) : pick(percents)
  const fixedAmount = calculationType === 'fixed' ? pick([null, 0, 250, 1999]) : pick([null, 0])
  const taxIncluded = next() < 0.4
  const compound = next() < 0.35
  const simpleTotal = compound ? int(0, 300000) : 0
  taxes.push([
    base,
    percent,
    fixedAmount,
    calculationType,
    taxIncluded,
    compound,
    simpleTotal,
    calcTaxAmount(base, percent, fixedAmount, calculationType, taxIncluded, compound, simpleTotal),
  ])
}

// A line's share of the document: the one place the form rounds to two
// decimals, so the ties (multiples of an eighth) are covered on purpose.
const proportions: unknown[] = []
for (let i = 0; i < 300; i++) {
  const lineTotal = int(-100000, 1000000)
  const itemsTotal = int(1, 2000000)
  proportions.push([lineTotal, itemsTotal, parseFloat((lineTotal / itemsTotal).toFixed(2))])
}
for (let eighths = -40; eighths <= 40; eighths++) {
  proportions.push([eighths, 8, parseFloat((eighths / 8).toFixed(2))])
}

const rounds: unknown[] = []
for (const value of [0.5, 1.5, 2.5, -0.5, -1.5, -2.5, 0.49999999999999994, 1234.5, -1234.5, 7.4999, -7.5001]) {
  rounds.push([value, Math.round(value)])
}

writeFileSync(
  new URL('./cases.json', import.meta.url),
  JSON.stringify({ subtotals, itemDiscounts, taxes, proportions, rounds }) + '\n',
)
