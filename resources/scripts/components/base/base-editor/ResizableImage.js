import Image from '@tiptap/extension-image'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import ResizableImageView from './ResizableImageView.vue'

/**
 * Bild-Extension mit Ziehgriff.
 *
 * Die Breite wird bewusst als HTML-Attribut `width` gespeichert und nicht als
 * Inline-Style: PdfHtmlSanitizer entfernt `style` weiterhin ersatzlos, waehrend
 * `width` als schlichte Zahl durchgelassen wird. Dompdf wertet das Attribut aus,
 * die gezogene Groesse landet also unveraendert im PDF.
 */
export default Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      width: {
        default: null,
        parseHTML: (element) => {
          const width = parseInt(element.getAttribute('width') || '', 10)
          return Number.isFinite(width) && width > 0 ? width : null
        },
        renderHTML: (attributes) =>
          attributes.width ? { width: attributes.width } : {},
      },
    }
  },

  addNodeView() {
    return VueNodeViewRenderer(ResizableImageView)
  },
})
