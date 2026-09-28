# Bills and supplier payments

Available in the v3 purchasing implementation. This guide describes the development
branch; publication into the versioned documentation book follows its release review.

## Navigation

The Purchases menu contains **Suppliers**, **Bills**, **Expenses**, and **Payments**. Use the view selector in each list header:

- Bills offers **Bills** and **Credits** views.
- Payments offers **Payments** and **Refunds** views.

The info icon beside a page or report-section title opens its explanation. The same
control is used across the business screens, including sales, customers, items, and reports.

Sales and Purchases both use **Payments** in their menus. Their section headings,
collapsed-sidebar tooltips, and search results identify which side a payment belongs to.

The available views follow the user's permissions. Existing links to credits and
refunds still open the corresponding screen.

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
Settled bills and linked credits keep their supplier fixed. Refunds display the supplier belonging to the chosen source.

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

This support is for supplier and bill records. It does not add fields to bill lines,
credits, payments or refunds, and these internal fields do not print on PDFs.

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
credits and refunds, with balances grouped by currency.

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
payments cannot be voided while active refunds remain. A void keeps its reason on the
record.

## Not yet available

Recurring bills and recurring paid expenses are planned as a follow-up, built on
a scheduler shared with recurring invoices rather than a second engine. Until then,
recurring supplier charges are entered as they arrive.

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

**Reports → Taxes** is unchanged: bills and supplier credits are not part of it yet.
They join it in a follow-up that moves both sides of that report to document dates.

The Expenses report continues to cover direct expenses. Historical payables and
custom-field filtering/grouping are not provided by this reporting pass.

## API

All endpoints require staff authentication and the `company` header. The generated
OpenAPI document describes the resource inputs and responses. Money uses the host's
integer minor-unit convention (100 represents 1.00).

New resources: `/api/v1/suppliers`, `/bills`, `/supplier-payments`, `/supplier-credits`,
and `/supplier-refunds`. Existing `/api/v1/payments` remains the customer-receipt API.

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
relevant record permissions.

Supplier and bill writes accept `customFields: [{"id": 1, "value": "PO-123"}]`;
their responses expose stored answers as `fields` using the existing custom-field
resource format. IDs must belong to the active company and the correct model.
Creation applies definition defaults. Updates preserve omitted answers; an
explicit `null` clears an optional answer. Required fields are checked against
the resulting values, including defaults and preserved answers.

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

`POST /{resource}/{id}/actions` accepts `open`/`void` for bills and `void` for monetary
records. A void requires `reason`.

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
