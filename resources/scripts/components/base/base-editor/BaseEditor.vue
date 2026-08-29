<template>
  <BaseContentPlaceholders v-if="contentLoading">
    <BaseContentPlaceholdersBox
      :rounded="true"
      class="w-full"
      style="height: 200px"
    />
  </BaseContentPlaceholders>
  <div
    v-else
    class="box-border w-full text-sm leading-8 text-left bg-white border border-gray-200 rounded-md min-h-[200px] overflow-hidden"
  >
    <div v-if="editor" class="editor-content">
      <div class="flex justify-end p-2 border-b border-gray-200 md:hidden">
        <BaseDropdown width-class="w-48">
          <template #activator>
            <div
              class="flex items-center justify-center w-6 h-6 ml-2 text-sm text-black bg-white rounded-xs md:h-9 md:w-9"
            >
              <EllipsisVerticalIcon class="w-6 h-6 text-gray-600" />
            </div>
          </template>
          <div class="flex flex-wrap space-x-1">
            <button
              v-for="button in editorButtons"
              type="button"
              :key="button.name"
              class="p-1 rounded hover:bg-gray-100"
              @click="button.action"
            >
              <component
                :is="button.icon"
                v-if="button.icon"
                class="w-4 h-4 text-gray-700 fill-gray-700"
              />
              <span v-else-if="button.text" class="px-1 text-sm font-medium text-gray-600">
                {{ button.text }}
              </span>
            </button>
          </div>
        </BaseDropdown>
      </div>
      <div class="hidden p-2 border-b border-gray-200 md:flex">
        <div class="flex flex-wrap space-x-1">
          <button
              v-for="button in editorButtons"
              type="button"
              :key="button.name"
              class="p-1 rounded hover:bg-gray-100"
              @click="button.action"
            >
              <component
                :is="button.icon"
                v-if="button.icon"
                class="w-4 h-4 text-gray-700 fill-gray-700"
              />
              <span v-else-if="button.text" class="px-1 text-sm font-medium text-gray-600">
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

<script>
import { ref, onUnmounted, watch, markRaw } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import ResizableImage from './ResizableImage.js'
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
} from './icons/index.js'
import { Bars3BottomLeftIcon, Bars3BottomRightIcon, Bars3Icon, LinkIcon } from '@heroicons/vue/24/solid'

export default {
  components: {
    EditorContent,
    EllipsisVerticalIcon,
  },

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    contentLoading: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue'],

  setup(props, { emit }) {
    // --- Bilder per Strg+V / Drag&Drop ------------------------------------
    // Eingefuegte Bilder werden auf MAX_WIDTH herunterskaliert und als
    // Base64-Data-URI in die Notiz geschrieben. Das PDF-Template gibt die
    // Notiz unescaped aus ({!! $notes !!}), dompdf rendert data:-URIs -
    // derselbe Weg, ueber den auch das Firmenlogo ins PDF kommt.
    // MAX_WIDTH ist auf die nutzbare Breite einer A4-Seite abgestimmt.
    const MAX_WIDTH = 650

    function fileToDataUrl(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onerror = reject
        reader.onload = () => {
          // window.Image: der globale Bild-Konstruktor des Browsers.
          const img = new window.Image()
          img.onerror = reject
          img.onload = () => {
            const scale = Math.min(1, MAX_WIDTH / img.width)
            const canvas = document.createElement('canvas')
            canvas.width = Math.round(img.width * scale)
            canvas.height = Math.round(img.height * scale)
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height)
            // PNG behalten, wo Transparenz zaehlt (Unterschrift, Stempel).
            const keepAlpha = /png|webp|gif/i.test(file.type)
            resolve(canvas.toDataURL(keepAlpha ? 'image/png' : 'image/jpeg', 0.82))
          }
          img.src = reader.result
        }
        reader.readAsDataURL(file)
      })
    }

    function insertImageFiles(files) {
      const images = files.filter((f) => f.type.startsWith('image/'))
      if (!images.length) return false
      images.forEach((file) => {
        fileToDataUrl(file)
          .then((src) => {
            if (editor.value) editor.value.chain().focus().setImage({ src }).run()
          })
          .catch(() => {})
      })
      return true
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
        ResizableImage.configure({ inline: true, allowBase64: true }),
      ],
      editorProps: {
        handlePaste: (view, event) =>
          insertImageFiles(Array.from((event.clipboardData && event.clipboardData.files) || [])),
        handleDrop: (view, event) =>
          insertImageFiles(Array.from((event.dataTransfer && event.dataTransfer.files) || [])),
      },
      onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML())
      },
    })

    const editorButtons = ref([
      { name: 'bold', icon: markRaw(BoldIcon), action: () => editor.value.chain().focus().toggleBold().run() },
      { name: 'italic', icon: markRaw(ItalicIcon), action: () => editor.value.chain().focus().toggleItalic().run() },
      { name: 'strike', icon: markRaw(StrikethroughIcon), action: () => editor.value.chain().focus().toggleStrike().run() },
      { name: 'code', icon: markRaw(CodingIcon), action: () => editor.value.chain().focus().toggleCode().run() },
      { name: 'paragraph', icon: markRaw(ParagraphIcon), action: () => editor.value.chain().focus().setParagraph().run() },
      { name: 'h1', text: 'H1', action: () => editor.value.chain().focus().toggleHeading({ level: 1 }).run() },
      { name: 'h2', text: 'H2', action: () => editor.value.chain().focus().toggleHeading({ level: 2 }).run() },
      { name: 'h3', text: 'H3', action: () => editor.value.chain().focus().toggleHeading({ level: 3 }).run() },
      { name: 'bulletList', icon: markRaw(ListUlIcon), action: () => editor.value.chain().focus().toggleBulletList().run() },
      { name: 'orderedList', icon: markRaw(ListIcon), action: () => editor.value.chain().focus().toggleOrderedList().run() },
      { name: 'blockquote', icon: markRaw(QuoteIcon), action: () => editor.value.chain().focus().toggleBlockquote().run() },
      { name: 'codeBlock', icon: markRaw(CodeBlockIcon), action: () => editor.value.chain().focus().toggleCodeBlock().run() },
      { name: 'undo', icon: markRaw(UndoIcon), action: () => editor.value.chain().focus().undo().run() },
      { name: 'redo', icon: markRaw(RedoIcon), action: () => editor.value.chain().focus().redo().run() },
      { name: 'alignLeft', icon: markRaw(Bars3BottomLeftIcon), action: () => editor.value.chain().focus().setTextAlign('left').run() },
      { name: 'alignRight', icon: markRaw(Bars3BottomRightIcon), action: () => editor.value.chain().focus().setTextAlign('right').run() },
      { name: 'alignJustify', icon: markRaw(Bars3Icon), action: () => editor.value.chain().focus().setTextAlign('justify').run() },
      { name: 'alignCenter', icon: markRaw(MenuCenterIcon), action: () => editor.value.chain().focus().setTextAlign('center').run() },
      { name: 'addLink', icon: markRaw(LinkIcon), action: () => {
        const url = window.prompt('URL')
        if (url) {
          editor.value.chain().focus().setLink({ href: url }).run()
        }
      }},
    ])

    watch(() => props.modelValue, (newValue) => {
      if (editor.value && newValue !== editor.value.getHTML()) {
        editor.value.commands.setContent(newValue, false)
      }
    })

    onUnmounted(() => {
      if (editor.value) {
        editor.value.destroy()
      }
    })

    return {
      editor,
      editorButtons,
    }
  },
}
</script>

<style>
@reference "../../../../css/invoiceshelf.css";

.ProseMirror {
  min-height: 200px;
  padding: 8px 12px;
  outline: none;
  @apply rounded-md rounded-tl-none rounded-tr-none border border-transparent;

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
    border-left: 2px solid rgba(13, 13, 13, 0.1);
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

.editor__content img {
  max-width: 100%;
  height: auto;
}

.resizable-image {
  position: relative;
  display: inline-block;
  line-height: 0;
  max-width: 100%;
}

.resizable-image img {
  max-width: 100%;
  height: auto;
  display: block;
}

.resizable-image .resize-handle {
  position: absolute;
  right: -6px;
  bottom: -6px;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  background: var(--color-primary-500);
  border: 2px solid #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
  cursor: nwse-resize;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.resizable-image:hover .resize-handle,
.resizable-image.is-selected .resize-handle {
  opacity: 1;
}

.resizable-image.is-selected img {
  outline: 2px solid var(--color-primary-500);
}
</style>
