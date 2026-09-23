import { computed, inject, provide, useId } from 'vue'
import type { ComputedRef, InjectionKey, Ref } from 'vue'

/**
 * Ties a form group's visible label, help text and error to the control inside
 * it, so assistive technology reads them together.
 *
 * `BaseInputGroup` provides the context; each control calls `useFormField()`.
 * The first control to claim the group takes its id (the label's `for` points
 * at it); any further control in the same group is named by `aria-labelledby`
 * instead, so ids never repeat.
 */
export interface FormFieldContext {
  controlId: string
  labelId: string
  hasLabel: Ref<boolean>
  describedBy: ComputedRef<string | undefined>
  invalid: ComputedRef<boolean>
  required: ComputedRef<boolean>
  claim: () => boolean
}

const FORM_FIELD: InjectionKey<FormFieldContext> = Symbol('form-field')

interface GroupState {
  hasLabel: Ref<boolean>
  hasHelp: Ref<boolean>
  hasHint: Ref<boolean>
  hasError: Ref<boolean>
  required: Ref<boolean>
}

export function provideFormField(state: GroupState) {
  const base = `field-${useId()}`
  let claimed = false

  const ids = {
    controlId: `${base}-control`,
    labelId: `${base}-label`,
    helpId: `${base}-help`,
    hintId: `${base}-hint`,
    errorId: `${base}-error`,
  }

  const context: FormFieldContext = {
    controlId: ids.controlId,
    labelId: ids.labelId,
    hasLabel: state.hasLabel,
    describedBy: computed(() => {
      const parts = [
        state.hasError.value ? ids.errorId : null,
        state.hasHelp.value ? ids.helpId : null,
        state.hasHint.value ? ids.hintId : null,
      ].filter(Boolean)

      return parts.length ? parts.join(' ') : undefined
    }),
    invalid: computed(() => state.hasError.value),
    required: computed(() => state.required.value),
    claim: () => {
      if (claimed) {
        return false
      }

      claimed = true

      return true
    },
  }

  provide(FORM_FIELD, context)

  return ids
}

interface FieldOptions {
  /** The control's own invalid flag, merged with the group's */
  invalid?: () => boolean
  /**
   * Controls with no native labelable element (a listbox button, a combobox
   * div, a switch) are named by the label's id rather than by `for`.
   */
  labelledBy?: boolean
}

/**
 * The attributes a control spreads onto its focusable element. Anything the
 * caller passes explicitly through `$attrs` should be spread after these, so it
 * wins.
 */
export function useFormField(options: FieldOptions = {}) {
  const field = inject(FORM_FIELD, null)
  const ownsId = field ? field.claim() : false

  const attrs = computed<Record<string, string | undefined>>(() => {
    const invalid = (options.invalid?.() ?? false) || (field?.invalid.value ?? false)

    if (!field) {
      return { 'aria-invalid': invalid ? 'true' : undefined }
    }

    const byLabel = options.labelledBy || !ownsId

    return {
      // Widgets named by aria-labelledby keep the ids their library gives them
      id: ownsId && !options.labelledBy ? field.controlId : undefined,
      'aria-labelledby': byLabel && field.hasLabel.value ? field.labelId : undefined,
      'aria-describedby': field.describedBy.value,
      'aria-invalid': invalid ? 'true' : undefined,
      'aria-required': field.required.value ? 'true' : undefined,
    }
  })

  return { attrs, inGroup: !!field }
}

/**
 * After a failed submit, move focus to the first field marked invalid so a
 * keyboard or screen reader user lands on the problem.
 */
export function focusFirstInvalid(root: ParentNode = document): void {
  requestAnimationFrame(() => {
    root.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
  })
}

/**
 * Does the same for every form in the app, once: submit handlers validate and
 * the errors render a frame or two later, so look then. A field that is
 * itself invalid keeps focus, so pressing Enter in it does not move you.
 */
export function focusInvalidAfterSubmit(): void {
  document.addEventListener(
    'submit',
    (event) => {
      const form = event.target

      if (!(form instanceof HTMLFormElement)) {
        return
      }

      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          const invalid = form.isConnected
            ? form.querySelector<HTMLElement>('[aria-invalid="true"]')
            : null
          const active = document.activeElement

          if (invalid && active?.getAttribute('aria-invalid') !== 'true') {
            invalid.focus()
          }
        })
      })
    },
    true,
  )
}
