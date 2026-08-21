import assert from 'node:assert/strict'
import { readFileSync, readdirSync } from 'node:fs'
import test from 'node:test'

function readProjectFile(path) {
  return readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8')
}

const documentViewPaths = [
  'resources/scripts/admin/views/invoices/View.vue',
  'resources/scripts/admin/views/estimates/View.vue',
  'resources/scripts/admin/views/payments/View.vue',
  'resources/scripts/admin/views/recurring-invoices/partials/RecurringInvoiceViewSidebar.vue',
  'resources/scripts/customer/views/invoices/View.vue',
  'resources/scripts/customer/views/estimates/View.vue',
  'resources/scripts/customer/views/payments/View.vue',
]

const selectableIndexPaths = [
  'resources/scripts/admin/views/invoices/Index.vue',
  'resources/scripts/admin/views/estimates/Index.vue',
  'resources/scripts/admin/views/items/Index.vue',
  'resources/scripts/admin/views/payments/Index.vue',
  'resources/scripts/admin/views/recurring-invoices/Index.vue',
  'resources/scripts/admin/views/expenses/Index.vue',
]

test('uses logical positioning in document sidebars', () => {
  for (const path of documentViewPaths) {
    const view = readProjectFile(path)

    assert.match(view, /\binset-s-0\b/, path)
    assert.match(view, /\bborder-s(?:-4)?\b/, path)
    assert.doesNotMatch(view, /\bleft-0\b/, path)
    assert.doesNotMatch(view, /\bborder-l(?:-4)?\b/, path)
    assert.doesNotMatch(view, /\btext-right\b/, path)
  }
})

test('mirrors document search icons without changing BaseInput globally', () => {
  for (const path of documentViewPaths) {
    const view = readProjectFile(path)

    assert.match(view, /rtl:block/, path)
    assert.match(view, /rtl:hidden/, path)
  }
})

test('uses logical spacing in shared header and navigation components', () => {
  const siteHeader = readProjectFile(
    'resources/scripts/admin/layouts/partials/TheSiteHeader.vue',
  )
  const companySwitcher = readProjectFile(
    'resources/scripts/components/CompanySwitcher.vue',
  )
  const globalSearch = readProjectFile(
    'resources/scripts/components/GlobalSearchBar.vue',
  )
  const listItem = readProjectFile(
    'resources/scripts/components/list/BaseListItem.vue',
  )
  const breadcrumbItem = readProjectFile(
    'resources/scripts/components/base/BaseBreadcrumbItem.vue',
  )

  assert.equal(siteHeader.match(/class="ms-2"/g)?.length, 2)
  assert.match(siteHeader, /float-left ms-2/)
  assert.match(companySwitcher, /\binset-e-0\b/)
  assert.match(globalSearch, /\binset-e-0\b/)
  assert.match(listItem, /\bme-3\b/)
  assert.match(breadcrumbItem, /\bme-2\b/)
})

test('positions selectable table controls at inline start', () => {
  for (const path of selectableIndexPaths) {
    const index = readProjectFile(path)

    assert.match(index, /\binset-s-6\b/, path)
    assert.doesNotMatch(index, /\bleft-6\b/, path)
  }

  const checkbox = readProjectFile(
    'resources/scripts/components/base/BaseCheckbox.vue',
  )

  assert.match(checkbox, /\bms-3\b/)
  assert.doesNotMatch(checkbox, /\bml-3\b/)
})

test('keeps multiselect controls and text clear in RTL', () => {
  const multiselect = readProjectFile(
    'resources/scripts/components/base-select/BaseMultiselect.vue',
  )
  const createItems = readProjectFile(
    'resources/scripts/admin/components/estimate-invoice-common/CreateItems.vue',
  )

  assert.match(multiselect, /rtl:justify-start/)
  assert.match(multiselect, /rtl:ps-10 rtl:pe-3\.5/)
  assert.match(multiselect, /relative ms-1 opacity-40/)
  assert.match(multiselect, /'ps-3\.5 relative z-10/)
  assert.match(multiselect, /z-10 ms-3\.5 animate-spin/)
  assert.match(multiselect, /\btext-start\b/)
  assert.doesNotMatch(multiselect, /rounded-md pl-3\.5/)
  assert.match(createItems, /class="me-2"/)
  assert.doesNotMatch(createItems, /class="mr-2"/)
})

test('uses logical spacing throughout the customer selection popup', () => {
  const customerSelect = readProjectFile(
    'resources/scripts/components/base/BaseCustomerSelectPopup.vue',
  )

  assert.match(customerSelect, /\bms-3\b/)
  assert.match(customerSelect, /\bme-4\b/)
  assert.match(customerSelect, /\btext-start\b/)
  assert.doesNotMatch(customerSelect, /\b(?:ml|mr)-(?:1|3|4|5|6)\b/)
  assert.doesNotMatch(customerSelect, /\btext-left\b/)
})

test('keeps date picker text clear of its logical-start icon', () => {
  const datePicker = readProjectFile(
    'resources/scripts/components/base/BaseDatePicker.vue',
  )

  assert.match(datePicker, /\binset-s-0\b/)
  assert.match(datePicker, /\bms-2\b/)
  assert.match(datePicker, /font-base ps-8 py-2/)
  assert.doesNotMatch(datePicker, /font-base pl-8 py-2/)
})

test('uses logical spacing between dropdown action icons and text', () => {
  const dropdownDirectory = new URL(
    '../../resources/scripts/admin/components/dropdowns/',
    import.meta.url,
  )

  for (const fileName of readdirSync(dropdownDirectory)) {
    if (!fileName.endsWith('.vue')) {
      continue
    }

    const dropdown = readProjectFile(
      `resources/scripts/admin/components/dropdowns/${fileName}`,
    )

    assert.doesNotMatch(dropdown, /\bmr-3\b/, fileName)
  }
})
