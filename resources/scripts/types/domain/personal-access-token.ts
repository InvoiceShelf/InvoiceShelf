/**
 * One signed-in device, as the account that owns it sees it.
 *
 * The token value itself is shown once at sign-in and never again, so what the
 * server publishes is only what identifies a device in a list. `current` marks
 * the token carrying the request: it is false for every row when the caller is
 * the session-authenticated web app, which holds no token row of its own.
 */
export interface PersonalAccessToken {
  id: number
  name: string
  last_used_at: string | null
  created_at: string
  current: boolean
}
