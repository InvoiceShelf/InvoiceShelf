# Sign in with SSO

InvoiceShelf can sign users in through an outside identity provider with single sign-on (OpenID Connect). Any OpenID Connect provider with a userinfo endpoint should work, such as Keycloak or Authentik. The [provider notes](#provider-notes) below cover the eight providers that were tested. Plain OAuth 2.0 providers that are not OpenID Connect providers (no `sub` claim, no userinfo endpoint) are not supported.

One identity provider per installation is supported. The settings belong to the protocol: `OIDC_*` for OpenID Connect, the only protocol today.

It was run end to end against Keycloak 26.8.2, Authentik 2026.8.6, Authelia 4.39.28, Zitadel 4.20.5, Dex 2.46.1, Logto 1.46.0, Casdoor 4.30.0 and Pocket ID 2.19.3. Other providers and versions were not tested.

When it is set up, a **Sign in with SSO** button appears next to **Login**, both on the web and in the mobile app. The email and password form keeps working, and the customer portal doesn't change.

## 1. Register InvoiceShelf with the identity provider

Create an OpenID Connect client (some providers call it an application) with these settings:

- **Client type:** confidential, with a client secret.
- **Redirect URI:** `https://invoices.example.com/auth/oidc/callback`, with your InvoiceShelf address. Use exact matching if the provider offers it.
- **Grant type:** authorization code. InvoiceShelf also uses PKCE (S256).
- **Scopes:** `openid`, `profile` and `email`.
- **Access:** only the people who may use InvoiceShelf.

Copy the client ID and the secret, and note the provider's **issuer** URL. The issuer is the address whose `/.well-known/openid-configuration` document describes the provider. The [provider notes](#provider-notes) say where to find it.

## 2. Configure InvoiceShelf

### With the issuer

This is the usual way. Add these lines to `.env`, or to the container's environment:

```dotenv
OIDC_CLIENT_ID=the client ID
OIDC_CLIENT_SECRET=the client secret
OIDC_ISSUER=https://sso.example.com/realms/example
```

InvoiceShelf reads the authorization, token and userinfo endpoints from `https://sso.example.com/realms/example/.well-known/openid-configuration` when someone first signs in, and keeps the document for an hour. A trailing slash on the issuer doesn't matter: write it the way your provider does.

The `issuer` inside that document must be the issuer you configured (a trailing slash aside), as OpenID Connect Discovery requires. Otherwise the sign-in fails and the log says so.

### With the endpoint URLs

If your provider has no discovery document, give the three endpoints instead of the issuer:

```dotenv
OIDC_CLIENT_ID=the client ID
OIDC_CLIENT_SECRET=the client secret
OIDC_AUTHORIZE_URL=https://sso.example.com/authorize
OIDC_TOKEN_URL=https://sso.example.com/token
OIDC_USERINFO_URL=https://sso.example.com/userinfo
```

You can also combine the two. With `OIDC_ISSUER` set, any endpoint URL you set wins over the discovered one, for example a token URL on an internal address the server reaches more directly. If you set an issuer here as well, InvoiceShelf also checks ID tokens against it.

### Settings

The button appears once the client ID, the secret and either the issuer or all three endpoint URLs are set. If you cache the configuration (`php artisan config:cache` or `php artisan optimize`), run that command again after any change.

| Setting | Default | What it does |
| --- | --- | --- |
| `OIDC_CLIENT_ID` | none | The client ID from the identity provider. Required. |
| `OIDC_CLIENT_SECRET` | none | The client secret. Required. |
| `OIDC_ISSUER` | none | The issuer URL. The endpoints are discovered from it. |
| `OIDC_AUTHORIZE_URL` | discovered | The authorization endpoint. |
| `OIDC_TOKEN_URL` | discovered | The token endpoint. |
| `OIDC_USERINFO_URL` | discovered | The userinfo endpoint. |
| `OIDC_CLIENT_AUTH` | `auto` | How InvoiceShelf proves itself at the token endpoint. `post` sends the secret in the request body (`client_secret_post`). `basic` sends it in an `Authorization: Basic` header (`client_secret_basic`) and leaves it out of the body. `auto` uses `post`, unless the issuer's discovery document lists the methods its token endpoint accepts and that list has `client_secret_basic` but not `client_secret_post`. |
| `OIDC_REQUIRE_VERIFIED_EMAIL` | `true` | Whether the first sign-in needs the provider to say it verified the email address. See [the verified email rule](#the-verified-email-rule). |
| `OIDC_SCOPES` | `openid profile email` | The scopes to ask for, separated by spaces or commas. Keep `openid`. |
| `OIDC_REDIRECT_URI` | `/auth/oidc/callback` on the host the sign-in started on | A fixed callback URL. It must match the provider's redirect URI exactly. |

The InvoiceShelf server has to reach the issuer, the token endpoint and the userinfo endpoint itself. A URL that only works in users' browsers isn't enough. It waits up to 5 seconds to connect and 10 seconds for an answer (5 in all for the discovery document); a provider that takes longer fails the sign-in, and the log says so.

Behind a reverse proxy, InvoiceShelf builds the callback URL from `X-Forwarded-Proto` and `X-Forwarded-Host` (see `TRUSTED_PROXIES`). If the provider reports a redirect URI error and the URL it shows starts with `http://` or names an internal host, fix the proxy headers or set `OIDC_REDIRECT_URI`.

### ID tokens

When the token endpoint returns an ID token, InvoiceShelf refuses the sign-in unless the token is for its client ID (`aud`), has not expired (`exp`, with a minute allowed for clock differences), comes from the configured issuer (`iss`, when one is configured) and describes the same account as the userinfo endpoint (`sub`). It doesn't check the token's signature: the token comes straight from the token endpoint over TLS, which OpenID Connect allows in place of the signature. Encrypted ID tokens aren't supported. The ID token is optional: a provider that sends none still works, as long as its userinfo endpoint names the account.

## How accounts are linked

Signing in with SSO never creates a user. Add everyone who should use InvoiceShelf as a user first, with the same email address they have at the identity provider.

1. On a person's first sign-in with SSO, InvoiceShelf finds the user with that email address and links the two accounts. Upper and lower case don't matter.
2. From then on, only the link counts. Links are rows in the `sso_identities` table: the user, the protocol (`type`, `oidc` today), the provider's issuer (`issuer`), the account's `sub` claim (`subject`) and the time of its latest sign-in (`last_used_at`). So:
   - changing the email address in either system later doesn't break the link;
   - a different account that later gets the same email address can't take the user over;
   - an account with the same `sub` at a different identity provider is a different account. It can't sign in as the user, and because the user is already linked, its email address doesn't link it either.
3. An account is linked to one user, and a user to one account per protocol. Deleting a user deletes their links.
4. The issuer stored is the ID token's `iss` when the provider sends an ID token, otherwise `OIDC_ISSUER`, otherwise the scheme and host of the token URL, always without a trailing slash. Keep the `openid` scope so it stays the same from one sign-in to the next.

### The verified email rule

The first sign-in links by email address, so it is only as safe as the email addresses at the identity provider. If someone could put another person's address on their own account there, they could sign in to InvoiceShelf as that person. So by default the first sign-in needs the provider's `email_verified` claim (from the userinfo endpoint, or the ID token if the userinfo answer has none) to be `true`; the string `"true"` counts too. Otherwise the person sees "Your identity provider has not confirmed your email address" and nothing is linked. Users who are already linked aren't affected.

Set `OIDC_REQUIRE_VERIFIED_EMAIL=false` to drop the rule only when no one but an administrator can set or change an email address at the provider: users can't edit their own address, and nobody can register an account themselves without an address check. Then every address there is one an administrator chose, which is as good as verified. The [provider notes](#provider-notes) say how to get `email_verified: true` sent instead, which is the better fix.

### Removing a link

To remove a person's links, for example after their account was deleted and created again at the identity provider, run:

```bash
php artisan sso:unlink person@example.com
```

`--type=oidc` removes only their OpenID Connect link. Their next sign-in links them again by email address.

**Moving to another identity provider,** or changing the issuer (another realm or application slug, another host name): existing links name the old issuer, so every linked person is refused as "linked to another account" until you run `sso:unlink` for them. Their next sign-in then links them to the new provider's account by verified email address.

## The mobile app

The app shows **Sign in with SSO** when the server's client manifest says it is set up (`sso_login`). The app link needs no setup on the server.

1. The app starts a hand-off on the API and keeps its secret.
2. It opens the sign-in in the in-app browser. The browser first shows "Sign in to the InvoiceShelf app on *device*?" with **Continue** and **Cancel**.
3. **Continue** goes on to the identity provider, which is asked to have the person sign in again (`prompt=login`), even if they are signed in there already.
4. When the sign-in is done, the server sends the browser to the app link `com.invoiceshelf.app:/sso` with a one-time code.
5. The app trades the code and its secret for an access token named after the device, as a password sign-in does.

The confirmation is there because anyone can start a hand-off and send its address to somebody else. Without it, a person who is signed in at the identity provider and opens such a link would sign the sender's device in to their account without noticing. Only continue a sign-in you just started in the app yourself.

`INVOICESHELF_CLIENT_APP_LINK` changes where the browser is sent back to. Only two cases need it:

- **An app built with another URL scheme.** Register that scheme in `mobile/android` and `mobile/ios`, and set it here.
- **The client bundle in a browser tab (`pnpm dev:client`).** Point it at `http://localhost:4173/sso-return.html`. The sign-in then opens in a popup, which hands the result back to the tab.

## Provider notes

These notes come from the test runs, one per provider and version. In each, the aim was the three lines `OIDC_ISSUER`, `OIDC_CLIENT_ID` and `OIDC_CLIENT_SECRET`, unless a note says otherwise. Where the test ran over plain `http`, use `https` for real installations.

What providers send as `email_verified`, which decides what the first sign-in needs:

| What the provider does | Providers |
| --- | --- |
| Reports the real status per account | Keycloak, Dex, Casdoor, Pocket ID |
| Stops unverified accounts itself | Zitadel |
| Reports every account as verified | Authelia, Logto |
| Reports every account as unverified with its stock mapping | Authentik |

### Keycloak (26.8.2)

- **Issuer:** `https://keycloak.example.com/realms/<realm>`, without a trailing slash.
- **Client:** confidential (client authentication on, client authenticator `client-secret`), standard flow only, with the redirect URI above. The realm's default client scopes supplied `openid profile email`. The client authenticator takes both secret methods, so `OIDC_CLIENT_AUTH=auto` works.
- **`email_verified`:** sent per account, from the user's email verified flag (`emailVerified` in a realm import). Turn it on for each person who should sign in.
- Keycloak's discovery document does not list `email_verified` among its claims, but it sends it. The ID token's `aud` is a string and `sub` is the user's UUID.

### Authentik (2026.8.6, the version tested)

- **Issuer:** `https://authentik.example.com/application/o/<application slug>/`, with the trailing slash.
- **Client:** an OAuth2/OpenID provider (client type confidential) with strict redirect URIs, and an application that uses it. The provider must list its grant types (`authorization_code`, `refresh_token`): a provider made from a blueprint without them answers every authorization request with `error=invalid_request`. `OIDC_CLIENT_AUTH=auto` works.
- **`email_verified`:** the stock mapping "authentik default OAuth Mapping: OpenID 'email'" sends `email_verified: false` for every account, so every first sign-in is refused with "has not confirmed your email address", and so is an account with no InvoiceShelf user, because the verified check runs first. Either give the provider a scope mapping (scope name `email`) that sends `true` for people you trust, for example one that reads a user attribute, or set `OIDC_REQUIRE_VERIFIED_EMAIL=false` where only administrators can change addresses. The test used this mapping, in place of the stock one in the provider's property mappings:

  ```python
  return {
      "email": request.user.email,
      "email_verified": bool(request.user.attributes.get("email_verified", False)),
  }
  ```

- The stored issuer has no trailing slash, although authentik's `iss` has one.

### Authelia (4.39.28)

- **Issuer:** Authelia's own address, such as `https://auth.example.com`. Authelia requires HTTPS for itself. A private certificate authority must be trusted by the PHP of the InvoiceShelf server, which makes the token and userinfo requests.
- **Client:** under `identity_providers.oidc.clients`, with `client_secret` stored as a digest, `public: false`, `scopes: ['openid', 'profile', 'email']`, `grant_types: ['authorization_code']` and `response_types: ['code']`.
- **Client authentication:** Authelia accepts only the method the client registers, and its default for a confidential client is `client_secret_basic`. The test registered `token_endpoint_auth_method: 'client_secret_post'`. Authelia's discovery document lists both methods, so `auto` picks `post` and no `OIDC_CLIENT_AUTH` setting was needed. With Authelia's default, set `OIDC_CLIENT_AUTH=basic` (not tested).
- **Userinfo:** the test client set `userinfo_signed_response_alg: 'none'`, which gives plain JSON.
- **`email_verified`:** sent as `true` for every account, including one set up as unverified, so the rule cannot tell accounts apart. The test used the file user backend. Authelia puts only `sub` and protocol claims in the ID token, and the email in userinfo.
- Authelia shows a consent screen on every sign-in. On a mobile hand-off with an existing Authelia session it shows consent first and then asks for the password again on the same page, because the hand-off asks for a fresh sign-in (`prompt=login`). Don't replace that with `max_age=0`: Authelia 4.39 then never finishes the sign-in.

### Zitadel (4.20.5)

- **Issuer:** Zitadel's own address, such as `https://id.example.com`.
- **Client:** a project with an OIDC application of type web, authentication method POST (`client_secret_post`), authorization code. Zitadel generates the client ID and the secret, so copy them from the application.
- **`email_verified`:** Zitadel itself stops an account with an unverified email at an "E-Mail Verification" screen asking for a code, so such an account never reaches InvoiceShelf. People must verify their address at Zitadel first.
- The ID token's `aud` has two values, the client and the project, and carries no email claims (they are in userinfo). `sub` is a numeric user ID. The access token is opaque. On a first sign-in Zitadel offered a "2-Factor Setup" step.

### Dex (2.46.1)

- **Issuer:** Dex's issuer, such as `https://id.example.com/dex`.
- **Client:** an entry under `staticClients` with `id`, `secret` and `redirectURIs`. Dex accepts both secret methods.
- **`email_verified`:** sent per account. In the test setup it came from the `emailVerified` field of each entry under `staticPasswords`. Dex signs these accounts in by email address.
- Dex shows its "Grant Access" consent page on every sign-in (`skipApprovalScreen: false`) and keeps no browser session. `sub` is an encoded value (the user ID and the connector name), stable when `userID` is fixed.

### Logto (1.46.0)

- **Issuer:** `https://id.example.com/oidc`.
- **Client:** a Traditional application (confidential) with the redirect URI above. The test created it by inserting rows into Logto's database, because Logto's open source edition has no bootstrap token; that depends on Logto's internal schema. `OIDC_CLIENT_AUTH=auto` works.
- **`email_verified`:** sent as `true` for every account with a primary email, including one set up as unverified, so the rule cannot tell accounts apart and that account was linked.
- The ID token is signed with ES384, which InvoiceShelf accepted. The first-party application shows no consent screen.

### Casdoor (4.30.0)

- **Issuer:** the value of Casdoor's `origin` setting, such as `https://id.example.com`, without a path.
- **Client:** an application with a client ID, a secret, grant type authorization code, JWT token format and the redirect URI above. A Casdoor application object needs its full set of login page fields, so the test used a copy of Casdoor's built-in application. `OIDC_CLIENT_AUTH=auto` works.
- **`email_verified`:** sent per account. It is in the ID token, and the userinfo answer leaves it out when false. InvoiceShelf reads the ID token's value when userinfo has none, so an unverified account is refused.
- The ID token carries the whole Casdoor user object and `aud` is a list. No consent screen appears.

### Pocket ID (2.19.3)

- **Issuer:** Pocket ID's address, a domain name: Pocket ID refuses an IP address as its address. The test used `http://localhost:8188`.
- **Client:** a confidential client (not public) with the callback URL above. Pocket ID generates a client secret when the client is created. Both secret methods are accepted.
- **`email_verified`:** sent per account, from the user's `emailVerified` property.
- Pocket ID asks for consent on a client's first use. It offers passkeys and login codes, not passwords. The test signed in with a one-time access code, not a passkey. The mobile hand-off asks the provider for a fresh sign-in, which Pocket ID answers with a passkey prompt that the automated test could not complete, so the hand-off with a real passkey is untested.

## Troubleshooting

When InvoiceShelf refuses a sign-in, `storage/logs/laravel.log` records why. The person sees one of three messages: "Signing in with SSO did not work", "Your SSO account does not match a user here" or "Your identity provider has not confirmed your email address".

| What you see | Log message | What to do |
| --- | --- | --- |
| No **Sign in with SSO** button | none | A required setting is missing, or the configuration is cached. |
| The provider shows a redirect URI error | none | The callback URL doesn't match the provider's redirect URI exactly. See the reverse proxy note above. |
| "did not work" | the identity provider's discovery document could not be read | Check `OIDC_ISSUER`, and that the InvoiceShelf server can reach it (DNS, firewall, TLS certificate). |
| "did not work" | the discovery document is not a JSON object with an "issuer" | `OIDC_ISSUER` points at something other than the issuer, such as the provider's home page. |
| "did not work" | the issuer in the identity provider's discovery document does not match OIDC_ISSUER | Copy the issuer exactly as the provider shows it: realm or application slug, `http` or `https`, host name. |
| "did not work" | the discovery document has no userinfo_endpoint (or another endpoint) | Set the matching `OIDC_*_URL`. |
| "did not work" | the identity provider's token endpoint (or userinfo endpoint) did not answer in time, or could not be reached | The server can't reach that URL, or the provider is too slow. Check DNS, the firewall and the TLS certificate from the InvoiceShelf server. |
| "did not work" | the identity provider answered with an error | The log has the provider's `error` and `error_description`, such as `access_denied` or `invalid_scope`. The person may also have cancelled. |
| "did not work" | an exception reported from the token request (the sign-in code does not catch it, so the message is the HTTP client's error for the provider's answer, such as the status it returned) | Check the client secret, and try the other value of `OIDC_CLIENT_AUTH`. |
| "did not work" | the userinfo endpoint did not answer with a JSON object | Turn off signed userinfo answers at the provider, and keep `openid` in `OIDC_SCOPES`. |
| "did not work" | the identity provider sent no "sub" claim | The userinfo answer has no `sub`. Keep `openid` in `OIDC_SCOPES`. |
| "did not work" | the ID token could not be decoded | The provider sends encrypted ID tokens, which aren't supported. Turn ID token encryption off. |
| "did not work" | the ID token is for another client | `OIDC_CLIENT_ID` differs from the client the provider issued the token to. |
| "did not work" | the ID token has expired, or has no "exp" | The clocks of the two servers differ by more than a minute. Check NTP. |
| "did not work" | the ID token comes from another issuer than OIDC_ISSUER | The token endpoint belongs to another issuer, for example another realm. |
| "did not work" | the ID token and the userinfo endpoint describe different accounts | The token and userinfo endpoints belong to different providers or realms. |
| "did not work" | none | A sign-in took too long, or was finished in another browser than it started in. Try again. |
| "has not confirmed your email address" | the identity provider has not verified this email address | See [the verified email rule](#the-verified-email-rule) and the provider notes. |
| "does not match a user here" | no single user has this email address | Add a user with that address, or fix the address on either side. |
| "does not match a user here" | the user with this email address is linked to another account | The user is linked to a different account, or to the same `sub` at a different identity provider. Run `php artisan sso:unlink` if the new account is the right one. |
| **Sign in with SSO** signs in without asking for a password | none | You are still signed in at the identity provider in that browser. Signing out of InvoiceShelf doesn't sign you out there. |

After changing the provider's endpoints while keeping the same issuer, InvoiceShelf may use the old discovery document for up to an hour. `php artisan cache:clear` makes it read the new one at once.
