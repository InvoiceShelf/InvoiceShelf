import { client } from '../client'
import { API } from '../endpoints'
import type {
  McpConnection,
  McpServerInfo,
  McpServerSettings,
} from '@/scripts/types/domain/mcp'

/**
 * The MCP server: where AI apps connect, the apps the caller has connected,
 * and, for the super admin, the server switch itself.
 *
 * Connections belong to their user whatever company is selected, so none of
 * these calls depend on the company header.
 */
export const mcpService = {
  async server(): Promise<McpServerInfo> {
    const { data } = await client.get<{ data: McpServerInfo }>(API.MCP_SERVER)
    return data.data
  },

  async listConnections(): Promise<McpConnection[]> {
    const { data } = await client.get<{ data: McpConnection[] }>(API.MCP_CONNECTIONS)
    return data.data
  },

  /**
   * A connection can only be narrowed to read-only here; widening it takes a
   * new approval in the app.
   */
  async makeReadOnly(id: number): Promise<McpConnection> {
    const { data } = await client.patch<{ data: McpConnection }>(`${API.MCP_CONNECTIONS}/${id}`, { access: 'read' })
    return data.data
  },

  async disconnect(id: number): Promise<void> {
    await client.delete(`${API.MCP_CONNECTIONS}/${id}`)
  },

  async settings(): Promise<McpServerSettings> {
    const { data } = await client.get<{ data: McpServerSettings }>(API.SUPER_ADMIN_MCP)
    return data.data
  },

  async updateSettings(payload: { enabled?: boolean; redirect_domains?: string[] }): Promise<McpServerSettings> {
    const { data } = await client.put<{ data: McpServerSettings }>(API.SUPER_ADMIN_MCP, payload)
    return data.data
  },

  /**
   * New signing keys: every connected app is signed out and has to connect
   * again.
   */
  async regenerateKeys(): Promise<McpServerSettings> {
    const { data } = await client.post<{ data: McpServerSettings }>(API.SUPER_ADMIN_MCP_KEYS)
    return data.data
  },
}
