<script setup lang="ts">
import { ref, computed } from 'vue'
import { useModalStore } from '@/scripts/stores/modal.store'
import { useUserStore } from '@/scripts/stores/user.store'

interface ModalData {
  templates: Array<{ name: string; path?: string }>
  store: { setTemplate: (name: string) => void; isEdit?: boolean; [key: string]: unknown }
  storeProp: string
  isMarkAsDefault: boolean
  markAsDefaultDescription: string
}

const modalStore = useModalStore()
const userStore = useUserStore()

const selectedTemplate = ref<string>('')

const modalActive = computed<boolean>(
  () => modalStore.active && modalStore.componentName === 'SelectTemplate',
)

const modalData = computed<ModalData | null>(() => {
  return modalStore.data as ModalData | null
})

function setData(): void {
  if (!modalData.value) return

  const currentName =
    (modalData.value.store[modalData.value.storeProp] as Record<string, unknown>)
      ?.template_name as string | undefined

  if (currentName) {
    selectedTemplate.value = currentName
  } else if (modalData.value.templates.length) {
    selectedTemplate.value = modalData.value.templates[0].name
  }
}

async function chooseTemplate(): Promise<void> {
  if (!modalData.value) return

  modalData.value.store.setTemplate(selectedTemplate.value)

  if (!modalData.value.store.isEdit && modalData.value.isMarkAsDefault) {
    if (modalData.value.storeProp === 'newEstimate') {
      await userStore.updateUserSettings({
        settings: {
          default_estimate_template: selectedTemplate.value,
        },
      })
    } else if (modalData.value.storeProp === 'newInvoice') {
      await userStore.updateUserSettings({
        settings: {
          default_invoice_template: selectedTemplate.value,
        },
      })
    }
  }

  closeModal()
}

function getTickImage(): string {
  return new URL('$images/tick.png', import.meta.url).href
}

function closeModal(): void {
  modalStore.closeModal()
}
</script>

<template>
  <BaseModal :show="modalActive" closable @close="closeModal" @open="setData">
    <template #header>
      {{ modalStore.title }}
    </template>

    <div class="px-8 py-8 sm:p-6">
      <!-- A radio group: Tab reaches the choice, the arrow keys change it -->
      <fieldset v-if="modalData">
        <legend class="sr-only">{{ $t('general.select_template') }}</legend>
        <div class="grid grid-cols-3 gap-2 p-1 overflow-x-auto">
          <label
            v-for="(template, index) in modalData.templates"
            :key="index"
            :class="{
              'border border-solid border-primary-500':
                selectedTemplate === template.name,
            }"
            class="
              relative
              flex flex-col
              m-2
              border border-line-default border-solid
              cursor-pointer
              hover:border-primary-300
              has-[input:focus-visible]:ring-2 has-[input:focus-visible]:ring-focus has-[input:focus-visible]:ring-offset-2
            "
          >
            <input
              v-model="selectedTemplate"
              type="radio"
              name="document-template"
              :value="template.name"
              class="sr-only"
            />
            <img
              :src="template.path"
              alt=""
              class="w-full min-h-[100px]"
            />
            <img
              v-if="selectedTemplate === template.name"
              alt=""
              class="absolute z-10 w-5 h-5 text-primary-500"
              style="top: -6px; right: -5px"
              :src="getTickImage()"
            />
            <span
              :class="[
                'w-full p-1 bg-surface-muted text-sm text-center absolute bottom-0 start-0',
                {
                  'text-primary-600 bg-primary-100':
                    selectedTemplate === template.name,
                  'text-body': selectedTemplate !== template.name,
                },
              ]"
            >
              {{ template.name }}
            </span>
          </label>
        </div>
      </fieldset>

      <div
        v-if="modalData && !modalData.store.isEdit"
        class="z-0 flex ms-3 pt-5"
      >
        <BaseCheckbox
          v-model="modalData.isMarkAsDefault"
          :set-initial-value="false"
          variant="primary"
          :label="$t('general.mark_as_default')"
          :description="modalData.markAsDefaultDescription"
        />
      </div>
    </div>

    <div
      class="z-0 flex justify-end p-4 border-t border-line-default border-solid"
    >
      <BaseButton class="me-3" variant="primary-outline" @click="closeModal">
        {{ $t('general.cancel') }}
      </BaseButton>
      <BaseButton variant="primary" @click="chooseTemplate">
        <template #left="slotProps">
          <BaseIcon name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('general.choose') }}
      </BaseButton>
    </div>
  </BaseModal>
</template>
