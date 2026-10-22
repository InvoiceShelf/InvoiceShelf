<template>
  <BasePage v-if="!isLoading">
    <BasePageHeader :title="pageTitle">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="dashboard" />
        <BaseBreadcrumbItem
          :title="$t('administration.users.title')"
          to="/admin/administration/users"
        />
        <BaseBreadcrumbItem :title="pageTitle" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <form @submit.prevent="submitForm">
      <BaseCard class="mt-6">
        <BaseInputGrid class="mt-5">
          <BaseInputGroup
            :label="$t('members.name')"
            :error="v$.name.$error && v$.name.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="formData.name"
              :invalid="v$.name.$error"
              @blur="v$.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('members.email')"
            :error="v$.email.$error && v$.email.$errors[0].$message"
            required
          >
            <BaseInput
              v-model="formData.email"
              type="email"
              :invalid="v$.email.$error"
              @blur="v$.email.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('members.phone')">
            <BaseInput v-model="formData.phone" type="text" />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('members.password')"
            :error="v$.password.$error && v$.password.$errors[0].$message"
            :help-text="isCreate ? $t('administration.users.password_new_hint') : undefined"
            :required="isCreate"
          >
            <BaseInput
              v-model="formData.password"
              type="password"
              revealable
              :invalid="v$.password.$error"
              autocomplete="new-password"
              @blur="v$.password.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <div class="mt-6 border-t border-line-light pt-2">
          <BaseSwitchSection
            v-model="formData.isSuperAdmin"
            :title="$t('administration.users.super_admin')"
            :description="
              editingSelf
                ? $t('administration.users.super_admin_self')
                : $t('administration.users.super_admin_description')
            "
            :disabled="editingSelf"
          />
        </div>

        <div v-if="managesCompanyAccess" class="mt-6 border-t border-line-light pt-6">
          <h3 class="text-sm font-medium text-heading">
            {{ $t('administration.users.global_roles_title') }}
          </h3>
          <p class="mt-1 mb-4 text-sm text-muted">
            {{ $t('administration.users.global_roles_description') }}
          </p>
          <BaseInputGroup :label="$t('administration.users.global_roles')">
            <BaseMultiselect
              v-model="globalRoleKeys"
              :options="globalRoleOptions"
              value-prop="key"
              label="title"
              track-by="title"
              mode="tags"
              searchable
              :can-clear="true"
              :can-deselect="true"
              :placeholder="$t('administration.users.select_global_role')"
            />
          </BaseInputGroup>
        </div>

        <div v-if="managesCompanyAccess" class="mt-6 border-t border-line-light pt-6">
          <h3 class="text-sm font-medium text-heading">
            {{ $t('administration.users.restricted_companies_title') }}
          </h3>
          <p class="mt-1 mb-4 text-sm text-muted">
            {{ $t('administration.users.restricted_companies_description') }}
          </p>
          <BaseInputGroup :label="$t('administration.users.restricted_companies')">
            <BaseMultiselect
              v-model="restrictedCompanyIds"
              :options="restrictableCompanies"
              value-prop="id"
              label="name"
              track-by="name"
              mode="tags"
              searchable
              :can-clear="true"
              :can-deselect="true"
              :placeholder="$t('administration.users.select_restricted_company')"
            />
          </BaseInputGroup>
        </div>

        <div v-if="managesCompanyAccess" class="mt-6 border-t border-line-light pt-6">
          <h3 class="text-sm font-medium text-heading">
            {{ $t('administration.users.companies_title') }}
          </h3>
          <p class="mt-1 mb-4 text-sm text-muted">
            {{ $t('administration.users.companies_description') }}
          </p>
          <AdminMembershipRows
            v-model="memberships"
            :companies="directAssignableCompanies"
            :locked-company-ids="ownedCompanyIds"
          />
        </div>

        <BaseButton
          :loading="isSaving"
          :disabled="isSaving"
          type="submit"
          class="mt-6"
        >
          <template #left="slotProps">
            <BaseIcon
              v-if="!isSaving"
              :class="slotProps.class"
              name="ArrowDownOnSquareIcon"
            />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </BaseCard>
    </form>
  </BasePage>

  <BaseGlobalLoader v-else />
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { required, requiredIf, email, minLength, helpers } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useAdminStore } from '../stores/admin.store'
import type { AdminMembership, UpdateUserData } from '../stores/admin.store'
import { useUserStore } from '@/scripts/stores/user.store'
import { useNotificationStore } from '@/scripts/stores/notification.store'
import { handleApiError } from '@/scripts/utils/error-handling'
import AdminMembershipRows from '../components/AdminMembershipRows.vue'
import { rolePresetService } from '@/scripts/api/services/role-preset.service'
import type { RolePreset } from '@/scripts/types/domain/role'

/**
 * Creates a user or edits one: the account, whether they are a super
 * administrator, and every company they belong to with their role there.
 */
interface UserFormData {
  name: string
  email: string
  phone: string
  password: string
  isSuperAdmin: boolean
}

const route = useRoute()
const router = useRouter()
const adminStore = useAdminStore()
const userStore = useUserStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isCreate = computed<boolean>(() => route.name === 'admin.users.create')
const pageTitle = computed<string>(() =>
  isCreate.value ? t('administration.users.new_user') : t('administration.users.edit_user')
)

const isLoading = ref<boolean>(true)
const isSaving = ref<boolean>(false)
const userId = ref<number | null>(null)
const companies = ref<Array<{ id: number; name: string }>>([])
const memberships = ref<AdminMembership[]>([])
const ownedCompanyIds = ref<number[]>([])
const rolePresets = ref<RolePreset[]>([])
const globalRoleKeys = ref<string[]>([])
const restrictedCompanyIds = ref<number[]>([])

const editingSelf = computed<boolean>(() => userId.value !== null && userId.value === userStore.currentUser?.id)
const managesCompanyAccess = computed<boolean>(() => !formData.isSuperAdmin)
const directCompanyIds = computed<Set<number>>(
  () => new Set(memberships.value.flatMap((row) => (row.id === null ? [] : [row.id])))
)
const restrictedCompanySet = computed<Set<number>>(() => new Set(restrictedCompanyIds.value))
const globalRoleOptions = computed<RolePreset[]>(() => rolePresets.value.filter((preset) => !preset.is_owner))
const restrictableCompanies = computed<Array<{ id: number; name: string }>>(() =>
  companies.value.filter(
    (company) => !directCompanyIds.value.has(company.id) || restrictedCompanySet.value.has(company.id)
  )
)
const directAssignableCompanies = computed<Array<{ id: number; name: string }>>(() =>
  companies.value.filter(
    (company) => !restrictedCompanySet.value.has(company.id) || directCompanyIds.value.has(company.id)
  )
)

const formData = reactive<UserFormData>({
  name: '',
  email: '',
  phone: '',
  password: '',
  isSuperAdmin: false,
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
    required: helpers.withMessage(t('validation.required'), requiredIf(isCreate)),
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8)
    ),
  },
}))

const v$ = useVuelidate(
  rules,
  computed(() => formData)
)

onMounted(async () => {
  // Installs have few companies; one generous page keeps the picker local.
  const companyPage = await adminStore.fetchCompanies({ limit: 500, orderByField: 'name', orderBy: 'asc' })
  companies.value = companyPage.data.map((company) => ({ id: company.id, name: company.name }))
  rolePresets.value = await rolePresetService.list()

  if (!isCreate.value) {
    const response = await adminStore.fetchUser(route.params.id as string)
    const user = response.data
    userId.value = user.id

    formData.name = user.name
    formData.email = user.email
    formData.phone = user.phone ?? ''
    formData.isSuperAdmin = user.is_super_admin
    globalRoleKeys.value = user.global_role_keys ?? []
    restrictedCompanyIds.value = user.restricted_company_ids ?? []

    const userCompanies = user.companies ?? []
    for (const company of userCompanies) {
      if (!companies.value.some((known) => known.id === company.id)) {
        companies.value.push({ id: company.id, name: company.name })
      }
    }

    memberships.value = userCompanies.map((company) => ({
      id: company.id,
      roles: user.roles?.filter((role) => role.scope === company.id).map((role) => role.name) ?? [],
      include_global_roles: company.include_global_roles ?? false,
    }))
    ownedCompanyIds.value = userCompanies
      .filter((company) => company.owner_id === user.id)
      .map((company) => company.id)
  }

  isLoading.value = false
})

async function submitForm(): Promise<void> {
  v$.value.$touch()

  if (v$.value.$invalid) {
    return
  }

  isSaving.value = true

  const data: UpdateUserData = {
    name: formData.name,
    email: formData.email,
    phone: formData.phone,
  }

  const companyMemberships = memberships.value
      .filter((row): row is { id: number; roles: string[]; include_global_roles: boolean } => row.id !== null && row.roles.length > 0)
      .map((row) => ({
        id: row.id,
        roles: row.roles,
        include_global_roles: row.include_global_roles,
      }))

  if (managesCompanyAccess.value) {
    data.global_roles = globalRoleKeys.value
    data.restricted_company_ids = restrictedCompanyIds.value
    data.companies = companyMemberships
  } else if (isCreate.value) {
    data.companies = []
  }

  if (!editingSelf.value) {
    data.is_super_admin = formData.isSuperAdmin
  }

  if (formData.password) {
    data.password = formData.password
  }

  try {
    if (isCreate.value) {
      await adminStore.createUser(data)
    } else {
      await adminStore.updateUser(route.params.id as string, data)
    }

    router.push({ name: 'admin.users.index' })
  } catch (err: unknown) {
    notificationStore.showNotification({ type: 'error', message: handleApiError(err).message })
  } finally {
    isSaving.value = false
  }
}
</script>
