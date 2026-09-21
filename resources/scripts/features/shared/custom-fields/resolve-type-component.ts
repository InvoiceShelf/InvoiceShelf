import { defineAsyncComponent, type Component } from 'vue'

/**
 * Every input component a custom field type can render as, keyed by the path
 * Vite resolves at build time.
 *
 * A glob rather than a template-literal `import()` on purpose: the set is
 * known up front, so an unrecognised type is a missing key we can answer for,
 * not a chunk request that fails at runtime.
 */
const typeComponents = import.meta.glob('./types/*Type.vue')

/**
 * The input a field of this type is edited with, or null when the type has no
 * component -- an empty type on a half-filled form, or a value written by an
 * older release whose component has since gone.
 */
export function resolveCustomFieldTypeComponent(
  type: string | null | undefined
): Component | null {
  if (!type) {
    return null
  }

  const loader = typeComponents[`./types/${type}Type.vue`]

  if (!loader) {
    return null
  }

  return defineAsyncComponent(loader as () => Promise<Component>)
}
