<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { helpers, required, email } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useMemberStore } from '../store'
import { roleService } from '../../../../api/services/role.service'
import type { Role } from '../../../../types/domain/role'

interface Props {
  show: boolean
}

interface Emits {
  (e: 'close'): void
}

interface InviteForm {
  email: string
  role_id: number | null
}

withDefaults(defineProps<Props>(), {
  show: false,
})

const emit = defineEmits<Emits>()
const memberStore = useMemberStore()
const { t } = useI18n()

const isSending = ref<boolean>(false)
const roles = ref<Role[]>([])

const form = reactive<InviteForm>({
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
  const response = await roleService.list()
  roles.value = response.data as unknown as Role[]
})

async function submitInvitation(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid) return

  isSending.value = true
  try {
    await memberStore.inviteMember({
      email: form.email,
      role_id: form.role_id,
    })
    form.email = ''
    form.role_id = null
    v$.value.$reset()
    emit('close')
  } catch {
    // Error handled by store
  } finally {
    isSending.value = false
  }
}
</script>

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
          :error="v$.email.$error && v$.email.$errors[0]?.$message"
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
          :error="v$.role_id.$error && v$.role_id.$errors[0]?.$message"
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
