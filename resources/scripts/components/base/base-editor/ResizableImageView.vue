<template>
  <NodeViewWrapper
    as="span"
    class="resizable-image"
    :class="{ 'is-selected': selected }"
  >
    <img
      ref="img"
      :src="node.attrs.src"
      :alt="node.attrs.alt"
      :title="node.attrs.title"
      :width="node.attrs.width || null"
      draggable="false"
    />
    <span
      class="resize-handle"
      title="Ziehen zum Ändern der Größe"
      @mousedown="startResize"
    />
  </NodeViewWrapper>
</template>

<script>
import { NodeViewWrapper } from '@tiptap/vue-3'

// Obergrenze in Pixeln. Entspricht der nutzbaren Breite einer A4-Seite im
// PDF - breiter zu ziehen wuerde im Dokument abgeschnitten.
const MAX_WIDTH = 650
const MIN_WIDTH = 40

export default {
  components: { NodeViewWrapper },

  // Diese Props reicht TipTap jeder NodeView herein.
  props: {
    node: { type: Object, required: true },
    updateAttributes: { type: Function, required: true },
    selected: { type: Boolean, default: false },
  },

  data() {
    return { startX: 0, startWidth: 0 }
  },

  beforeUnmount() {
    this.stopResize()
  },

  methods: {
    startResize(event) {
      event.preventDefault()
      this.startX = event.clientX
      this.startWidth = this.$refs.img.getBoundingClientRect().width
      window.addEventListener('mousemove', this.onResize)
      window.addEventListener('mouseup', this.stopResize)
    },

    onResize(event) {
      const width = Math.round(
        Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, this.startWidth + (event.clientX - this.startX)))
      )
      this.updateAttributes({ width })
    },

    stopResize() {
      window.removeEventListener('mousemove', this.onResize)
      window.removeEventListener('mouseup', this.stopResize)
    },
  },
}
</script>
