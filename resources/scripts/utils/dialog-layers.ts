import type { InjectionKey, Ref } from 'vue'

/**
 * Layers that other code appends to <body> while a dialog is open: flatpickr's
 * calendar, tooltips, the toasts, and a module's own teleported dialog. Reka's
 * own menus, selects and popovers are already known to the dialog as nested
 * layers; these are not, so a click or focus in them would otherwise close it.
 */
const FOREIGN_LAYERS = [
  '.flatpickr-calendar',
  '.v-popper__popper',
  '[data-app-layer]',
  'body > [role="dialog"]',
].join(', ')

export function isForeignLayer(target: EventTarget | null): boolean {
  return target instanceof Element && target.closest(FOREIGN_LAYERS) !== null
}

type OutsideEvent = CustomEvent<{ originalEvent: Event }>

/** For a dialog's pointer-down-outside, focus-outside and interact-outside */
export function keepForeignLayers(event: OutsideEvent): void {
  if (isForeignLayer(event.detail.originalEvent.target)) {
    event.preventDefault()
  }
}

/**
 * Provided by dialogs and sheets: the dialog's own element. A date picker
 * inside one renders its calendar in place and a select opens its list there
 * rather than on <body>, so they stay within the dialog's focus trap and a
 * screen reader finds them inside the dialog.
 */
export const DIALOG_LAYER: InjectionKey<Ref<HTMLElement | null>> = Symbol('dialog-layer')

/*
 * Reka hands focus back to whatever had it when a dialog opened. A dialog
 * opened from a menu item or a popover outlives them, so by the time it closes
 * that item is gone and focus would fall to the page. Such items stand in for
 * the button that opened their menu or popover.
 */
function standIn(element: HTMLElement): HTMLElement {
  const content = element.closest('[data-reka-popper-content-wrapper]')?.firstElementChild

  if (!(content instanceof HTMLElement)) {
    return element
  }

  const labelledBy = content.getAttribute('aria-labelledby')
  const trigger = (labelledBy ? document.getElementById(labelledBy) : null)
    ?? (content.id ? document.querySelector(`[aria-controls="${CSS.escape(content.id)}"]`) : null)

  return trigger instanceof HTMLElement ? trigger : element
}

// An item removed with focus on it leaves focus on <body> without an event,
// so the last element to take focus is kept, already swapped for its stand-in
let lastFocused: HTMLElement | null = null

document.addEventListener('focusin', (event) => {
  if (event.target instanceof HTMLElement) {
    lastFocused = standIn(event.target)
  }
}, true)

/** For a dialog's open-auto-focus and close-auto-focus */
export function useFocusReturn(): { remember: () => void; restore: (event: Event) => void } {
  let target: HTMLElement | null = null

  return {
    remember(): void {
      const active = document.activeElement

      target = active instanceof HTMLElement && active !== document.body ? standIn(active) : lastFocused
    },
    restore(event: Event): void {
      if (target?.isConnected) {
        event.preventDefault()
        target.focus()
      }

      target = null
    },
  }
}
