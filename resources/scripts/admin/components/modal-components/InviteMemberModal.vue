<template>
  <BaseModal :show="show" @close="$emit('close')">
    <template #header>
      <div class="flex justify-between w-full">
        {{ $t('members.invite_member') }}
        <BaseIcon
          name="XMarkIcon"
          class="w-6 h-6 text-muted cursor-pointer"
          @click="$emit('close')"
        />
      </div>
    </template>

    <form @submit.prevent="submitInvitation">
      <div class="p-4 space-y-4">
        <BaseInputGroup
          :label="$t('members.email')"
          :error="v$.email.$error && v$.email.$errors[0].$message"
          required
        >
          <BaseInput
            v-model="form.email"
            type="email"
            :invalid="v$.email.$error"
            @input="v$.email.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('members.role')"
          :error="v$.role_id.$error && v$.role_id.$errors[0].$message"
          required
        >
          <BaseMultiselect
            v-model="form.role_id"
            :options="roles"
            label="title"
            value-prop="id"
            track-by="title"
            :searchable="true"
          />
        </BaseInputGroup>
      </div>

      <div class="flex justify-end p-4 border-t border-line-default">
        <BaseButton
          variant="primary-outline"
          class="mr-3"
          @click="$emit('close')"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSending"
          :disabled="isSending"
          type="submit"
        >
          {{ $t('members.invite_member') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useMembersStore } from '@/scripts/admin/stores/members'
import { useI18n } from 'vue-i18n'
import { helpers, required, email } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import http from '@/scripts/http'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])
const membersStore = useMembersStore()
const { t } = useI18n()

const isSending = ref(false)
const roles = ref([])

const form = reactive({
  email: '',
  role_id: null,
})

const rules = computed(() => ({
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  role_id: {
    required: helpers.withMessage(t('validation.required'), required),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => form)
)

onMounted(async () => {
  const response = await http.get('/api/v1/roles')
  roles.value = response.data.data
})

async function submitInvitation() {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSending.value = true
  try {
    await membersStore.inviteMember({
      email: form.email,
      role_id: form.role_id,
    })
    form.email = ''
    form.role_id = null
    v$.value.$reset()
    emit('close')
  } catch (e) {
    // Error handled by store
  } finally {
    isSending.value = false
  }
}
</script>
