<script setup lang="ts">
import { computed } from 'vue'
import CustomFieldsGrid from './CustomFieldsGrid.vue'

/**
 * The custom-field block a record's form carries, heading and all.
 *
 * Every form that can hold answers renders the same divider, heading and
 * grid, so it lives here once. `type` is the model the definitions are
 * attached to, which the listing endpoint reads as `model_type`.
 */
const props = withDefaults(
  defineProps<{
    type: string
    store: Record<string, any>
    storeProp: string
    isEdit?: boolean
    isLoading?: boolean | null
    scope?: string
  }>(),
  {
    isEdit: false,
    isLoading: null,
    scope: 'customFields',
  }
)

/**
 * Whether this company has defined anything for this model. The grid fills
 * the list itself once it has asked the server, so only the chrome around it
 * is withheld -- gating the grid too would mean it never asked.
 */
const hasCustomFields = computed<boolean>(
  () => (props.store[props.storeProp]?.customFields?.length ?? 0) > 0
)
</script>

<template>
  <BaseDivider
    v-if="hasCustomFields"
    class="mb-5 md:mb-8"
  />

  <div class="grid grid-cols-5 gap-2 mb-8">
    <h6
      v-if="hasCustomFields"
      class="col-span-5 text-lg font-semibold text-left lg:col-span-1"
    >
      {{ $t('settings.custom_fields.title') }}
    </h6>

    <div class="col-span-5 lg:col-span-4">
      <CustomFieldsGrid
        :type="type"
        :store="store"
        :store-prop="storeProp"
        :is-edit="isEdit"
        :is-loading="isLoading"
        :custom-field-scope="scope"
      />
    </div>
  </div>
</template>
