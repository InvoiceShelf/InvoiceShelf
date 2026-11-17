import { client } from '../client'
import { API } from '../endpoints'
import type { User } from '@/scripts/types/domain/user'
import type { ApiResponse } from '@/scripts/types/api'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface LoginResponse {
  token: string
  user: User
}

/**
 * Bearer sign-in. `username` is the account's email address; `device_name`
 * names the token so a lost device can be cut off from another session.
 */
export interface TokenLoginPayload {
  username: string
  password: string
  device_name: string
}

export interface TokenLoginResponse {
  type: string
  token: string
}

/** A sign-in with SSO the mobile client started (see `loginWithSso`). */
export interface SsoHandoff {
  id: string
  /** Proves this device started the hand-off. It never leaves the app. */
  secret: string
  /** The server's sign-in page, to open in a browser. */
  url: string
  /** Where the server sends that browser once the sign-in is done. */
  app_link: string
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  email: string
  password: string
  password_confirmation: string
  token: string
}

export interface InvitationDetails {
  email: string
  company_name: string
  invited_by: string
}

export interface RegisterWithInvitationPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  invitation_token: string
}

export interface RegisterWithInvitationResponse {
  type: string
  token: string
}

export const authService = {
  async refreshCsrfCookie(): Promise<void> {
    await client.get(API.CSRF_COOKIE)
  },

  async login(payload: LoginPayload): Promise<ApiResponse<LoginResponse>> {
    await client.get(API.CSRF_COOKIE)
    const { data } = await client.post(API.LOGIN, payload)
    return data
  },

  async logout(): Promise<void> {
    await client.post(API.LOGOUT)
  },

  /**
   * Sign in for a client that has no cookie jar to put a session in.
   */
  async loginWithToken(payload: TokenLoginPayload): Promise<TokenLoginResponse> {
    const { data } = await client.post<TokenLoginResponse>(API.TOKEN_LOGIN, payload)
    return data
  },

  /**
   * Revoke the token that carries this request, and only that one, so the
   * account's other devices stay signed in.
   */
  async logoutWithToken(): Promise<void> {
    await client.post(API.TOKEN_LOGOUT)
  },

  /**
   * Start a sign-in with SSO for a client, which has no session for the
   * browser sign-in to end in.
   */
  async startSsoHandoff(payload: { device_name: string }): Promise<SsoHandoff> {
    const { data } = await client.post<SsoHandoff>(API.SSO_HANDOFFS, payload)
    return data
  },

  /**
   * Trade the code the browser brought back, with the hand-off's secret, for
   * a token named after this device.
   */
  async finishSsoHandoff(id: string, payload: { secret: string; code: string }): Promise<TokenLoginResponse> {
    const { data } = await client.post<TokenLoginResponse>(
      `${API.SSO_HANDOFFS}/${encodeURIComponent(id)}/token`,
      payload
    )
    return data
  },

  async forgotPassword(payload: ForgotPasswordPayload): Promise<ApiResponse<{ success: boolean }>> {
    const { data } = await client.post(API.FORGOT_PASSWORD, payload)
    return data
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<ApiResponse<{ success: boolean }>> {
    const { data } = await client.post(API.RESET_PASSWORD, payload)
    return data
  },

  async check(): Promise<ApiResponse<{ success: boolean }>> {
    const { data } = await client.get(API.AUTH_CHECK)
    return data
  },

  async getInvitationDetails(token: string): Promise<ApiResponse<InvitationDetails>> {
    const { data } = await client.get(`${API.INVITATION_DETAILS}/${token}/details`)
    return data
  },

  async registerWithInvitation(payload: RegisterWithInvitationPayload): Promise<RegisterWithInvitationResponse> {
    const { data } = await client.post<RegisterWithInvitationResponse>(API.REGISTER_WITH_INVITATION, payload)
    return data
  },
}
