import { VTooltip } from 'v-tooltip'
import type { App, Directive } from 'vue'

/**
 * Install the v-tooltip directive on the given Vue app instance.
 */
export function installTooltipDirective(app: App): void {
  app.directive('tooltip', VTooltip as Directive)
}
