import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

const invoiceView = readFileSync(
  new URL(
    '../../resources/scripts/admin/views/invoices/View.vue',
    import.meta.url,
  ),
  'utf8',
)

test('positions the admin invoice viewer with logical RTL-aware offsets', () => {
  assert.match(invoiceView, /class="xl:ps-96 xl:ms-8"/)
  assert.match(invoiceView, /\binset-s-0\b/)
  assert.match(invoiceView, /\bms-56\b/)
  assert.match(invoiceView, /\bxl:ms-64\b/)

  assert.doesNotMatch(invoiceView, /\bxl:pl-96\b/)
  assert.doesNotMatch(invoiceView, /\bxl:ml-8\b/)
  assert.doesNotMatch(invoiceView, /\bleft-0\b/)
  assert.doesNotMatch(invoiceView, /\bml-56\b/)
  assert.doesNotMatch(invoiceView, /\bxl:ml-64\b/)
})
