<template>
  <form @submit.prevent="updateAvatar">
    <BaseInputGrid>
      <BaseInputGroup
        :label="$t('settings.account_settings.profile_picture')"
      >
        <BaseFileUploader
          v-model="imgFiles"
          :avatar="true"
          accept="image/*"
          @change="onFileInputChange"
          @remove="onFileInputRemove"
        />
      </BaseInputGroup>
    </BaseInputGrid>

    <BaseButton :loading="isSaving" :disabled="isSaving" class="mt-6">
      <template #left="slotProps">
        <BaseIcon
          v-if="!isSaving"
          name="ArrowDownOnSquareIcon"
          :class="slotProps.class"
        />
      </template>
      {{ $t('settings.company_info.save') }}
    </BaseButton>
  </form>
</template>

<script setup>
import { ref } from 'vue'
import { useUserStore } from '@/scripts/admin/stores/user'

const userStore = useUserStore()

const isSaving = ref(false)
let avatarFileBlob = ref(null)
let imgFiles = ref([])
const isAdminAvatarRemoved = ref(false)

if (userStore.currentUser.avatar) {
  imgFiles.value.push({
    image: userStore.currentUser.avatar,
  })
}

function onFileInputChange(fileName, file) {
  avatarFileBlob.value = file
}

function onFileInputRemove() {
  avatarFileBlob.value = null
  isAdminAvatarRemoved.value = true
}

async function updateAvatar() {
  if (!avatarFileBlob.value && !isAdminAvatarRemoved.value) {
    return
  }

  isSaving.value = true

  try {
    let data = new FormData()

    if (avatarFileBlob.value) {
      data.append('admin_avatar', avatarFileBlob.value)
    }
    data.append('is_admin_avatar_removed', isAdminAvatarRemoved.value)

    await userStore.uploadAvatar(data)
    avatarFileBlob.value = null
    isAdminAvatarRemoved.value = false
  } finally {
    isSaving.value = false
  }
}
</script>
