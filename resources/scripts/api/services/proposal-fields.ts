export type ProposalKind = 'estimate' | 'quote'

const relatedData = new Set([
  'customer',
  'company',
  'creator',
  'currency',
  'fields',
  'customFields',
  'custom_fields',
])

/** Map document field names at the boundary, leaving all user-entered text intact. */
export function mapProposalFields<T>(
  value: T,
  from: ProposalKind,
  to: ProposalKind,
): T {
  if (from === to || value === null || typeof value !== 'object') return value
  if (
    !Array.isArray(value) &&
    Object.getPrototypeOf(value) !== Object.prototype &&
    Object.getPrototypeOf(value) !== null
  )
    return value
  if (Array.isArray(value))
    return value.map((entry) => mapProposalFields(entry, from, to)) as T
  return Object.fromEntries(
    Object.entries(value).map(([key, entry]) => [
      key.replaceAll(from, to),
      relatedData.has(key)
        ? entry
        : key === 'orderByField' && typeof entry === 'string'
          ? entry.replaceAll(from, to)
          : mapProposalFields(entry, from, to),
    ]),
  ) as T
}
