<template>
  <div
    class="
      has-[input[type=file]:focus-visible]:ring-2 has-[input[type=file]:focus-visible]:ring-focus
      relative
      flex
      items-center
      justify-center
      p-2
      border-2 border-dashed
      rounded-md
      cursor-pointer
      avatar-upload
      border-line-strong
      transition-all
      duration-300
      ease-in-out
      isolate
      hover:border-line-strong
      group
      min-h-[100px]
      bg-surface-secondary
    "
    :class="avatar ? 'w-32 h-32' : 'w-full'"
  >
    <input
      :id="inputId"
      ref="inputRef"
      type="file"
      :aria-label="$t('general.file_upload.choose_file')"
      :multiple="multiple"
      :name="inputFieldName"
      :accept="accept"
      class="absolute z-10 w-full h-full opacity-0 cursor-pointer"
      @click="($event.target as HTMLInputElement).value = ''"
      @change="
        onChange(
          ($event.target as HTMLInputElement).name,
          ($event.target as HTMLInputElement).files!,
          ($event.target as HTMLInputElement).files!.length
        )
      "
    />

    <!-- Avatar Not Selected -->
    <div v-if="!localFiles.length && avatar" class="">
      <img :src="getDefaultAvatar()" class="rounded" alt="" />

      <button
        type="button"
        :aria-label="$t('general.file_upload.browse')"
        class="absolute z-30 bg-surface rounded-full -bottom-3 -right-3 group"
        @click.prevent.stop="onBrowse"
      >
        <BaseIcon
          name="PlusCircleIcon"
        class="
            h-8
            text-xl
            leading-6
            text-primary-500
            group-hover:text-primary-600
          "
        />
      </button>
    </div>

    <!-- Not Selected -->
    <div v-else-if="!localFiles.length" class="flex flex-col items-center">
      <BaseIcon
        name="CloudArrowUpIcon"
        class="h-6 mb-2 text-xl leading-6 text-subtle"
      />
      <p class="text-xs leading-4 text-center text-subtle">
        {{ $t('general.file_upload.drag_a_file') }}
        <button
          type="button"
        class="
            cursor-pointer
            text-primary-500
            hover:text-primary-600 hover:font-medium
            relative
            z-20
          "
          @click.prevent.stop="onBrowse"
        >
          {{ $t('general.file_upload.browse') }}
        </button>
        {{ $t('general.file_upload.to_choose') }}
      </p>
      <p class="text-xs leading-4 text-center text-subtle mt-2">
        {{ recommendedText }}
      </p>

      <!--
        On a phone the file picker is the wrong first answer for a receipt:
        the photo does not exist yet. `relative z-20` lifts this above the
        invisible file input that covers the whole dropzone.
      -->
      <button
        v-if="canCapture"
        type="button"
        class="
          relative
          z-20
          mt-3
          inline-flex
          items-center
          gap-1.5
          px-3
          py-1.5
          text-xs
          font-medium
          rounded-md
          border border-line-default
          bg-surface
          text-body
          hover:border-line-strong
        "
        @click.prevent.stop="onCapture"
      >
        <BaseIcon name="CameraIcon" class="h-4 text-subtle" />
        {{ $t('general.file_upload.take_photo') }}
      </button>
    </div>

    <div
      v-else-if="localFiles.length && avatar && !multiple"
      class="flex w-full h-full border border-line-default rounded justify-center items-center"
    >
      <img
        v-if="localFiles[0].image"
        :alt="localFile.name ?? ''"
        :src="localFiles[0].image"
        class="block object-cover w-full h-full rounded opacity-100"
        style="animation: fadeIn 2s ease"
      />

      <div
        v-else
        class="
          flex
          justify-center
          items-center
          text-subtle
          flex-col
          space-y-2
          px-2
          py-4
          w-full
        "
      >
        <!-- DocumentText Icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-8 w-8"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.25"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>

        <p
          v-if="localFiles[0].name"
        class="
            text-body
            font-medium
            text-sm
            truncate
            overflow-hidden
            w-full
          "
        >
          {{ localFiles[0].name }}
        </p>
      </div>

      <button
        type="button"
        :aria-label="$t('general.file_upload.remove_file')"
        class="
          box-border
          absolute
          z-30
          flex
          items-center
          justify-center
          w-8
          h-8
          bg-surface
          border border-line-default
          rounded-full
          shadow-md
          -bottom-3
          -right-3
          group
          hover:border-line-strong
        "
        @click.prevent.stop="onAvatarRemove(localFiles[0])"
      >
        <BaseIcon name="XMarkIcon" class="h-4 text-xl leading-6 text-heading" />
      </button>
    </div>

    <!-- Preview Files Multiple -->
    <div
      v-else-if="localFiles.length && multiple"
      class="flex flex-wrap w-full"
    >
      <div
        v-for="(localFile, index) in localFiles"
        :key="index"
        class="
          block
          p-2
          m-2
          bg-surface
          border border-line-default
          rounded
          hover:border-gray-500
          relative
          max-w-md
        "
      >
        <img
          v-if="localFile.image"
          :alt="localFile.name ?? ''"
          :src="localFile.image"
          class="block object-cover w-20 h-20 opacity-100"
          style="animation: fadeIn 2s ease"
        />

        <div
          v-else
        class="
            flex
            justify-center
            items-center
            text-subtle
            flex-col
            space-y-2
            px-2
            py-4
            w-full
          "
        >
          <!-- DocumentText Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-8 w-8"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.25"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>

          <p
            v-if="localFile.name"
        class="
              text-body
              font-medium
              text-sm
              truncate
              overflow-hidden
              w-full
            "
          >
            {{ localFile.name }}
          </p>
        </div>

        <button
          type="button"
          :aria-label="$t('general.file_upload.remove_file')"
        class="
            cursor-pointer
            box-border
            absolute
            z-30
            flex
            items-center
            justify-center
            w-8
            h-8
            bg-surface
            border border-line-default
            rounded-full
            shadow-md
            -bottom-3
            -right-3
            group
            hover:border-line-strong
          "
          @click.prevent.stop="onFileRemove(index)"
        >
          <BaseIcon name="XMarkIcon" class="h-4 text-xl leading-6 text-heading" />
        </button>
      </div>
    </div>

    <div v-else class="flex w-full items-center justify-center">
      <div
        v-for="(localFile, index) in localFiles"
        :key="index"
        class="
          block
          p-2
          m-2
          bg-surface
          border border-line-default
          rounded
          hover:border-gray-500
          relative
          max-w-md
        "
      >
        <img
          v-if="localFile.image"
          :alt="localFile.name ?? ''"
          :src="localFile.image"
          class="block object-contain h-20 opacity-100 min-w-[5rem]"
          style="animation: fadeIn 2s ease"
        />

        <div
          v-else
        class="
            flex
            justify-center
            items-center
            text-subtle
            flex-col
            space-y-2
            px-2
            py-4
            w-full
          "
        >
          <!-- DocumentText Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-8 w-8"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.25"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>

          <p
            v-if="localFile.name"
        class="
              text-body
              font-medium
              text-sm
              truncate
              overflow-hidden
              w-full
            "
          >
            {{ localFile.name }}
          </p>
        </div>

        <button
          type="button"
          :aria-label="$t('general.file_upload.remove_file')"
        class="
            cursor-pointer
            box-border
            absolute
            z-30
            flex
            items-center
            justify-center
            w-8
            h-8
            bg-surface
            border border-line-default
            rounded-full
            shadow-md
            -bottom-3
            -right-3
            group
            hover:border-line-strong
          "
          @click.prevent.stop="onFileRemove(index)"
        >
          <BaseIcon name="XMarkIcon" class="h-4 text-xl leading-6 text-heading" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { client as http } from '@/scripts/api/client'
import { isNative } from '@/scripts/config/runtime'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import * as utils from '@/scripts/utils/format-money'

interface LocalFile {
  fileObject?: File
  type: string
  name: string
  image?: string
}

interface UploadedFile {
  id: string | number
  url: string
  [key: string]: unknown
}

interface Props {
  multiple?: boolean
  avatar?: boolean
  autoProcess?: boolean
  uploadUrl?: string
  preserveLocalFiles?: boolean
  accept?: string
  inputFieldName?: string
  base64?: boolean
  modelValue?: LocalFile[]
  recommendedText?: string
}

const props = withDefaults(defineProps<Props>(), {
  multiple: false,
  avatar: false,
  autoProcess: false,
  uploadUrl: '',
  preserveLocalFiles: false,
  accept: 'image/*',
  inputFieldName: 'photos',
  base64: false,
  modelValue: () => [],
  recommendedText: '',
})

interface Emits {
  (e: 'change', fieldName: string, file: FileList | File | string, fileCount: number, rawFile?: File): void
  (e: 'remove', value: LocalFile | number): void
  (e: 'update:modelValue', value: LocalFile[]): void
}

const emit = defineEmits<Emits>()

// status
const STATUS_INITIAL = 0
const STATUS_SAVING = 1
const STATUS_SUCCESS = 2
const STATUS_FAILED = 3

const { t } = useI18n()
const notificationStore = useNotificationStore()

/**
 * Whether to offer the camera. Fixed for the life of the component: the shell
 * cannot change under a running app, and an uploader that takes no images has
 * no use for a photograph.
 */
const canCapture = isNative() && props.accept.includes('image/')

const uploadedFiles = ref<UploadedFile[]>([])
const localFiles = ref<LocalFile[]>([])
const inputRef = ref<HTMLInputElement | null>(null)
const inputId = `file-upload-${useId()}`
const uploadError = ref<unknown>(null)
const currentStatus = ref<number | null>(null)

function reset(): void {
  // reset form to initial state
  currentStatus.value = STATUS_INITIAL

  uploadedFiles.value = []

  if (props.modelValue && props.modelValue.length) {
    localFiles.value = [...props.modelValue]
  } else {
    localFiles.value = []
  }

  uploadError.value = null
}

function upload(formData: FormData): Promise<UploadedFile[]> {
  return (
    http
      .post(props.uploadUrl, formData)
      // get data
      .then((x: { data: Record<string, unknown>[] }) => x.data)
      // add url field
      .then((x) =>
        x.map((img) => ({ ...img, url: `/images/${img.id}` }) as UploadedFile)
      )
  )
}

// upload data to the server
function save(formData: FormData): void {
  currentStatus.value = STATUS_SAVING

  upload(formData)
    .then((x) => {
      uploadedFiles.value = ([] as UploadedFile[]).concat(x)
      currentStatus.value = STATUS_SUCCESS
    })
    .catch((err: { response: unknown }) => {
      uploadError.value = err.response
      currentStatus.value = STATUS_FAILED
    })
}

function getBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.readAsDataURL(file)
    reader.onload = () => resolve(reader.result as string)
    reader.onerror = (error) => reject(error)
  })
}

function onChange(fieldName: string, fileList: FileList, fileCount: number): void {
  if (!fileList.length) return

  if (props.multiple) {
    emit('change', fieldName, fileList, fileCount)
  } else {
    emitSingle(fieldName, fileList[0], fileCount)
  }

  absorb(fieldName, Array.from(fileList))
}

// One file, announced the way the caller asked for it.
function emitSingle(fieldName: string, file: File, fileCount: number): void {
  if (props.base64) {
    getBase64(file).then((res) => {
      emit('change', fieldName, res, fileCount, file)
    })
  } else {
    emit('change', fieldName, file, fileCount)
  }
}

// Everything that happens to a chosen file whatever it was chosen with: the
// previews, the v-model, and the optional immediate upload. Split out of
// `onChange` so a photograph goes through the very same steps as a pick.
function absorb(fieldName: string, files: File[]): void {
  if (!props.preserveLocalFiles) {
    localFiles.value = []
  }

  files.forEach((file) => {
    if (utils.isImageFile(file.type)) {
      getBase64(file).then((image) => {
        localFiles.value.push({
          fileObject: file,
          type: file.type,
          name: file.name,
          image,
        })
      })
    } else {
      localFiles.value.push({
        fileObject: file,
        type: file.type,
        name: file.name,
      })
    }
  })

  emit('update:modelValue', localFiles.value)

  if (!props.autoProcess) return

  // append the files to FormData
  const formData = new FormData()

  files.forEach((file) => {
    formData.append(fieldName, file, file.name)
  })

  // save it
  save(formData)
}

/**
 * Photograph the file instead of picking it.
 *
 * The import sits inside the build-define branch, which is the folded
 * constant `false` on the web, so the camera plugin and the rest of the
 * Capacitor adapter are dropped from that bundle entirely.
 *
 * A capture is one file, so it takes the single-file path even where
 * `multiple` is set; the picker underneath is still there for the rest.
 */
async function onCapture(): Promise<void> {
  if (__INVOICESHELF_CLIENT__) {
    try {
      const { capturePhoto } = await import('@/scripts/platform/capacitor')
      const file = await capturePhoto()

      // Null is a user who changed their mind. Nothing to report.
      if (!file) return

      emitSingle(props.inputFieldName, file, 1)
      absorb(props.inputFieldName, [file])
    } catch {
      // A refused permission or a camera the OS would not hand over. Saying
      // so beats an unhandled rejection and a button that did nothing; the
      // file picker underneath still works.
      notificationStore.showNotification({
        type: 'error',
        message: t('general.file_upload.capture_failed'),
      })
    }
  }
}

function onBrowse(): void {
  if (inputRef.value) {
    inputRef.value.click()
  }
}

function onAvatarRemove(image: LocalFile): void {
  localFiles.value = []
  emit('remove', image)
}

function onFileRemove(index: number): void {
  localFiles.value.splice(index, 1)
  emit('remove', index)
}

function getDefaultAvatar(): string {
  const imgUrl = new URL('$images/default-avatar.jpg', import.meta.url)
  return imgUrl.href
}

onMounted(() => {
  reset()
})

watch(
  () => props.modelValue,
  (v) => {
    localFiles.value = [...v]
  }
)
</script>
