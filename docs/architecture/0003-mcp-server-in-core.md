# ADR 0003: The MCP server is a core platform capability

Date: 2026-09-23

Status: Accepted

## Context

AI clients (claude.ai, ChatGPT, Claude Desktop, Claude Code, Cursor) speak the
Model Context Protocol. Users want to point the client they already use at
their own InvoiceShelf and have it look up, create, send and settle records.
The AI Assistant module already offers an in-app chat, but it depends on a
provider the administrator pays for and it is read-only by contract.

The module program's rule is that modules add new capabilities and nothing
leaves core. An MCP server is a new capability, so a module was considered.
It does not fit: modules may only require `php`, `invoiceshelf/modules` and
`ext-*`, so `laravel/mcp` and `laravel/passport` would have to be host
dependencies anyway; the OAuth authorization server it needs is identity
infrastructure with root `/.well-known` endpoints; and the SDK has no write
contracts, while full operations need the domain services directly.

## Decision

The MCP server lives in `app/Platform/Mcp`, built on `laravel/mcp` and on the
OAuth server of ADR 0002.

- **Off by default.** A super administrator switches it on (or runs
  `php artisan mcp:enable`). Until then `/mcp`, the discovery documents,
  client registration and every OAuth route answer 404.
- **OAuth 2.1 from the start.** Protected resource and authorization server
  metadata, dynamic client registration restricted to known redirect origins
  (claude.ai, claude.com, chatgpt.com, loopback on any port, the Cursor and VS
  Code schemes, plus origins an administrator adds; never a wildcard), the
  authorization code grant with PKCE, and refresh tokens.
- **The company is bound at consent.** The consent screen, rendered by the
  server, asks which of the user's companies the client works in and whether
  it may write. `mcp_connections` records the choice per user and client.
  `BindMcpConnection` resolves the connection from the token on every request
  and overwrites the `company` header with the bound company before the host's
  `company` and `bouncer` middleware run, so a client cannot name another
  company and every downstream check sees the right one. Tools never take a
  company argument.
- **What a client may do is the intersection** of the connection's access
  level and the user's role in the company. `McpTool::shouldRegister` hides
  write tools from read-only connections and tools whose policy ability the
  user lacks; records are looked up inside the bound company.
- **Only the MCP scope is bound.** The consent, approval and auto-approval
  changes apply to requests for `mcp:use`; another consumer of the OAuth server
  keeps Passport's behaviour.
- **Connections end** when the user revokes them, is removed from the company,
  or is deleted (through ADR 0002's revoker), and are refused on use if the
  membership disappeared some other way.

## Consequences

- The host now depends on `laravel/mcp`. Its OAuth metadata assumes Passport's
  route names, which the boundary tests pin.
- Clients must reach the installation at its `APP_URL`. Remote connectors need
  public HTTPS; subdirectory installs cannot serve root `/.well-known` paths.
- Registration is open by design, so it is rate limited and unused
  registrations are pruned daily (`mcp:prune`).
- Screens for the administrator and for a user's connected apps are built
  separately, on top of the SPA redesign; until then the JSON APIs and
  `mcp:enable` cover them.
