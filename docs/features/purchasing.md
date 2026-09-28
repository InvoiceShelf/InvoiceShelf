# Bills and supplier payments

Available in the v3 purchasing implementation. This guide describes the development
branch; publication into the versioned documentation book follows its release review.

## Navigation

The Purchases menu contains **Suppliers**, **Bills**, **Expenses**, and **Payments**. Use the view selector in each list header:

- Bills offers **One-time**, **Credits**, and **Recurring** views.
- Expenses offers **One-time** and **Recurring** views.
- Payments offers **Payments** and **Refunds** views.

The info icon beside a page or report-section title opens its explanation. The same
control is used across the business screens, including sales, customers, items, and reports.

Sales and Purchases both use **Payments** in their menus. Their section headings,
collapsed-sidebar tooltips, and search results identify which side a payment belongs to.

The available views follow the user's permissions. Existing links to credits,
refunds, and recurring costs still open the corresponding screen.

## Choosing a record

Use **Expenses** for a purchase already paid. Use **Purchases → Bills** when a supplier
invoice needs a balance and payment history. Paying a bill creates a supplier payment;
it does not create a second expense.

Choose a supplier from the searchable card, or use **New supplier** inside the picker.
The inline form has the same fields as the Suppliers screen, including addresses and
purchase defaults. Its currency, payment terms, and expense category prefill new
purchases. Editing the selected supplier updates the card without changing amounts
or dates already entered. Suppliers have no customer portal login. Existing expenses
can be associated with a supplier without converting them into bills; the supplier
remains optional for expenses.

Category, payment-method, and purchase-tax selectors offer inline creation when the
user has permission. Saving selects the new record in the field that opened the dialog;
a new tax is added to the line's existing selection. Cancelling keeps the purchase
draft intact. A supplier's default category can also be created inside its form.
Settled bills, linked credits, and schedules that have generated records keep their
supplier fixed. Refunds display the supplier belonging to the chosen source.

## Custom fields

In **Settings → Custom Fields**, choose **Supplier** or **Bill** to add internal
fields such as a supplier account number, purchase-order number, or department.
Configured fields appear alongside the standard fields; the supplier's inline
dialog and standalone form use the same definitions. Answers are visible on the
record's detail screen. Supplier answers stay with the supplier and are not
automatically copied onto bills.

Users who can work with the relevant records can fill in their fields without
access to custom-field Settings. Required fields and configured constraints are
validated when saving. Paid or credited bills still allow custom-field edits under
the bill-edit permission, while their financial details remain locked. Void bills
remain read-only.

Recurring bills use **Bill** definitions. Template answers are copied into each new
bill; changing the template never changes previous bills. Generation ignores
definitions deleted since the template was saved. Missing required or invalid
answers leave the occurrence pending with an error for correction and retry.

This support is for supplier and bill records, including recurring bills. It does
not add fields to bill lines, credits, payments, refunds, or recurring expenses,
and these internal fields do not print on PDFs.

## Recording a bill

Choose the supplier, supplier reference, document date, due date, and currency. Each
line has a description, quantity, unit price, category, optional percentage discount,
and purchase taxes. Choose whether prices include tax. Save as Draft while preparing
it, or Recorded when it should contribute to payables and purchase reporting. Attach the
original PDF or image; downloads require company access and the document's permission.

Use **New supplier payment** for money already sent. Enter its actual date, amount, method,
and reference. A payment can cover several bills from the same supplier in the same
currency. The unapplied remainder is an advance available for later bills.

On a supplier payment, **Manage allocations** changes which bills it settles. Zero
releases an allocation, reopening that bill. Payment creation and allocation updates
are separate permissions. Supplier details link to the supplier's bills, payments,
credits, refunds, and recurring costs, with balances grouped by currency.

## Credits and refunds

A supplier credit reduces purchase cost. A refund records money returned. Creating or
applying a credit does not itself move cash.

From a bill, **More → New supplier credit** retains the original prices and tax snapshots. Enter
returned quantities; partial credits together cannot exceed the original quantities.
A paid bill can be credited. Its payment history remains, while the new credit can be
applied to another bill or refunded. A standalone supplier credit is also supported.
From an expense assigned to a supplier, **New supplier credit** accepts the amount credited.

On a supplier credit or payment with an available balance, **More → New supplier refund** records
money actually returned. Allocated funds must first be released before a payment can
be refunded. A refund cannot exceed the available balance.

**More → Void** corrects an entry made in error and requires a reason. It is not a substitute
for recording returned cash. A bill cannot be voided while settlements or active linked
credits remain; credits cannot be voided while allocations or active refunds remain;
payments cannot be voided while active refunds remain. Changes appear in history.

## Recurring costs

Use **Bills → Recurring bills** for unpaid invoices, or **Expenses → Recurring expenses** for known paid charges. A recurring cost normally creates an unpaid bill. Choose a frequency and interval,
start date, optional end date or occurrence count, and the bill's payment terms.
Monthly dates retain their original day, using the last day in shorter months.

The **Paid expense** mode requires explicit permission to create expenses and explicit
consent to record the known charge automatically. It does not check a bank balance or
initiate a payment. Each occurrence uses the template's saved exchange rate.

The scheduler runs `recurring-costs:generate` every minute. Generation catches up after
downtime, in batches of at most 100 occurrences per schedule. Each successful record,
occurrence marker, and next date are committed together. A failed occurrence retains
its date for retry and exposes its error on the schedule. Pausing creates no records;
resuming starts with the next future occurrence and skips the paused dates.

## Reports

**Reports → Purchases** offers a period picker, an optional supplier filter, and a
PDF download of the same figures. The supplier selector is available to users with
supplier-view permission; financial-report permission is required for the report
and its PDF. Existing `/admin/reports/purchases` links open the Purchases tab.

- Cash movement uses expense and supplier payment/refund dates. Advances are included.
- Purchase costs use expense, open-bill, and supplier-credit document dates. Settlement
  records do not add further costs. Purchase tax uses the saved document tax breakdown.
- Current payables show outstanding bills, overdue balances, due within 30 days, and due
  later. The screen and PDF label the balance date in the company's timezone. These
  are current balances, independent of the selected period. Unapplied advances and
  unused credits are separate until explicitly allocated.
- Company totals use stored base-currency amounts. Supplier balances retain their own
  currency; allocations between different currencies are not supported.

The existing cash dashboard and cash profit/loss report include supplier payments and
refunds. The profit/loss PDF explicitly labels its cash basis and includes advances.
The dashboard also displays current payables for users with bill-view permission.

**Reports → Taxes** uses document dates consistently: issued invoices and customer
credit notes contribute sales tax; expenses and recorded bills, less supplier credits,
contribute purchase tax. Unpaid and partially paid invoices are included. Drafts and
void purchase documents are excluded. Payments, refunds, and allocations do not add
another tax entry. The report uses stored tax amounts, names, and exchange-rate
snapshots. This replaces the previous sales-tax filter that required fully paid invoices.

The Expenses report continues to cover direct expenses. Historical payables and
custom-field filtering/grouping are not provided by this reporting pass.

## API

All endpoints require staff authentication and the `company` header. The generated
OpenAPI document describes the resource inputs and responses. Money uses the host's
integer minor-unit convention (100 represents 1.00).

New resources: `/api/v1/suppliers`, `/bills`, `/supplier-payments`, `/supplier-credits`,
`/supplier-refunds`, and `/recurring-costs`. Existing `/api/v1/payments` remains the
customer-receipt API. The recurring-cost list accepts an optional `mode=BILL` or
`mode=EXPENSE` filter; omitting it continues to return both kinds.

Example bill body:

```json
{
  "supplier_id": 1,
  "currency_id": 142,
  "exchange_rate": 1,
  "document_date": "2026-09-01",
  "due_date": "2026-09-30",
  "status": "OPEN",
  "reference": "SUPPLIER-123",
  "tax_included": false,
  "items": [{
    "description": "Hosting",
    "expense_category_id": 1,
    "quantity": 1,
    "price": 100000,
    "discount": 0,
    "tax_type_ids": []
  }]
}
```

Use IDs returned by the installation's reference endpoints, not the example IDs.
`GET /api/v1/purchase-options` supplies currencies, categories, purchase taxes, and
manual payment methods without requiring access to customer payment settings.

`GET /api/v1/reports/purchases` accepts `from_date`, `to_date`, and optional
`supplier_id`. Its `supplier` identifies the selected supplier (or is `null`), and
`payables.as_of_date` identifies the current balance date. The dashboard exposes the
same dated payables summary only to users with bill-view permission.
`GET /reports/purchases/{hash}` accepts the same filters and supports the existing
`preview` and `download` flags. The hash identifies the company; authentication,
report permission, and company membership are still required.

Pass `custom_field_model=Supplier` or `custom_field_model=Bill` to also receive
the corresponding definitions in `data.custom_fields`. This request uses the
relevant record permissions, including recurring-cost permissions for Bill fields.

Supplier and bill writes accept `customFields: [{"id": 1, "value": "PO-123"}]`;
their responses expose stored answers as `fields` using the existing custom-field
resource format. IDs must belong to the active company and the correct model.
Creation applies definition defaults. Updates preserve omitted answers; an
explicit `null` clears an optional answer. Required fields are checked against
the resulting values, including defaults and preserved answers. Recurring bills
accept the same answer array under `template.customFields`.

Payment creation accepts `allocations: [{"bill_id": 1, "amount": 100000}]` and may leave
an unapplied balance. `PUT /supplier-payments/{id}/allocations` and
`PUT /supplier-credits/{id}/allocations` replace the entire allocation set; an empty
array releases every allocation.

Credits use the bill's item shape. For a linked credit, provide `source_bill_id` and
`source_bill_item_id` on each line; quantity determines the credit and supplied prices
are replaced with source snapshots. For an expense credit, provide `source_expense_id`
and `source_amount`, with the expense already assigned to the supplier.

Refund creation accepts exactly one of `supplier_payment_id` or `supplier_credit_id`,
plus `amount`, `payment_date`, and `exchange_rate`. Optional fields are
`payment_method_id`, `reference`, and `notes`.

`POST /{resource}/{id}/actions` accepts `open`/`void` for bills, `void` for monetary
records, and `pause`/`resume` for recurring costs. A void requires `reason`.

A recurring cost's `template` is a bill input without dates/status, or an expense input
with `amount`, `currency_id`, `exchange_rate`, `expense_category_id`, and optional
`payment_method_id`, `notes`, and `taxes: [{"tax_type_id": 1, "amount": 100}]`.
Top-level fields include `name`, `supplier_id`, `mode` (`BILL` or `EXPENSE`), `frequency`
(`DAY`, `WEEK`, `MONTH`, `YEAR`), `interval`, `starts_at`, optional `ends_at` and
`max_occurrences`, `due_days`, and `auto_record_paid`.

## Upgrade

Back up the database and storage. Pause writers and workers during migration, deploy
the matching application code, run migrations, then restart workers and clear cached
configuration/authorization data through the application's normal upgrade process.

The migration renames `payments` to `customer_payments` and `payment_allocations` to
`customer_payment_allocations`. It changes the persisted morph aliases `payment` and
`payment_allocation` to `customer_payment` and `customer_payment_allocation` in media,
email logs, custom-field values, and authorization/type-bearing columns. Record IDs,
foreign-key column names, receipt URLs, and public v1 discriminator strings remain.
Historical migrations are retained. A new migration synchronizes owner role grants.

The new purchasing tables are additive. Historical expenses are not converted and
suppliers are not inferred. New PHP model namespaces remain canonical; no class aliases
or duplicate legacy models are introduced.
