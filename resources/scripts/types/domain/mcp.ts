/**
 * What a connection may do: read the company's records, or also change them,
 * send documents and record payments.
 */
export type McpAccess = 'read' | 'write'

/**
 * An AI app the signed-in user connected to one of their companies.
 *
 * The client name is whatever the app registered, so the page leads with the
 * host it was sent back to, which the app cannot choose.
 */
export interface McpConnection {
  id: number
  client_name: string | null
  redirect_host: string | null
  company: { id: number; name: string } | null
  access: McpAccess
  last_used_at: string | null
  created_at: string | null
}

/** Where apps connect, as any signed-in user sees it. */
export interface McpServerInfo {
  enabled: boolean
  server_url: string
}

/** Where the OAuth signing keys come from: the environment, files, or nowhere yet. */
export type McpKeyStatus = 'env' | 'file' | 'missing'

/** The server as the super admin manages it. */
export interface McpServerSettings extends McpServerInfo {
  secure: boolean
  key_status: McpKeyStatus
  default_redirect_domains: string[]
  redirect_domains: string[]
  connection_count: number
}
