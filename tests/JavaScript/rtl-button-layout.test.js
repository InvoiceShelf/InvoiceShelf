import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import test from 'node:test'

function readProjectFile(path) {
  return readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8')
}

test('uses logical spacing for BaseButton icons', () => {
  const baseButton = readProjectFile(
    'resources/scripts/components/base/BaseButton.vue',
  )

  assert.match(baseButton, /-ms-1 me-2 h-5 w-5/)
  assert.match(baseButton, /ms-2 -me-1 h-5 w-5/)
  assert.doesNotMatch(baseButton, /-ml-1 mr-2 h-5 w-5/)
  assert.doesNotMatch(baseButton, /ml-2 -mr-1 h-5 w-5/)
})

test('uses logical spacing between invoice action buttons', () => {
  const invoiceIndex = readProjectFile(
    'resources/scripts/admin/views/invoices/Index.vue',
  )
  const invoiceView = readProjectFile(
    'resources/scripts/admin/views/invoices/View.vue',
  )

  assert.match(invoiceIndex, /class="ms-4"/)
  assert.match(invoiceView, /class="text-sm me-3"/)
  assert.match(invoiceView, /class="ms-3"/)
})

test('uses logical spacing between document edit actions', () => {
  const editViewPaths = [
    'resources/scripts/admin/views/invoices/create/InvoiceCreate.vue',
    'resources/scripts/admin/views/estimates/create/EstimateCreate.vue',
    'resources/scripts/admin/views/recurring-invoices/create/RecurringInvoiceCreate.vue',
  ]

  for (const path of editViewPaths) {
    const editView = readProjectFile(path)

    assert.match(editView, /class="me-3"/, path)
    assert.doesNotMatch(editView, /class="mr-3"/, path)
  }
})
