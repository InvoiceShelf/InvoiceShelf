<template>
  <BaseDropdown :content-loading="contentLoading">
    <template #activator>
      <BaseButton v-if="route.name === 'credit_notes.view'" variant="primary">
        <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-white" />
      </BaseButton>
      <BaseIcon v-else name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- Copy pdf url  -->
    <BaseDropdown-item
      v-if="
        route.name === 'credit_notes.view' &&
        userStore.hasAbilities(abilities.VIEW_CREDIT_NOTE)
      "
      class="rounded-md"
      @click="copyPdfUrl"
    >
      <BaseIcon
        name="LinkIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.copy_pdf_url') }}
    </BaseDropdown-item>

    <!-- edit credit note  -->
    <router-link
      v-if="userStore.hasAbilities(abilities.EDIT_CREDIT_NOTE)"
      :to="`/admin/credit-notes/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- view credit note  -->
    <router-link
      v-if="
        route.name !== 'credit_notes.view' &&
        userStore.hasAbilities(abilities.VIEW_CREDIT_NOTE)
      "
      :to="`/admin/credit-notes/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Send Estimate  -->
    <BaseDropdownItem
      v-if="
        row.status !== 'SENT' &&
        route.name !== 'credit_notes.view' &&
        userStore.hasAbilities(abilities.SEND_CREDIT_NOTE)
      "
      @click="sendCreditNote(row)"
    >
      <BaseIcon
        name="PaperAirplaneIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('credit_notes.send_credit_note') }}
    </BaseDropdownItem>

    <!-- delete credit note  -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_CREDIT_NOTE)"
      @click="removeCreditNote(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useDialogStore } from '@/scripts/stores/dialog'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import { useCreditNoteStore } from '@/scripts/admin/stores/credit-note'
import { useRoute, useRouter } from 'vue-router'
import { inject } from 'vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },
  table: {
    type: Object,
    default: null,
  },
  contentLoading: {
    type: Boolean,
    default: false,
  },
})

const dialogStore = useDialogStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()
const creditNoteStore = useCreditNoteStore()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const modalStore = useModalStore()

const $utils = inject('utils')

function removeCreditNote(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('credit_notes.confirm_delete', 1),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
      variant: 'danger',
      size: 'lg',
      hideNoButton: false,
    })
    .then(async (res) => {
      if (res) {
        await creditNoteStore.deleteCreditNote({ ids: [id] })
        router.push(`/admin/credit-notes`)
        props.table && props.table.refresh()

        return true
      }
    })
}

function copyPdfUrl() {
  let pdfUrl = `${window.location.origin}/credit-notes/pdf/${props.row?.unique_hash}`

  $utils.copyTextToClipboard(pdfUrl)

  notificationStore.showNotification({
    type: 'success',
    message: t('general.copied_pdf_url_clipboard'),
  })
}

async function sendCreditNote(creditNote) {
  modalStore.openModal({
    title: t('credit_notes.send_credit_note'),
    componentName: 'SendCreditNoteModal',
    id: creditNote.id,
    data: creditNote,
    variant: 'lg',
  })
}
</script>
