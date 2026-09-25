/**
 * The ability catalogue as the role editors use it: a company owner's role
 * editor and the super administrator's preset editor. A selection is a plain
 * list of ability keys; everything else is derived from it.
 */

/** One catalogue entry, as the abilities endpoints return it. */
export interface AbilityDefinition {
  name: string
  ability: string
  model: string | null
  depends_on?: string[]
}

export interface AbilityGroup {
  label: string
  items: AbilityDefinition[]
}

export type Selection = 'all' | 'view' | 'none'

/** Read-only abilities: seeing records, reports and the dashboard. */
export function isViewAbility(key: string): boolean {
  return key.startsWith('view-') || key === 'dashboard'
}

/**
 * The catalogue grouped by the model an ability is about (`Common` for none),
 * in catalogue order.
 */
export function groupAbilities(catalogue: AbilityDefinition[]): AbilityGroup[] {
  const groups = new Map<string, AbilityDefinition[]>()

  for (const entry of catalogue) {
    const model = entry.model ?? ''
    const label = model ? model.substring(model.lastIndexOf('\\') + 1) : 'Common'
    groups.set(label, [...(groups.get(label) ?? []), entry])
  }

  return [...groups.entries()].map(([label, items]) => ({ label, items }))
}

/**
 * The selection with everything its abilities depend on. Keys outside the
 * catalogue (a switched-off module's) are kept as they are.
 */
export function withDependencies(selected: string[], catalogue: AbilityDefinition[]): string[] {
  const byKey = new Map(catalogue.map((entry) => [entry.ability, entry]))
  const chosen = new Set(selected)
  const queue = [...selected]

  while (queue.length) {
    const key = queue.pop() as string
    for (const dependency of byKey.get(key)?.depends_on ?? []) {
      if (!chosen.has(dependency)) {
        chosen.add(dependency)
        queue.push(dependency)
      }
    }
  }

  const known = catalogue.map((entry) => entry.ability).filter((key) => chosen.has(key))
  const unknown = selected.filter((key) => !byKey.has(key))

  return [...known, ...unknown]
}

/**
 * Abilities that cannot be unticked because a ticked ability depends on them.
 */
export function lockedBy(selected: string[], catalogue: AbilityDefinition[]): Set<string> {
  const chosen = new Set(selected)
  const locked = new Set<string>()

  for (const entry of catalogue) {
    if (chosen.has(entry.ability)) {
      for (const dependency of entry.depends_on ?? []) locked.add(dependency)
    }
  }

  return locked
}

/**
 * Give a set of abilities (a group, or the whole catalogue) everything, only
 * the read-only ones, or nothing, leaving the rest of the selection alone.
 */
export function applySelection(
  selected: string[],
  items: AbilityDefinition[],
  selection: Selection,
  catalogue: AbilityDefinition[]
): string[] {
  const keys = new Set(items.map((item) => item.ability))
  const kept = selected.filter((key) => !keys.has(key))
  const added =
    selection === 'all'
      ? items.map((item) => item.ability)
      : selection === 'view'
        ? items.filter((item) => isViewAbility(item.ability)).map((item) => item.ability)
        : []

  return withDependencies([...kept, ...added], catalogue)
}

/** Whether a group mixes read-only and other abilities, so "View" means something. */
export function hasViewAndMore(items: AbilityDefinition[]): boolean {
  return (
    items.some((item) => isViewAbility(item.ability)) &&
    items.some((item) => !isViewAbility(item.ability))
  )
}
