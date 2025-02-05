<template>
  <BaseModal :show="modalActive" @close="closeDiskModal" @open="loadData">
    <template #header>
      <div class="flex justify-between w-full">
        {{ modalStore.title }}
        <BaseIcon
          name="XMarkIcon"
          class="h-6 w-6 text-gray-500 cursor-pointer"
          @click="closeDiskModal"
        />
      </div>
    </template>
    <div class="file-disk-modal">
      <component
        :is="diskStore.selected_driver"
        :loading="isLoading"
        :disks="diskStore.getDiskDrivers"
        :is-edit="isEdit"
        @onChangeDisk="(val) => diskChange(val)"
        @submit="createNewDisk"
      >
        <template #default="slotProps">
          <div
            class="z-0 flex justify-end p-4 border-t border-solid border-gray-light"
          >
            <BaseButton
              class="mr-3 text-sm"
              variant="primary-outline"
              type="button"
              @click="closeDiskModal"
            >
              {{ $t('general.cancel') }}
            </BaseButton>

            <BaseButton
              :loading="isRequestFire(slotProps)"
              :disabled="isRequestFire(slotProps)"
              variant="primary"
              type="submit"
            >
              <BaseIcon
                v-if="!isRequestFire(slotProps)"
                name="ArrowDownOnSquareIcon"
                class="w-6 mr-2"
              />

              {{ $t('general.save') }}
            </BaseButton>
          </div>
        </template>
      </component>
    </div>
  </BaseModal>
</template>

<script>
import { useDiskStore } from '@/scripts/admin/stores/disk'
import { useModalStore } from '@/scripts/stores/modal'
import { computed, ref, watchEffect } from 'vue'
import Dropbox from '@/scripts/admin/components/modal-components/disks/DropboxDisk.vue'
import Local from '@/scripts/admin/components/modal-components/disks/LocalDisk.vue'
import S3 from '@/scripts/admin/components/modal-components/disks/S3Disk.vue'
import S3compat from '@/scripts/admin/components/modal-components/disks/S3CompatDisk.vue'
import DoSpaces from '@/scripts/admin/components/modal-components/disks/DoSpacesDisk.vue'
export default {
  components: {
    Dropbox,
    Local,
    S3,
    S3compat,
    DoSpaces,
  },
  setup() {
    const diskStore = useDiskStore()
    const modalStore = useModalStore()

    let isLoading = ref(false)
    let isEdit = ref(false)

    watchEffect(() => {
      if (modalStore.id) {
        isEdit.value = true
      }
    })

    const modalActive = computed(() => {
      return modalStore.active && modalStore.componentName === 'FileDiskModal'
    })

    function isRequestFire(slotProps) {
      return (
        slotProps && (slotProps.diskData.isLoading.value || isLoading.value)
      )
    }

    async function loadData() {
      isLoading.value = true
      let res = await diskStore.fetchDiskDrivers()
      if (isEdit.value) {
        diskStore.selected_driver = modalStore.data.driver
      } else {
        diskStore.selected_driver = res.data.drivers[0].value
      }
      isLoading.value = false
    }

    async function createNewDisk(data) {
      try {
        Object.assign(diskStore.diskConfigData, data)
        isLoading.value = true

        let formData = {
          id: modalStore.id,
          ...data,
        }

        let response = null
        const action = isEdit.value
          ? diskStore.updateDisk
          : diskStore.createDisk

        response = await action(formData)
        modalStore.refreshData()
        closeDiskModal()
      } catch (e) {
        // error is handled by the disk store
      } finally {
        isLoading.value = false
      }
    }

    function closeDiskModal() {
      modalStore.closeModal()
    }

    function diskChange(value) {
      diskStore.selected_driver = value
      diskStore.diskConfigData.selected_driver = value
    }

    return {
      isEdit,
      createNewDisk,
      isRequestFire,
      diskStore,
      closeDiskModal,
      loadData,
      diskChange,
      modalStore,
      isLoading,
      modalActive,
    }
  },
}
</script>
