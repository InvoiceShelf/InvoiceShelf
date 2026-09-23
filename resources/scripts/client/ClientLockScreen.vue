<script setup lang="ts">
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

/**
 * The app lock's front door.
 *
 * It is deliberately its own screen and not a route: it has to cover
 * whatever the app was showing, the login screen included, and a route
 * would be one `router.push` away from being stepped around.
 *
 * Nothing here decides anything. `client/lock.ts` owns when the lock goes
 * up and what the device is asked; this only draws it and reports the two
 * things the user can do.
 */

interface Props {
  /** True while the system prompt is up, so it is not asked for twice. */
  busy?: boolean
}

withDefaults(defineProps<Props>(), {
  busy: false,
})

interface Emits {
  (e: 'unlock'): void
  (e: 'sign-out'): void
}

defineEmits<Emits>()
</script>

<template>
  <Teleport to="body">
    <div
      role="dialog"
      aria-modal="true"
      class="
        fixed inset-0 z-[100] flex flex-col items-center justify-center
        px-6 py-12
        bg-surface-tertiary bg-glass-gradient
      "
    >
      <MainLogo class="w-auto h-10 text-heading" />

      <h1 class="mt-10 text-xl font-semibold text-center text-heading">
        {{ $t('client.app_lock_locked_heading') }}
      </h1>

      <p class="mt-2 max-w-xs text-sm text-center text-muted">
        {{ $t('client.app_lock_locked_description') }}
      </p>

      <BaseButton
        type="button"
        size="lg"
        class="mt-8 w-full max-w-xs justify-center"
        :loading="busy"
        :disabled="busy"
        @click="$emit('unlock')"
      >
        {{ $t('client.app_lock_unlock') }}
      </BaseButton>

      <button
        type="button"
        class="
          mt-6 text-sm text-primary-600 transition-colors hover:text-body
        "
        @click="$emit('sign-out')"
      >
        {{ $t('client.app_lock_sign_out') }}
      </button>
    </div>
  </Teleport>
</template>
