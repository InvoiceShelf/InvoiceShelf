import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mapProposalFields } from '../../resources/scripts/api/services/proposal-fields.ts'

test('quote requests map document keys without rewriting customer-entered text', () => {
  const draft = {
    estimate_number: 'QUO-17',
    estimate_date: '2026-09-27',
    notes: 'Keep this estimate_number reference.',
    items: [
      {
        estimate_id: 17,
        description: 'estimate',
        custom_fields: [{ slug: 'estimate_code', value: 'original estimate' }],
      },
    ],
  }
  const wire = mapProposalFields(draft, 'estimate', 'quote')
  assert.equal(wire.quote_number, 'QUO-17')
  assert.equal(wire.items[0].quote_id, 17)
  assert.equal(wire.notes, draft.notes)
  assert.deepEqual(wire.items[0].custom_fields, draft.items[0].custom_fields)
  assert.deepEqual(mapProposalFields(wire, 'quote', 'estimate'), draft)
  assert.equal(draft.estimate_number, 'QUO-17')
})

test('quote list responses and sort fields use the shared view model', () => {
  assert.deepEqual(
    mapProposalFields(
      {
        data: [{ quote_number: 'QUO-2', quote_pdf_url: '/quotes/pdf/token' }],
        meta: { quote_total_count: 1 },
      },
      'quote',
      'estimate',
    ),
    {
      data: [
        { estimate_number: 'QUO-2', estimate_pdf_url: '/quotes/pdf/token' },
      ],
      meta: { estimate_total_count: 1 },
    },
  )
  assert.equal(
    mapProposalFields({ orderByField: 'estimate_date' }, 'estimate', 'quote')
      .orderByField,
    'quote_date',
  )
})

test('existing estimate payloads pass through unchanged', () => {
  const payload = { estimate_number: 'EST-1', customFields: [], notes: null }
  assert.equal(mapProposalFields(payload, 'estimate', 'estimate'), payload)
  assert.equal(mapProposalFields(null, 'estimate', 'quote'), null)
})

test('non-plain values preserve their JSON serialization', () => {
  const date = new Date('2026-09-27T10:00:00Z')
  assert.equal(
    mapProposalFields({ value: date }, 'estimate', 'quote').value,
    date,
  )
})

test('related records and custom-field payloads keep their own identities', () => {
  const input = {
    customer: { estimates_count: 8, quotes_count: 2 },
    customFields: [{ value: { estimate_number: 'external' } }],
  }
  const output = mapProposalFields(input, 'estimate', 'quote')
  assert.deepEqual(output, input)
  assert.equal(output.customer, input.customer)
  assert.equal(output.customFields, input.customFields)
})
