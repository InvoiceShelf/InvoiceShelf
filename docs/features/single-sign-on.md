# Sign in with SSO

InvoiceShelf can sign users in through an outside identity provider with single sign-on (OpenID Connect). Any OpenID Connect provider with a userinfo endpoint should work, such as Keycloak, Authentik, Authelia or Zitadel. The [provider notes](#provider-notes) below cover the first three. Plain OAuth 2.0 providers that are not OpenID Connect providers (no `sub` claim, no userinfo endpoint) are not supported.

One identity provider per installation is supported. The settings belong to the protocol: `OIDC_*` for OpenID Connect, the only protocol today.

<!-- VERIFY: list the providers this has been run against end to end once the provider runs are done. -->

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
3. **Continue** goes on to the identity provider, which is asked to have the person sign in again (`prompt=login`), even if they are signed in there already. <!-- VERIFY: that Keycloak, Authentik and Authelia each ask for the password again on prompt=login. -->
4. When the sign-in is done, the server sends the browser to the app link `com.invoiceshelf.app:/sso` with a one-time code.
5. The app trades the code and its secret for an access token named after the device, as a password sign-in does.

The confirmation is there because anyone can start a hand-off and send its address to somebody else. Without it, a person who is signed in at the identity provider and opens such a link would sign the sender's device in to their account without noticing. Only continue a sign-in you just started in the app yourself.

`INVOICESHELF_CLIENT_APP_LINK` changes where the browser is sent back to. Only two cases need it:

- **An app built with another URL scheme.** Register that scheme in `mobile/android` and `mobile/ios`, and set it here.
- **The client bundle in a browser tab (`pnpm dev:client`).** Point it at `http://localhost:4173/sso-return.html`. The sign-in then opens in a popup, which hands the result back to the tab.

## Provider notes

### Keycloak

- **Issuer:** `https://keycloak.example.com/realms/<realm>`, without a trailing slash. Keycloak versions before 17 put `/auth` in front: `https://keycloak.example.com/auth/realms/<realm>`.
- **Client:** in the realm, open **Clients → Create client**. Choose *OpenID Connect*, enter the client ID, turn **Client authentication** on and keep **Standard flow**. Copy the secret from the client's **Credentials** tab. <!-- VERIFY: Keycloak admin console labels (Clients → Create client, Client authentication, Standard flow, Credentials tab) in the current release. -->
- **Redirect URI:** the client's **Valid redirect URIs**, on its **Settings** tab. <!-- VERIFY: field name and tab. -->
- **`email_verified`:** Keycloak sends the user's **Email verified** flag. Turn it on for each user under **Users → (user) → Details**, or have Keycloak check addresses itself with **Realm settings → Login → Verify email**. Users an administrator creates start unverified unless that flag is set. <!-- VERIFY: where the Email verified toggle and the Verify email realm setting live in the current admin console. -->
- `OIDC_CLIENT_AUTH=auto` works: Keycloak's token endpoint takes both methods.

### Authentik

- **Issuer:** `https://authentik.example.com/application/o/<application slug>/`, with the trailing slash. The provider's overview page shows it as **OpenID Configuration Issuer**. <!-- VERIFY: label on the provider overview page. -->
- **Client:** open **Applications → Applications** and choose **Create with provider**. Pick *OAuth2/OpenID Provider* and set **Client type** to *Confidential*. Bind the group that may use InvoiceShelf. To have the tile in a user's library sign them in, set the application's **Launch URL** to `https://invoices.example.com/auth/sso/redirect`. <!-- VERIFY: wizard labels in the current release. -->
- **Redirect URI:** the provider's **Redirect URIs**, one entry with matching mode *Strict*. <!-- VERIFY: field name and matching mode label. -->
- **`email_verified`:** Authentik 2025.10 and later sends `false` by default, so the first sign-in is refused until you choose one of these: <!-- VERIFY: the version that changed the default, and that it is false rather than absent. -->
  1. **Mark addresses as verified at Authentik.** Create a scope mapping under **Customization → Property Mappings → Create → Scope Mapping**, with scope name `email` and an expression such as the one below, and select it in the provider's **Advanced protocol settings → Scopes** in place of the default email mapping. Only do this if Authentik's addresses can be trusted (see the next option). <!-- VERIFY: menu path, the Scopes field location, and that replacing the default email mapping is how it is applied. -->

     ```python
     return {
         "email": request.user.email,
         "email_verified": True,
     }
     ```

  2. **Set `OIDC_REQUIRE_VERIFIED_EMAIL=false`.** Only when users can't change their own address (leave **System → Settings → Allow users to change email** off, the default) and nobody can enroll without an address check. If people can create their own accounts, limit InvoiceShelf to a group those accounts don't join by themselves. <!-- VERIFY: the settings path and its default. -->
- `OIDC_CLIENT_AUTH=auto` works.

### Authelia

- **Issuer:** Authelia's own address, such as `https://auth.example.com`. Its discovery document is at `https://auth.example.com/.well-known/openid-configuration`.
- **Client:** add it under `identity_providers.oidc.clients` in Authelia's configuration, with `client_secret` stored as a hash, `public: false`, `authorization_policy` set as you need, `scopes: ['openid', 'profile', 'email']` and `grant_types: ['authorization_code']`.
- **Redirect URI:** the client's `redirect_uris` list.
- **Client authentication:** Authelia holds each client to its `token_endpoint_auth_method`, and its discovery document lists both methods, so `auto` picks `post`. Either set `token_endpoint_auth_method: 'client_secret_post'` on the client, or keep Authelia's default and set `OIDC_CLIENT_AUTH=basic`. <!-- VERIFY: that client_secret_basic is Authelia's default for confidential clients, and that a mismatched method is refused with invalid_client. -->
- **Userinfo:** leave `userinfo_signed_response_alg` at `none`. InvoiceShelf needs a plain JSON userinfo answer. <!-- VERIFY: option name. -->
- **`email_verified`:** Authelia has no address check of its own and sends the claim as `true`. Its addresses come from your user file or LDAP directory, which only administrators edit. <!-- VERIFY: that Authelia sends email_verified true for file and LDAP users. -->

```yaml
identity_providers:
  oidc:
    clients:
      - client_id: 'invoiceshelf'
        client_name: 'InvoiceShelf'
        client_secret: '$pbkdf2-sha512$...'
        public: false
        authorization_policy: 'two_factor'
        redirect_uris:
          - 'https://invoices.example.com/auth/oidc/callback'
        scopes: ['openid', 'profile', 'email']
        grant_types: ['authorization_code']
        response_types: ['code']
        token_endpoint_auth_method: 'client_secret_post'
```

<!-- VERIFY: the Authelia client example against the current configuration reference. -->

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
| "did not work" | an exception with `invalid_client` or HTTP 401 from the token endpoint | Wrong client secret, or the provider expects the other client authentication method: set `OIDC_CLIENT_AUTH`. |
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
