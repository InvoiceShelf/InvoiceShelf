<script setup lang="ts">
import { ref } from 'vue'
import { useUserStore } from '../../../../stores/user.store'
import { useNotificationStore } from '../../../../stores/notification.store'
import { useI18n } from 'vue-i18n'

interface AvatarFile {
  image: string
}

const userStore = useUserStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSaving = ref<boolean>(false)
const imgFiles = ref<AvatarFile[]>([])
const avatarFileBlob = ref<File | null>(null)

if (userStore.currentUser?.avatar) {
  imgFiles.value.push({ image: userStore.currentUser.avatar as string })
}

function onFileInputChange(_fileName: string, file: File): void {
  avatarFileBlob.value = file
}

function onFileInputRemove(): void {
  avatarFileBlob.value = null
}

async function updateAvatar(): Promise<void> {
  if (!avatarFileBlob.value) return

  isSaving.value = true
  try {
    const formData = new FormData()
    formData.append('admin_avatar', avatarFileBlob.value)

    await userStore.uploadAvatar(formData)

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.account_settings.updated_message',
    })

    avatarFileBlob.value = null
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <form @submit.prevent="updateAvatar">
    <BaseSettingCard
      :title="$t('settings.account_settings.profile_picture')"
      :description="$t('settings.account_settings.profile_picture_description')"
    >
      <BaseInputGrid class="mt-5">
        <BaseInputGroup :label="$t('settings.account_settings.profile_picture')">
          <BaseFileUploader
            v-model="imgFiles"
            :avatar="true"
            accept="image/*"
            @change="onFileInputChange"
            @remove="onFileInputRemove"
          />
        </BaseInputGroup>
      </BaseInputGrid>

      <BaseButton :loading="isSaving" :disabled="isSaving" type="submit" class="mt-6">
        <template #left="slotProps">
          <BaseIcon v-if="!isSaving" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
        </template>
        {{ $t('settings.company_info.save') }}
      </BaseButton>
    </BaseSettingCard>
  </form>
</template>
