<template>
  <BaseMultiselect
    v-model="selectedInstaller"
    v-bind="$attrs"
    track-by="name"
    value-prop="id"
    label="name"
    :filter-results="false"
    resolve-on-load
    :delay="500"
    :searchable="true"
    :options="searchInstallers"
    label-value="name"
    :placeholder="$t('installers.type_or_click')"
    :can-deselect="false"
    class="w-full"
  >
    <template v-if="showAction" #action>
      <BaseSelectAction
        v-if="userStore.hasAbilities(abilities.CREATE_INSTALLER)"
        @click="addInstaller"
      >
        <BaseIcon
          name="UserAddIcon"
          class="h-4 mr-2 -ml-2 text-center text-primary-400"
        />

        {{ $t('installers.add_new_installer') }}
      </BaseSelectAction>
    </template>
  </BaseMultiselect>

  <InstallerModal />
</template>

<script setup>
import { useInstallerStore } from '@/scripts/admin/stores/installer'
import { computed, watch } from 'vue'
import { useModalStore } from '@/scripts/stores/modal'
import { useI18n } from 'vue-i18n'
import InstallerModal from '@/scripts/admin/components/modal-components/InstallerModal.vue'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  modelValue: {
    type: [String, Number, Object],
    default: '',
  },
  fetchAll: {
    type: Boolean,
    default: false,
  },
  showAction: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()

const emit = defineEmits(['update:modelValue'])

const modalStore = useModalStore()
const installerStore = useInstallerStore()
const userStore = useUserStore()

const selectedInstaller = computed({
  get: () => props.modelValue,
  set: (value) => {
    emit('update:modelValue', value)
  },
})

async function searchInstallers(search) {
  let data = {
    search,
  }

  if (props.fetchAll) {
    data.limit = 'all'
  }

  let res = await installerStore.fetchInstallers(data)
  if(res.data.data.length>0 && installerStore.editInstaller) {
    let installerFound = res.data.data.find((c) => c.id==installerStore.editInstaller.id)
    if(!installerFound) {
      let edit_installer = Object.assign({}, installerStore.editInstaller)
      res.data.data.unshift(edit_installer)
    }
  }

  return res.data.data
}

async function addInstaller() {
  installerStore.resetCurrentInstaller()

  modalStore.openModal({
    title: t('installers.add_new_installer'),
    componentName: 'InstallerModal',
  })
}
</script>
