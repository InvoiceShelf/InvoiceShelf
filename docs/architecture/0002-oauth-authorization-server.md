# ADR 0002: An OAuth authorization server in the Accounts context

Date: 2026-09-23

Status: Accepted

## Context

Outside clients are about to receive tokens that act for a user: first the MCP
server, which lets AI clients such as claude.ai, ChatGPT, Claude Code and
Cursor work in a user's company, and possibly the mobile app later. Remote MCP
connectors require OAuth 2.1: protected resource and authorization server
metadata, dynamic client registration, the authorization code grant with PKCE,
and refresh tokens. Sanctum's personal access tokens cover none of that.

Sign-in is also about to change for other reasons. OIDC single sign-on (issue
#124) and a second factor are planned, and both alter the same session sign-in
that OAuth consent depends on. Building the OAuth parts as a feature of the MCP
server would have tied them to one consumer and left OIDC to rework them.

## Decision

Laravel Passport runs as the installation's authorization server, owned by the
Accounts context.

- **A separate guard.** Passport issues tokens for the `oauth` guard
  (`passport` driver, `users` provider). Sanctum keeps `auth:sanctum` for the
  SPA and the thin clients. Neither guard accepts the other's tokens. A feature
  is a scope on this guard (the MCP server is `mcp:use`), not a guard of its
  own.
- **No change to the user model.** Passport's `HasApiTokens` cannot sit beside
  Sanctum's: the two declare `$accessToken` with different types, which PHP
  rejects, and their `createToken` signatures differ. It is not needed.
  Passport 13 declares `OAuthenticatable` only in PHPDoc, and its token guard
  only calls `withAccessToken()`, which Sanctum's untyped version accepts.
  Acceptance tests in `tests/Feature/Accounts/OAuthServerTest.php` pin this.
  If a later Passport major type-hints the contract, the fallback is a request
  guard that validates the bearer token with League's resource server.
- **Consumers switch it on.** A feature that issues tokens registers with
  `OAuthServer::consumer()`. Every Passport route carries `oauth.enabled` and
  answers 404 while no consumer is on, so an installation that uses none of
  them exposes nothing.
- **Browser consent only.** Only the authorization code and refresh token
  grants are used; the device grant is off, the password and implicit grants
  stay off, and no client is created for client credentials. Consent always
  runs on the `web` session, so whatever later guards the session sign-in
  (OIDC, a required second factor) guards every grant as well.
- **One post-login rule.** `PostLoginRedirect` decides where a signed-in user
  goes next, for the guest redirect, the `guest` middleware and, mirrored, the
  SPA login view. The OIDC callback and a second-factor step are to use it too.
- **One revoker.** `AccessRevoker` ends grants: all of a user's on account
  deletion, one client's from a connections screen, every grant after the
  signing keys change. It announces removal from a company with an event, so
  consumers that bind grants to a company end them without Accounts knowing
  about the consumers.
- **Keys.** `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` when set, which
  multi-replica deployments need. Otherwise files in `storage/`, written by
  `oauth:keys` (the container entrypoint runs it with `--if-missing`) or by the
  web request that switches a consumer on. Replacing the keys revokes every
  token.

Access tokens last 60 minutes and refresh tokens 30 days, configurable in
`config/passport.php`. The tables follow the schema's conventions: unsigned
integer user ids and no foreign key constraints.

## Consequences

- A second token system exists. `auth:oauth` and `auth:sanctum` must never be
  combined on one route by accident; the boundary tests list every Passport
  route and its middleware.
- The in-app updater clears cached configuration, routes and compiled
  services when it finishes, so an installation that ran `config:cache` picks
  up the new guard.
- Passport brings `firebase/php-jwt`, `lcobucci/jwt` and phpseclib 4 into core.
  OIDC can verify identity tokens with them rather than by hand.
- The mobile app still signs in with a password for a Sanctum token. Moving it
  to this server (authorization code with PKCE in the system browser) would let
  SSO and a second factor cover it; that is left to the OIDC design.
