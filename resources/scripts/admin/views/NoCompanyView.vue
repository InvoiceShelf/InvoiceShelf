<template>
  <div class="flex items-center justify-center min-h-[70vh]">
    <div class="w-full max-w-xl p-8">
      <div class="text-center mb-8">
        <BaseIcon
          name="BuildingOfficeIcon"
          class="w-16 h-16 mx-auto text-gray-300 mb-4"
        />
        <h1 class="text-2xl font-semibold text-gray-900">
          {{ $t('general.welcome') }}, {{ userStore.currentUser.name }}
        </h1>
        <p class="mt-2 text-sm text-gray-500">
          {{ $t('general.no_company_description') }}
        </p>
      </div>

      <!-- Pending Invitations -->
      <div v-if="invitationStore.pendingInvitations.length > 0">
        <h2
          class="text-sm font-semibold uppercase tracking-wide text-gray-400 mb-3"
        >
          {{ $t('members.pending_invitations') }}
        </h2>
        <div class="space-y-3">
          <div
            v-for="invitation in invitationStore.pendingInvitations"
            :key="invitation.id"
            class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200"
          >
            <div>
              <p class="font-medium text-gray-900">
                {{ invitation.company?.name }}
              </p>
              <p class="text-sm text-gray-500">
                {{ invitation.role?.title }} &middot;
                {{ $t('members.invited_by') }}:
                {{ invitation.invited_by?.name }}
              </p>
            </div>
            <div class="flex space-x-2 ml-4 shrink-0">
              <BaseButton
                size="sm"
                @click="acceptInvitation(invitation.token)"
              >
                {{ $t('general.accept') }}
              </BaseButton>
              <BaseButton
                variant="white"
                size="sm"
                @click="declineInvitation(invitation.token)"
              >
                {{ $t('general.decline') }}
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInvitationStore } from '@/scripts/admin/stores/invitation'
import { useUserStore } from '@/scripts/admin/stores/user'

const invitationStore = useInvitationStore()
const userStore = useUserStore()
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
</script>
