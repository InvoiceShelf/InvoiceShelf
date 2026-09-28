<template>
  <div class="space-y-5">
    <BaseCard class="relative focus-within:z-10" container-class="p-4 md:p-5">
      <div class="grid gap-5 md:grid-cols-2">
        <PurchaseField
          v-for="field in fields"
          :key="field"
          :label="$t(`purchases.${field}`)"
          :name="field"
          :required="field === 'name'"
          ><BaseInput
            v-model="form[field]"
            :type="
              field === 'email' ? 'email' : field === 'website' ? 'url' : 'text'
            "
            :required="field === 'name'"
            maxlength="255"
        /></PurchaseField>
        <PurchaseField
          :label="$t('purchases.currency')"
          name="currency_id"
          required
          ><BaseMultiselect
            v-model="form.currency_id"
            :options="[
              ...options.currencies.map((currency) => ({
                value: currency.id,
                label: `${currency.code} — ${currency.name}`,
              })),
            ]"
            searchable
            :can-deselect="false"
        /></PurchaseField>
        <PurchaseField
          :label="$t('purchases.payment_terms')"
          name="payment_terms"
          required
          ><BaseInput
            v-model.number="form.payment_terms"
            type="number"
            min="0"
            max="3650"
            required
        /></PurchaseField>
        <PurchaseField
          :label="$t('purchases.default_category')"
          name="expense_category_id"
          ><PurchaseLookupSelect
            v-model="form.expense_category_id"
            kind="category"
            :options="options.categories"
        /></PurchaseField>
        <PurchaseCustomFieldInputs
          :fields="form.customFields"
          :scope="customFieldScope"
        />
        <BaseSwitch
          v-model="form.enabled"
          class="text-sm font-medium text-body"
          :label-right="$t('purchases.active_supplier')"
        />
      </div>
    </BaseCard>

    <BaseCard
      v-for="(address, index) in form.addresses"
      :key="index"
      container-class="p-4 md:p-5"
    >
      <div class="mb-4 flex items-center justify-between gap-3">
        <h2 class="font-medium text-heading">
          {{ $t('purchases.address') }} {{ index + 1 }}
        </h2>
        <BaseButton
          v-if="form.addresses.length > 1"
          type="button"
          variant="white"
          @click="form.addresses.splice(index, 1)"
          >{{ $t('purchases.remove') }}</BaseButton
        >
      </div>
      <div class="grid gap-5 md:grid-cols-2">
        <PurchaseField
          v-for="field in addressFields"
          :key="field"
          :label="$t(`purchases.${field}`)"
          :name="`addresses.${index}.${field}`"
          ><BaseInput v-model="address[field]" maxlength="255"
        /></PurchaseField>
        <PurchaseField
          :label="$t('purchases.country')"
          :name="`addresses.${index}.country_id`"
          ><BaseMultiselect
            v-model="address.country_id"
            :options="[
              ...countries.map((country) => ({
                value: country.id,
                label: country.name,
              })),
            ]"
            searchable
        /></PurchaseField>
      </div>
    </BaseCard>
    <BaseButton
      v-if="form.addresses.length < 5"
      type="button"
      variant="white"
      @click="form.addresses.push(emptySupplierAddress())"
      >{{ $t('purchases.add_address') }}</BaseButton
    >

    <BaseCard class="relative focus-within:z-10" container-class="p-4 md:p-5"
      ><PurchaseField :label="$t('purchases.notes')" name="notes"
        ><BaseTextarea v-model="form.notes" maxlength="10000" /></PurchaseField
    ></BaseCard>
  </div>
</template>
<script setup lang="ts">
import type {
  SupplierDraft,
  PurchaseOptions,
} from '@/scripts/types/domain/purchase'
import PurchaseField from './PurchaseField.vue'
import PurchaseCustomFieldInputs from './PurchaseCustomFieldInputs.vue'
import PurchaseLookupSelect from './PurchaseLookupSelect.vue'
import { emptySupplierAddress } from '../composables/use-supplier-editor'
const form = defineModel<SupplierDraft>({ required: true })
defineProps<{
  customFieldScope: string
  options: PurchaseOptions
  countries: Array<{ id: number; name: string }>
}>()
const fields = [
  'name',
  'contact_name',
  'email',
  'phone',
  'website',
  'tax_id',
] as const
const addressFields = [
  'address_street_1',
  'address_street_2',
  'city',
  'state',
  'zip',
] as const
</script>
