<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { helpers, required, email, minLength } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useMemberStore } from '../store'
import { useCompanyStore } from '../../../../stores/company.store'
import { roleService } from '../../../../api/services/role.service'
import type { Role } from '../../../../types/domain/role'

/**
 * Adds a member with a password the owner sets, for when the new member
 * should not wait for an invitation email (or the server sends no mail). The
 * owner hands the password over; an invitation lets the member choose it.
 */
interface Props {
  show: boolean
}

interface Emits {
  (e: 'close'): void
  (e: 'added'): void
}

interface AddMemberForm {
  name: string
  email: string
  password: string
  role: string | null
}

withDefaults(defineProps<Props>(), {
  show: false,
})

const emit = defineEmits<Emits>()
const memberStore = useMemberStore()
const companyStore = useCompanyStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const roles = ref<Role[]>([])

const form = reactive<AddMemberForm>({
  name: '',
  email: '',
  password: '',
  role: null,
})

const rules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
  password: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.password_min_length', { count: 8 }), minLength(8)),
  },
  role: {
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

function reset(): void {
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = null
  v$.value.$reset()
}

async function submit(): Promise<void> {
  v$.value.$touch()
  if (v$.value.$invalid || !companyStore.selectedCompany || !form.role) return

  isSaving.value = true
  try {
    await memberStore.addUser({
      name: form.name,
      email: form.email,
      password: form.password,
      companies: [{ id: companyStore.selectedCompany.id, role: form.role }],
    })
    reset()
    emit('added')
    emit('close')
  } catch {
    // Error handled by store
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <BaseModal :show="show" closable @close="$emit('close')">
    <template #header>
      {{ $t('members.add_member') }}
    </template>

    <form @submit.prevent="submit">
      <div class="p-4 space-y-4">
        <p class="text-sm text-muted">{{ $t('members.add_member_hint') }}</p>

        <BaseInputGroup
          :label="$t('members.name')"
          :error="v$.name.$error && v$.name.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="form.name"
            :invalid="v$.name.$error"
            @input="v$.name.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('members.email')"
          :error="v$.email.$error && v$.email.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="form.email"
            type="email"
            autocomplete="off"
            :invalid="v$.email.$error"
            @input="v$.email.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('members.password')"
          :error="v$.password.$error && v$.password.$errors[0]?.$message"
          required
        >
          <BaseInput
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            revealable
            :invalid="v$.password.$error"
            @input="v$.password.$touch()"
          />
        </BaseInputGroup>

        <BaseInputGroup
          :label="$t('members.role')"
          :error="v$.role.$error && v$.role.$errors[0]?.$message"
          required
        >
          <BaseMultiselect
            v-model="form.role"
            :options="roles"
            label="title"
            value-prop="name"
            track-by="title"
            :searchable="true"
          />
        </BaseInputGroup>
      </div>

      <div class="flex justify-end p-4 border-t border-line-default">
        <BaseButton
          variant="primary-outline"
          class="me-3"
          @click="$emit('close')"
        >
          {{ $t('general.cancel') }}
        </BaseButton>
        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          type="submit"
        >
          {{ $t('members.add_member') }}
        </BaseButton>
      </div>
    </form>
  </BaseModal>
</template>
