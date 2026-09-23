<template>
  <ContentPlaceholder v-if="contentLoading">
    <ContentPlaceholderBox
      :rounded="true"
      class="w-full"
      style="height: 200px"
    />
  </ContentPlaceholder>
  <div
    v-else
    class="box-border w-full text-sm leading-8 text-start bg-surface border border-line-light rounded-xl shadow min-h-[200px] overflow-hidden"
  >
    <div v-if="editor" class="editor-content">
      <!--
        One toolbar at every width; on phones it scrolls sideways. It is a
        single tab stop, and the arrow keys move between its buttons.
      -->
      <!-- eslint-disable-next-line vuejs-accessibility/no-static-element-interactions -->
      <div
        role="toolbar"
        :aria-label="$t('general.editor.toolbar')"
        :aria-controls="contentId"
        class="flex p-2 overflow-x-auto border-b border-line-light md:overflow-visible"
        @keydown="onToolbarKeydown"
      >
        <div class="flex gap-1 md:flex-wrap">
          <button
            v-for="(button, i) in editorButtons"
            :key="button.name"
            type="button"
            data-toolbar-item
            :tabindex="i === toolbarIndex ? 0 : -1"
            :aria-label="$t(`general.editor.${button.name}`)"
            :title="$t(`general.editor.${button.name}`)"
            :aria-pressed="button.isActive ? button.isActive() : undefined"
            class="
              flex items-center justify-center shrink-0 min-w-8 h-8 p-1 rounded-md
              hover:bg-surface-tertiary aria-pressed:bg-surface-tertiary aria-pressed:text-heading
              focus:outline-hidden focus-visible:ring-2 focus-visible:ring-focus
            "
            @click="button.action"
            @focus="toolbarIndex = i"
          >
            <component
              :is="button.icon"
              v-if="button.icon"
              class="w-4 h-4 fill-current text-body"
              aria-hidden="true"
            />
            <span v-else-if="button.text" class="px-1 text-sm font-medium text-body" aria-hidden="true">
              {{ button.text }}
            </span>
          </button>
          <ExtensionSlot
            name="rich-editor-toolbar-actions"
            :context="editorContext"
          />
        </div>
      </div>
      <editor-content
        :editor="editor"
        class="box-border relative w-full text-sm leading-8 text-start editor__content"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted, watch, markRaw, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Component } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import {
  BoldIcon,
  CodingIcon,
  ItalicIcon,
  ListIcon,
  ListUlIcon,
  ParagraphIcon,
  QuoteIcon,
  StrikethroughIcon,
  UndoIcon,
  RedoIcon,
  CodeBlockIcon,
  MenuCenterIcon,
} from '@/scripts/components/editor/icons/index'
import {
  Bars3BottomLeftIcon,
  Bars3BottomRightIcon,
  Bars3Icon,
  LinkIcon,
} from '@heroicons/vue/24/solid'
import { ContentPlaceholder, ContentPlaceholderBox } from '../layout'
import ExtensionSlot from '@/scripts/extensions/ExtensionSlot.vue'
import type { RichEditorContext } from '@/scripts/extensions/types'
import { useFormField } from '@/scripts/composables/use-form-field'

interface EditorButton {
  /** Also the key of its name under general.editor */
  name: string
  icon?: Component
  text?: string
  action: () => void
  /** For formatting that toggles: whether it applies at the cursor */
  isActive?: () => boolean
}

interface Props {
  modelValue?: string
  contentLoading?: boolean
  /** The editing area's name, when no surrounding form group labels it */
  label?: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  contentLoading: false,
  label: '',
})

const emit = defineEmits<Emits>()

const { t } = useI18n()

const contentId = `editor-${useId()}`

// The editing area is a multi-line text box, named by the form group around it
const { attrs: fieldAttrs } = useFormField({ labelledBy: true })

function contentAttributes(): Record<string, string> {
  const attrs: Record<string, string> = {
    id: contentId,
    role: 'textbox',
    'aria-multiline': 'true',
  }

  for (const [key, value] of Object.entries(fieldAttrs.value)) {
    if (value !== undefined && key !== 'id') {
      attrs[key] = value
    }
  }

  if (props.label) {
    attrs['aria-label'] = props.label
  }

  return attrs
}

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      link: { openOnClick: false },
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph'],
      alignments: ['left', 'right', 'center', 'justify'],
    }),
  ],
  editorProps: {
    attributes: contentAttributes(),
  },
  onUpdate: ({ editor: ed }) => {
    emit('update:modelValue', ed.getHTML())
  },
})

watch([fieldAttrs, () => props.label], () => {
  editor.value?.setOptions({ editorProps: { attributes: contentAttributes() } })
})

const toolbarIndex = ref<number>(0)

function onToolbarKeydown(event: KeyboardEvent): void {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
    return
  }

  const toolbar = event.currentTarget as HTMLElement
  const buttons = Array.from(toolbar.querySelectorAll<HTMLButtonElement>('[data-toolbar-item]'))
  const current = buttons.indexOf(document.activeElement as HTMLButtonElement)

  if (current === -1) {
    return
  }

  event.preventDefault()

  const last = buttons.length - 1
  const next = {
    ArrowLeft: current === 0 ? last : current - 1,
    ArrowRight: current === last ? 0 : current + 1,
    Home: 0,
    End: last,
  }[event.key] as number

  toolbarIndex.value = next
  buttons[next].focus()
}

const isActive = (name: string, attrs?: Record<string, unknown>) => () => !!editor.value?.isActive(name, attrs)
const isAligned = (align: string) => () => !!editor.value?.isActive({ textAlign: align })

const editorButtons = ref<EditorButton[]>([
  { name: 'bold', icon: markRaw(BoldIcon) as Component, isActive: isActive('bold'), action: () => editor.value?.chain().focus().toggleBold().run() },
  { name: 'italic', icon: markRaw(ItalicIcon) as Component, isActive: isActive('italic'), action: () => editor.value?.chain().focus().toggleItalic().run() },
  { name: 'strike', icon: markRaw(StrikethroughIcon) as Component, isActive: isActive('strike'), action: () => editor.value?.chain().focus().toggleStrike().run() },
  { name: 'code', icon: markRaw(CodingIcon) as Component, isActive: isActive('code'), action: () => editor.value?.chain().focus().toggleCode().run() },
  { name: 'paragraph', icon: markRaw(ParagraphIcon) as Component, action: () => editor.value?.chain().focus().setParagraph().run() },
  { name: 'h1', text: 'H1', isActive: isActive('heading', { level: 1 }), action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run() },
  { name: 'h2', text: 'H2', isActive: isActive('heading', { level: 2 }), action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run() },
  { name: 'h3', text: 'H3', isActive: isActive('heading', { level: 3 }), action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run() },
  { name: 'bulletList', icon: markRaw(ListUlIcon) as Component, isActive: isActive('bulletList'), action: () => editor.value?.chain().focus().toggleBulletList().run() },
  { name: 'orderedList', icon: markRaw(ListIcon) as Component, isActive: isActive('orderedList'), action: () => editor.value?.chain().focus().toggleOrderedList().run() },
  { name: 'blockquote', icon: markRaw(QuoteIcon) as Component, isActive: isActive('blockquote'), action: () => editor.value?.chain().focus().toggleBlockquote().run() },
  { name: 'codeBlock', icon: markRaw(CodeBlockIcon) as Component, isActive: isActive('codeBlock'), action: () => editor.value?.chain().focus().toggleCodeBlock().run() },
  { name: 'undo', icon: markRaw(UndoIcon) as Component, action: () => editor.value?.chain().focus().undo().run() },
  { name: 'redo', icon: markRaw(RedoIcon) as Component, action: () => editor.value?.chain().focus().redo().run() },
  { name: 'alignLeft', icon: markRaw(Bars3BottomLeftIcon) as Component, isActive: isAligned('left'), action: () => editor.value?.chain().focus().setTextAlign('left').run() },
  { name: 'alignRight', icon: markRaw(Bars3BottomRightIcon) as Component, isActive: isAligned('right'), action: () => editor.value?.chain().focus().setTextAlign('right').run() },
  { name: 'alignJustify', icon: markRaw(Bars3Icon) as Component, isActive: isAligned('justify'), action: () => editor.value?.chain().focus().setTextAlign('justify').run() },
  { name: 'alignCenter', icon: markRaw(MenuCenterIcon) as Component, isActive: isAligned('center'), action: () => editor.value?.chain().focus().setTextAlign('center').run() },
  {
    name: 'addLink',
    icon: markRaw(LinkIcon) as Component,
    isActive: isActive('link'),
    action: () => {
      const url = window.prompt(t('general.editor.link_url'))
      if (url) {
        editor.value?.chain().focus().setLink({ href: url }).run()
      }
    },
  },
])

const editorContext: RichEditorContext = {
  getHtml: () => editor.value?.getHTML() ?? '',
  insertContent: (content: string) => {
    editor.value?.chain().focus().insertContent(content).run()
  },
  replaceContent: (content: string) => {
    editor.value?.chain().focus().selectAll().deleteSelection().insertContent(content).run()
  },
}

watch(
  () => props.modelValue,
  (newValue: string) => {
    if (editor.value && newValue !== editor.value.getHTML()) {
      editor.value.commands.setContent(newValue, { emitUpdate: false })
    }
  }
)

onUnmounted(() => {
  if (editor.value) {
    editor.value.destroy()
  }
})
</script>

<style>
@reference "../../../css/invoiceshelf.css";

.ProseMirror {
  min-height: 200px;
  padding: 8px 12px;
  outline: none;
  @apply rounded-xl rounded-ss-none rounded-se-none border border-transparent;

  h1 {
    font-size: 2em;
    font-weight: bold;
  }

  h2 {
    font-size: 1.5em;
    font-weight: bold;
  }

  h3 {
    font-size: 1.17em;
    font-weight: bold;
  }

  ul {
    padding: 0 1rem;
    list-style: disc !important;
  }

  ol {
    padding: 0 1rem;
    list-style: auto !important;
  }

  blockquote {
    padding-left: 1rem;
    border-left: 2px solid var(--color-line-default);
  }

  code {
    background-color: rgba(97, 97, 97, 0.1);
    color: #616161;
    border-radius: 0.4rem;
    font-size: 0.9rem;
    padding: 0.1rem 0.3rem;
  }

  pre {
    background: #0d0d0d;
    color: #fff;
    font-family: 'JetBrainsMono', monospace;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;

    code {
      color: inherit;
      padding: 0;
      background: none;
      font-size: 0.8rem;
    }
  }

  a {
    color: var(--color-primary-500);
    text-decoration: underline;
  }
}

.ProseMirror:focus {
  @apply border border-focus ring-1 ring-focus;
}
</style>
