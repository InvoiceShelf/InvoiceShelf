<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="w-full max-w-lg p-8">
      <div class="text-center mb-8">
        <BaseIcon
          name="BuildingOfficeIcon"
          class="w-16 h-16 mx-auto text-gray-400 mb-4"
        />
        <h1 class="text-2xl font-semibold text-gray-900">
          {{ $t('general.welcome') }}
        </h1>
      </div>

      <!-- Pending Invitations -->
      <div v-if="invitationStore.pendingInvitations.length > 0">
        <h2 class="text-lg font-medium text-gray-700 mb-4 text-center">
          {{ $t('members.pending_invitations') }}
        </h2>
        <div class="space-y-3">
          <BaseCard
            v-for="invitation in invitationStore.pendingInvitations"
            :key="invitation.id"
            class="p-4"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-medium text-gray-900">
                  {{ invitation.company?.name }}
                </p>
                <p class="text-sm text-gray-500">
                  {{ invitation.role?.title }} &middot;
                  {{ $t('members.invited_by') }}: {{ invitation.invited_by?.name }}
                </p>
              </div>
              <div class="flex space-x-2">
                <BaseButton
                  size="sm"
                  @click="acceptInvitation(invitation.token)"
                >
                  {{ $t('general.accept') }}
                </BaseButton>
                <BaseButton
                  variant="danger"
                  size="sm"
                  @click="declineInvitation(invitation.token)"
                >
                  {{ $t('general.decline') }}
                </BaseButton>
              </div>
            </div>
          </BaseCard>
        </div>
      </div>

      <!-- No Invitations -->
      <div v-else class="text-center">
        <p class="text-gray-500">
          You don't belong to any company yet. Ask your administrator to invite you.
        </p>
      </div>

      <!-- Logout -->
      <div class="mt-8 text-center">
        <BaseButton variant="primary-outline" @click="logout">
          {{ $t('navigation.logout') }}
        </BaseButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInvitationStore } from '@/scripts/admin/stores/invitation'
import { useAuthStore } from '@/scripts/admin/stores/auth'

const invitationStore = useInvitationStore()
const authStore = useAuthStore()
const router = useRouter()

onMounted(async () => {
  await invitationStore.fetchPending()
})

async function acceptInvitation(token) {
  await invitationStore.accept(token)
  router.push('/admin/dashboard')
}

async function declineInvitation(token) {
  await invitationStore.decline(token)
}

async function logout() {
  await authStore.logout()
  router.push('/login')
}
</script>
