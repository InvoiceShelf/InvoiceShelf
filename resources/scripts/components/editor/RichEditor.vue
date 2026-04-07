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
    class="box-border w-full text-sm leading-8 text-left bg-surface border border-line-light rounded-xl shadow min-h-[200px] overflow-hidden"
  >
    <div v-if="editor" class="editor-content">
      <div class="flex justify-end p-2 border-b border-line-light md:hidden">
        <BaseDropdown width-class="w-48">
          <template #activator>
            <div
              class="flex items-center justify-center w-6 h-6 ml-2 text-sm text-heading bg-surface rounded-xs md:h-9 md:w-9"
            >
              <EllipsisVerticalIcon class="w-6 h-6 text-body" />
            </div>
          </template>
          <div class="flex flex-wrap space-x-1">
            <button
              v-for="button in editorButtons"
              type="button"
              :key="button.name"
              class="p-1 rounded hover:bg-surface-tertiary"
              @click="button.action"
            >
              <component
                :is="button.icon"
                v-if="button.icon"
                class="w-4 h-4 text-body fill-current text-body"
              />
              <span v-else-if="button.text" class="px-1 text-sm font-medium text-body">
                {{ button.text }}
              </span>
            </button>
          </div>
        </BaseDropdown>
      </div>
      <div class="hidden p-2 border-b border-line-light md:flex">
        <div class="flex flex-wrap space-x-1">
          <button
              v-for="button in editorButtons"
              type="button"
              :key="button.name"
              class="p-1 rounded hover:bg-surface-tertiary"
              @click="button.action"
            >
              <component
                :is="button.icon"
                v-if="button.icon"
                class="w-4 h-4 text-body fill-current text-body"
              />
              <span v-else-if="button.text" class="px-1 text-sm font-medium text-body">
                {{ button.text }}
              </span>
            </button>
        </div>
      </div>
      <editor-content
        :editor="editor"
        class="box-border relative w-full text-sm leading-8 text-left editor__content"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted, watch, markRaw } from 'vue'
import type { Component } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import { EllipsisVerticalIcon } from '@heroicons/vue/24/outline'
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

interface EditorButton {
  name: string
  icon?: Component
  text?: string
  action: () => void
}

interface Props {
  modelValue?: string
  contentLoading?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  contentLoading: false,
})

const emit = defineEmits<Emits>()

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
  onUpdate: ({ editor: ed }) => {
    emit('update:modelValue', ed.getHTML())
  },
})

const editorButtons = ref<EditorButton[]>([
  { name: 'bold', icon: markRaw(BoldIcon) as Component, action: () => editor.value?.chain().focus().toggleBold().run() },
  { name: 'italic', icon: markRaw(ItalicIcon) as Component, action: () => editor.value?.chain().focus().toggleItalic().run() },
  { name: 'strike', icon: markRaw(StrikethroughIcon) as Component, action: () => editor.value?.chain().focus().toggleStrike().run() },
  { name: 'code', icon: markRaw(CodingIcon) as Component, action: () => editor.value?.chain().focus().toggleCode().run() },
  { name: 'paragraph', icon: markRaw(ParagraphIcon) as Component, action: () => editor.value?.chain().focus().setParagraph().run() },
  { name: 'h1', text: 'H1', action: () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run() },
  { name: 'h2', text: 'H2', action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run() },
  { name: 'h3', text: 'H3', action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run() },
  { name: 'bulletList', icon: markRaw(ListUlIcon) as Component, action: () => editor.value?.chain().focus().toggleBulletList().run() },
  { name: 'orderedList', icon: markRaw(ListIcon) as Component, action: () => editor.value?.chain().focus().toggleOrderedList().run() },
  { name: 'blockquote', icon: markRaw(QuoteIcon) as Component, action: () => editor.value?.chain().focus().toggleBlockquote().run() },
  { name: 'codeBlock', icon: markRaw(CodeBlockIcon) as Component, action: () => editor.value?.chain().focus().toggleCodeBlock().run() },
  { name: 'undo', icon: markRaw(UndoIcon) as Component, action: () => editor.value?.chain().focus().undo().run() },
  { name: 'redo', icon: markRaw(RedoIcon) as Component, action: () => editor.value?.chain().focus().redo().run() },
  { name: 'alignLeft', icon: markRaw(Bars3BottomLeftIcon) as Component, action: () => editor.value?.chain().focus().setTextAlign('left').run() },
  { name: 'alignRight', icon: markRaw(Bars3BottomRightIcon) as Component, action: () => editor.value?.chain().focus().setTextAlign('right').run() },
  { name: 'alignJustify', icon: markRaw(Bars3Icon) as Component, action: () => editor.value?.chain().focus().setTextAlign('justify').run() },
  { name: 'alignCenter', icon: markRaw(MenuCenterIcon) as Component, action: () => editor.value?.chain().focus().setTextAlign('center').run() },
  {
    name: 'addLink',
    icon: markRaw(LinkIcon) as Component,
    action: () => {
      const url = window.prompt('URL')
      if (url) {
        editor.value?.chain().focus().setLink({ href: url }).run()
      }
    },
  },
])

watch(
  () => props.modelValue,
  (newValue: string) => {
    if (editor.value && newValue !== editor.value.getHTML()) {
      editor.value.commands.setContent(newValue, false)
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
  @apply rounded-xl rounded-tl-none rounded-tr-none border border-transparent;

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
  @apply border border-primary-400 ring-primary-400;
}
</style>
