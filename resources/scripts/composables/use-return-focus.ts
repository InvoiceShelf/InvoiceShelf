import { watch } from 'vue'

/**
 * Remembers what had focus when a dialog opens and puts focus back there once
 * it has closed. Headless UI's static dialogs lose this on their own: the
 * focus trap lets go while the page behind is still inert, so the browser
 * drops focus on <body>.
 *
 * Call `restoreFocus` from the transition's `after-leave`.
 */
export function useReturnFocus(isOpen: () => boolean) {
  let returnTo: HTMLElement | null = null

  watch(
    isOpen,
    (open) => {
      if (!open) {
        return
      }

      const active = document.activeElement

      returnTo = active instanceof HTMLElement && active !== document.body ? active : null
    },
    { immediate: true },
  )

  function restoreFocus(): void {
    const target = returnTo

    returnTo = null

    // After the leave transition the dialog is still mounted, holding focus;
    // check once it has gone, and leave focus alone if something claimed it
    setTimeout(() => {
      const current = document.activeElement

      if (target?.isConnected && (!current || current === document.body)) {
        target.focus({ preventScroll: true })
      }
    })
  }

  return { restoreFocus }
}
