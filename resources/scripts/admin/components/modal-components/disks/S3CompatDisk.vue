<template>
  <form @submit.prevent="submitData">
    <div class="px-8 py-6">
      <BaseInputGrid>
        <BaseInputGroup
          :label="$t('settings.disk.name')"
          :error="
            v$.s3CompatDiskConfigData.name.$error &&
            v$.s3CompatDiskConfigData.name.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model="diskStore.s3CompatDiskConfigData.name"
            type="text"
            name="name"
            :invalid="v$.s3CompatDiskConfigData.name.$error"
            @input="v$.s3CompatDiskConfigData.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.driver')"
          :error="
            v$.s3CompatDiskConfigData.selected_driver.$error &&
            v$.s3CompatDiskConfigData.selected_driver.$errors[0].$message
          "
          required
        >
          <BaseMultiselect
            v-model="selected_driver"
            :invalid="v$.s3CompatDiskConfigData.selected_driver.$error"
            value-prop="value"
            :options="disks"
            searchable
            label="name"
            :can-deselect="false"
            @update:modelValue="onChangeDriver(data)"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_endpoint')"
          :error="
            v$.s3CompatDiskConfigData.root.$error &&
            v$.s3CompatDiskConfigData.root.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.endpoint"
            type="url"
            name="endpoint"
            placeholder="http://127.0.0.1:9005"
            :invalid="v$.s3CompatDiskConfigData.endpoint.$error"
            @input="v$.s3CompatDiskConfigData.endpoint.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_root')"
          :error="
            v$.s3CompatDiskConfigData.root.$error &&
            v$.s3CompatDiskConfigData.root.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.root"
            type="text"
            name="root"
            placeholder="Ex. /user/root/"
            :invalid="v$.s3CompatDiskConfigData.root.$error"
            @input="v$.s3CompatDiskConfigData.root.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_key')"
          :error="
            v$.s3CompatDiskConfigData.key.$error &&
            v$.s3CompatDiskConfigData.key.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.key"
            type="text"
            name="key"
            placeholder="Ex. KEIS4S39SERSDS"
            :invalid="v$.s3CompatDiskConfigData.key.$error"
            @input="v$.s3CompatDiskConfigData.key.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_secret')"
          :error="
            v$.s3CompatDiskConfigData.secret.$error &&
            v$.s3CompatDiskConfigData.secret.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.secret"
            type="text"
            name="secret"
            placeholder="Ex. ********"
            :invalid="v$.s3CompatDiskConfigData.secret.$error"
            @input="v$.s3CompatDiskConfigData.secret.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_region')"
          :error="
            v$.s3CompatDiskConfigData.region.$error &&
            v$.s3CompatDiskConfigData.region.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.region"
            type="text"
            name="region"
            placeholder="Ex. us-west"
            :invalid="v$.s3CompatDiskConfigData.region.$error"
            @input="v$.s3CompatDiskConfigData.region.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('settings.disk.s3_bucket')"
          :error="
            v$.s3CompatDiskConfigData.bucket.$error &&
            v$.s3CompatDiskConfigData.bucket.$errors[0].$message
          "
          required
        >
          <BaseInput
            v-model.trim="diskStore.s3CompatDiskConfigData.bucket"
            type="text"
            name="bucket"
            placeholder="Ex. AppName"
            :invalid="v$.s3CompatDiskConfigData.bucket.$error"
            @input="v$.s3CompatDiskConfigData.bucket.$touch()"
          />
        </BaseInputGroup>
      </BaseInputGrid>
      <div v-if="!isDisabled" class="flex items-center mt-6">
        <div class="relative flex items-center w-12">
          <BaseSwitch v-model="set_as_default" class="flex" />
        </div>
        <div class="ml-4 right">
          <p class="p-0 mb-1 text-base leading-snug text-black box-title">
            {{ $t('settings.disk.is_default') }}
          </p>
        </div>
      </div>
    </div>
    <slot :disk-data="{ isLoading, submitData }" />
  </form>
</template>

<script>
import { useDiskStore } from '@/scripts/admin/stores/disk'
import { useModalStore } from '@/scripts/stores/modal'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import useVuelidate from '@vuelidate/core'
import { required, helpers } from '@vuelidate/validators'
export default {
  props: {
    isEdit: {
      type: Boolean,
      require: true,
      default: false,
    },
    loading: {
      type: Boolean,
      require: true,
      default: false,
    },
    disks: {
      type: Array,
      require: true,
      default: Array,
    },
  },

  emits: ['submit', 'onChangeDisk'],

  setup(props, { emit }) {
    const diskStore = useDiskStore()
    const modalStore = useModalStore()
    const { t } = useI18n()

    let set_as_default = ref(false)
    let isLoading = ref(false)
    let selected_disk = ref(null)
    let is_current_disk = ref(null)

    const selected_driver = computed({
      get: () => diskStore.selected_driver,
      set: (value) => {
        diskStore.selected_driver = value
        diskStore.s3CompatDiskConfigData.selected_driver = value
      },
    })

    const rules = computed(() => {
      return {
        s3CompatDiskConfigData: {
          name: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          endpoint: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          root: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          key: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          secret: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          region: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          bucket: {
            required: helpers.withMessage(t('validation.required'), required),
          },
          selected_driver: {
            required: helpers.withMessage(t('validation.required'), required),
          },
        },
      }
    })

    const v$ = useVuelidate(
      rules,
      computed(() => diskStore),
    )

    onBeforeUnmount(() => {
      diskStore.s3CompatDiskConfigData = {
        name: null,
        selected_driver: 's3compat',
        key: null,
        secret: null,
        region: null,
        bucket: null,
        root: null,
      }
    })

    loadData()

    async function loadData() {
      isLoading.value = true
      let data = reactive({
        disk: 's3compat',
      })

      if (props.isEdit) {
        Object.assign(diskStore.s3CompatDiskConfigData, modalStore.data)
        set_as_default.value = modalStore.data.set_as_default

        if (set_as_default.value) {
          is_current_disk.value = true
        }
      } else {
        let diskData = await diskStore.fetchDiskEnv(data)
        Object.assign(diskStore.s3CompatDiskConfigData, diskData.data)
      }
      selected_disk.value = props.disks.find((v) => v.value == 's3compat')
      isLoading.value = false
    }

    const isDisabled = computed(() => {
      return props.isEdit && set_as_default.value && is_current_disk.value
        ? true
        : false
    })

    async function submitData() {
      v$.value.s3CompatDiskConfigData.$touch()
      if (v$.value.s3CompatDiskConfigData.$invalid) {
        return true
      }

      let data = {
        credentials: diskStore.s3CompatDiskConfigData,
        name: diskStore.s3CompatDiskConfigData.name,
        driver: selected_disk.value.value,
        set_as_default: set_as_default.value,
      }

      emit('submit', data)
      return false
    }

    function onChangeDriver() {
      emit('onChangeDisk', diskStore.s3CompatDiskConfigData.selected_driver)
    }

    return {
      v$,
      diskStore,
      modalStore,
      set_as_default,
      isLoading,
      selected_disk,
      selected_driver,
      is_current_disk,
      loadData,
      submitData,
      onChangeDriver,
      isDisabled,
    }
  },
}
</script>
