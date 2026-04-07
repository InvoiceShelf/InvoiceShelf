<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Ref, ComputedRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { client } from '@/scripts/api/client'
import InvoiceInformationCard from './InvoiceInformationCard.vue'
import type { Currency } from '@/scripts/types/domain'
import type { Company } from '@/scripts/types/domain'
import type { Customer } from '@/scripts/types/domain'

declare global {
  interface Window {
    customer_logo?: string | null
  }
}

interface InvoicePublicData {
  invoice_number: string
  paid_status: string
  total: number
  formatted_notes?: string | null
  payment_module_enabled?: boolean
  currency?: Currency
  company?: Pick<Company, 'name' | 'slug'>
  customer?: Pick<Customer, 'name'>
}

const invoiceData = ref<InvoicePublicData | null>(null) as Ref<InvoicePublicData | null>
const route = useRoute()
const router = useRouter()

loadInvoice()

async function loadInvoice(): Promise<void> {
  const hash = route.params.hash as string
  const { data } = await client.get(`/customer/invoices/${hash}`)
  invoiceData.value = data.data
}

const shareableLink = computed<string>(() => {
  return route.path + '?pdf'
})

function getLogo(): URL {
  const imgUrl = new URL('$images/logo-gray.png', import.meta.url)
  return imgUrl
}

const customerLogo = computed<string | false>(() => {
  if (window.customer_logo) {
    return window.customer_logo
  }

  return false
})

const pageTitle: ComputedRef<string> = computed(() => invoiceData.value?.invoice_number ?? '')

function payInvoice(): void {
  const resolved = router.resolve({ name: 'invoice.pay' })
  if (resolved.matched.length) {
    router.push({
      name: 'invoice.pay',
      params: {
        hash: route.params.hash as string,
        company: invoiceData.value?.company?.slug ?? '',
      },
    })
  }
}
</script>

<template>
  <div class="h-screen overflow-y-auto min-h-0">
    <div class="bg-linear-to-r from-primary-500 to-primary-400 h-5"></div>

    <div
      class="
        relative
        p-6
        pb-28
        px-4
        md:px-6
        w-full
        md:w-auto md:max-w-xl
        mx-auto
      "
    >
      <BasePageHeader :title="pageTitle || ''">
        <template #actions>
          <div
            class="
              flex flex-col
              md:flex-row
              absolute
              md:relative
              bottom-2
              left-0
              px-4
              md:px-0
              w-full
              md:space-x-4 md:space-y-0
              space-y-2
            "
          >
            <a :href="shareableLink" target="_blank" class="block w-full">
              <BaseButton
                variant="primary-outline"
                class="justify-center w-full"
              >
                {{ $t('general.download_pdf') }}
              </BaseButton>
            </a>

            <BaseButton
              v-if="
                invoiceData &&
                invoiceData.paid_status !== 'PAID' &&
                invoiceData.payment_module_enabled
              "
              variant="primary"
              class="justify-center"
              @click="payInvoice"
            >
              {{ $t('general.pay_invoice') }}
            </BaseButton>
          </div>
        </template>
      </BasePageHeader>

      <InvoiceInformationCard :invoice="invoiceData" />

      <div
        v-if="!customerLogo"
        class="flex items-center justify-center mt-4 text-muted font-normal"
      >
        Powered by
        <a href="https://invoiceshelf.com" target="_blank">
          <img :src="getLogo().href" class="h-4 ml-1 mb-1" />
        </a>
      </div>
    </div>
  </div>
</template>
