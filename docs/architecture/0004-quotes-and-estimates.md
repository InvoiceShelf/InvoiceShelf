# Quotes and estimates

Quotes are a separate v3 sales document, with their own menu, permissions,
numbering, preferences and customer-portal routes. `quotes` and `quote_items`
store their records; estimates keep their existing tables. Applied taxes use the
existing snapshot table with distinct `quote_id` and `quote_item_id` owners.

`SalesProposal` and `SalesProposalItem` share model behavior, while each concrete
model owns its table and stable morph alias. The abstract bases never have rows or
morph identities. `SalesProposalService` shares transactional creation, replacement,
duplication, tax/item/field copying, PDF preparation and invoice conversion.
`EstimateService` retains its established entry points; `QuoteService` supplies the
quote model and email port, plus guarded customer responses and view tracking.

The web and mobile clients reuse the existing proposal editor/list/detail views.
Quote route wrappers supply a context and use an independent Pinia store. The API
publishes native `quote_date`, `quote_number` and `quote_pdf_url` fields. A small
boundary mapper adapts them to the established editor view model, including sort
fields. It leaves free-form text, custom-field values and related records intact.

Quotes have independent company and preferred-template settings. Migration copies
existing address layouts and custom mail text without rewriting branding, maps
known document placeholders, and seeds the `QUO` number series. Owner, Manager and
Read only presets gain the corresponding quote abilities; custom roles require
explicit grants. Drafts remain private. Customer responses accept only Accepted or
Rejected while the quote is open, and viewing a quote notifies the issuer at most
once. Creation always saves a draft; sending uses its separately authorized route.

PDFs share the built-in layout code, render quote-specific labels, and support
separate custom designs through `make:template --type=quote`. The quote preview
PNGs were rendered from actual PDFs using isolated demonstration data.

Validation covers storage isolation, permission boundaries, numbering, totals,
exchange rates, taxes/custom fields, rollback, cloning/conversion, PDF/email token
identity, customer responses, and independent preferences. `pnpm test:unit` exercises
the client field mapping; browser checks cover the actual staff and portal flows.
