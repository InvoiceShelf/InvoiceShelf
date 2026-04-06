import type { RouteParamValue } from 'vue-router'

export function resolveCompanySlug(
  companyParam: RouteParamValue | RouteParamValue[] | undefined
): string {
  if (Array.isArray(companyParam)) {
    return companyParam[0] ?? ''
  }

  return companyParam ?? ''
}

export function buildCustomerPortalPath(
  companySlug: string,
  path: string = ''
): string {
  const normalizedPath = path.replace(/^\/+/, '')

  if (!normalizedPath) {
    return `/${companySlug}/customer`
  }

  return `/${companySlug}/customer/${normalizedPath}`
}

export function prefixCustomerPortalMenuLink(
  companySlug: string,
  link: string
): string {
  const normalizedLink = link.replace(/^\/+/, '')

  if (normalizedLink === 'customer') {
    return `/${companySlug}/customer`
  }

  if (normalizedLink.startsWith('customer/')) {
    return `/${companySlug}/${normalizedLink}`
  }

  return buildCustomerPortalPath(companySlug, normalizedLink)
}
