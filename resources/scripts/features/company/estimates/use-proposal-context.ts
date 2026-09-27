import { mapProposalFields } from '@/scripts/api/services/proposal-fields'
import { useCompanyStore } from '@/scripts/stores/company.store'
import { inject } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useEstimateStore, useQuoteStore } from './store'
import type { ProposalKind } from '@/scripts/api/services/proposal-fields'

export function useProposalKind(): ProposalKind {
  const supplied = inject<ProposalKind | null>('salesProposalKind', null)
  return (
    supplied ??
    (useRoute().meta.proposalKind === 'quote' ? 'quote' : 'estimate')
  )
}

export function useProposalStore() {
  return useProposalKind() === 'quote' ? useQuoteStore() : useEstimateStore()
}

export function useProposalContext() {
  const kind = useProposalKind()
  const i18n = useI18n()
  const textKey = (key: string) =>
    kind === 'quote' ? key.replaceAll('estimate', 'quote') : key
  const t = ((...args: Parameters<typeof i18n.t>) => {
    const [key, ...rest] = args
    return Reflect.apply(i18n.t, i18n, [
      typeof key === 'string' ? textKey(key) : key,
      ...rest,
    ]) as string
  }) as typeof i18n.t
  return {
    kind,
    t,
    textKey,
    basePath: `/admin/${kind}s`,
    modelType: kind === 'quote' ? ('Quote' as const) : ('Estimate' as const),
  }
}

export function useProposalSettings() {
  const kind = useProposalKind()
  const company = useCompanyStore()
  const settings =
    kind === 'quote'
      ? mapProposalFields(
          Object.fromEntries(
            Object.entries(company.selectedCompanySettings).filter(
              ([key]) => !key.startsWith('estimate_'),
            ),
          ),
          'quote',
          'estimate',
        )
      : company.selectedCompanySettings
  const updateSettings = (
    request: Parameters<typeof company.updateCompanySettings>[0],
  ) =>
    company.updateCompanySettings({
      ...mapProposalFields(request, 'estimate', kind),
      message:
        kind === 'quote'
          ? request.message?.replaceAll('estimate', 'quote')
          : request.message,
    })
  return { settings, updateSettings }
}
