import { client } from '../client'
import { API } from '../endpoints'
import type { PersonalAccessToken } from '@/scripts/types/domain/personal-access-token'
import type { ApiResponse } from '@/scripts/types/api'

/**
 * The caller's own personal access tokens: the devices signed in to this
 * account. Tokens never expire, so listing and revoking them is the only way
 * a lost device is cut off.
 *
 * The routes are scoped to the caller on the server, so nothing here takes an
 * account: a token id belonging to somebody else is answered 404.
 */
export const tokenService = {
  /**
   * Newest first, as the server orders them.
   */
  async listTokens(): Promise<ApiResponse<PersonalAccessToken[]>> {
    const { data } = await client.get<ApiResponse<PersonalAccessToken[]>>(API.AUTH_TOKENS)
    return data
  },

  /**
   * Revoking the token that carries this request signs the caller out; every
   * other id leaves the current session alone.
   */
  async revokeToken(id: number): Promise<void> {
    await client.delete(`${API.AUTH_TOKENS}/${id}`)
  },
}
