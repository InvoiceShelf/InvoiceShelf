# ADR 0004: Explicit customer receipts and supplier settlements

Date: 2026-09-27

Status: Accepted

## Decision

Purchases owns suppliers, itemized bills, supplier payments and their allocations,
supplier credits and their allocations, supplier refunds, recurring costs, and audit
history. These capabilities are core. Customer receipts stay in Receivables.

Existing storage identities become `customer_payments`, `customer_payment_allocations`,
`customer_payment`, and `customer_payment_allocation`. These distinguish customer
receipts from supplier money sent without collapsing both domains into one directional
transaction table. Public API identities are independent and remain compatible.

Bills and credits describe obligations and costs. Payments and refunds describe cash.
A supplier credit can reference a paid purchase, apply to another bill, or be refunded.
An advance is the unapplied part of a supplier payment. No payment generates an expense.

Every purchase-side financial mutation locks its supplier first. Allocation replacement
then locks affected bills in ascending ID order. Recurring generation locks its schedule
before entering the supplier workflow; no supplier operation locks a schedule. This
serializes balance checks across payments, credits, and refunds. Occurrence uniqueness
and atomic schedule advancement prevent duplicate generation and lost retries.

The existing document-tax calculator and integer money conventions are reused. Credits
use cumulative source-quantity differences, preserving rounding across partial credits.
Supplier credits retain purchase tax snapshots rather than recomputing historical tax.

Cash reports use dated cash movements. Purchase-cost reports use dated documents.
Unapplied balances remain visible separately from outstanding bills. There is no general
ledger, bank-account system, payment initiation, or foreign-currency allocation.

## Consequences

Customer refunds and credit notes against paid customer invoices are a later Receivables
project. Purchase-order and bank-feed modules integrate through versioned host contracts;
they do not own duplicate suppliers, bills, or financial settlement records.

Table/morph cutover is an explicit migration, with writes paused and workers restarted.
The migration preserves IDs and public links and rewrites stored polymorphic values.
SQLite, MySQL, and PostgreSQL are supported, including concurrent settlement checks on
the server databases.
