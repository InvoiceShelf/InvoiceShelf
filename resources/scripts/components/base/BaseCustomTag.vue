<script lang="ts">
import { h, defineComponent } from 'vue'

export default defineComponent({
  name: 'BaseCustomTag',
  props: {
    tag: {
      type: String,
      default: 'button',
    },
  },
  setup(props, { slots, attrs }) {
    return () => h(props.tag, attrs, slots)
  },
})
</script>
