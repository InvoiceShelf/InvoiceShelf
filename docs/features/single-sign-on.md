# Sign in with SSO

InvoiceShelf can sign users in through an identity provider over OAuth 2.0 and OpenID Connect. Any provider with a userinfo endpoint should work, such as Authentik, Keycloak, Authelia or Zitadel. **So far it has only been tested against Authentik.**

When it is set up, a **Sign in with SSO** button appears next to **Login**, both on the web and in the mobile app. The email and password form keeps working, and the customer portal doesn't change.

## How accounts are matched

Signing in with SSO never creates a user. Add everyone who should use InvoiceShelf as a user first, with the same email address they have at the identity provider.

1. On a person's first sign-in with SSO, InvoiceShelf finds the user with that email address and links the two accounts. Upper and lower case don't matter.
2. From then on, only the link counts. InvoiceShelf stores the account's `sub` claim in `users.oauth2_id`, so:
   - changing the email address in either system later doesn't break the link;
   - a different account that later gets the same email address can't take the user over.
3. To remove a link, for example after the account was deleted and created again at the identity provider, run:

   ```bash
   php artisan oauth2:unlink person@example.com
   ```

   Their next sign-in links them again by email address.

When InvoiceShelf refuses a sign-in, `storage/logs/laravel.log` records the reason.

The first sign-in trusts the email address the identity provider sends. InvoiceShelf doesn't require the `email_verified` claim, because Authentik 2025.10 and later sends `false` for it by default. Make sure nobody can give themselves an address that isn't theirs at the identity provider:

- Don't let users change their own email address. In Authentik, leave **System → Settings → Allow users to change email** off, which is the default.
- If people can create their own accounts there, limit InvoiceShelf to a group those accounts don't join by themselves, or have the enrollment verify the email address.

## 1. Set up the identity provider

Create an OAuth2 / OpenID Connect client (in some providers, an "application") with these settings:

- **Client type:** confidential. It sends its secret in the token request body (`client_secret_post`).
- **Redirect URI:** `https://invoices.example.com/auth/oauth2/callback`, with your InvoiceShelf address. Use exact matching if the provider offers it.
- **Grant type:** authorization code. InvoiceShelf also uses PKCE.
- **Scopes:** `openid`, `profile` and `email`.
- **Access:** only the people who may use InvoiceShelf.

Copy the client ID and secret. The provider's `/.well-known/openid-configuration` lists the three endpoint URLs InvoiceShelf needs: `authorization_endpoint`, `token_endpoint` and `userinfo_endpoint`.

### Authentik

1. Open **Applications → Applications** and choose **Create with provider**.
2. **Application:** name it *InvoiceShelf*. Set the **Launch URL** to `https://invoices.example.com/auth/oauth2/redirect`, so that the tile in a user's library signs them in.
3. **Provider type:** *OAuth2/OpenID Provider*.
4. **Provider:**
   - **Authorization flow:** the implicit-consent flow, or the explicit one if users should confirm each sign-in.
   - **Client type:** *Confidential*.
   - **Redirect URIs:** one entry, matching mode *Strict*.
   - Leave the scopes and the subject mode at their defaults.
5. **Bindings:** bind the group that may use InvoiceShelf.

The endpoints are the same for every Authentik application:

```dotenv
OAUTH2_AUTHORIZE_URL=https://authentik.example.com/application/o/authorize/
OAUTH2_TOKEN_URL=https://authentik.example.com/application/o/token/
OAUTH2_USERINFO_URL=https://authentik.example.com/application/o/userinfo/
```

## 2. Configure InvoiceShelf

Add these lines to `.env`, or to the container's environment:

```dotenv
OAUTH2_CLIENT_ID=the client ID
OAUTH2_CLIENT_SECRET=the client secret
OAUTH2_AUTHORIZE_URL=…
OAUTH2_TOKEN_URL=…
OAUTH2_USERINFO_URL=…
```

The button appears once all five settings are set. If you cache the configuration (`php artisan config:cache` or `php artisan optimize`), run that command again.

The InvoiceShelf server has to reach the token and userinfo URLs itself. A URL that only works in users' browsers isn't enough.

Optional settings:

| Setting | Default | What it does |
| --- | --- | --- |
| `OAUTH2_SCOPES` | `openid profile email` | The scopes to ask for, separated by spaces or commas. |
| `OAUTH2_REDIRECT_URI` | `/auth/oauth2/callback` on the host the sign-in started on | A fixed callback URL. It must match the provider's redirect URI exactly. |

Behind a reverse proxy, InvoiceShelf builds the callback URL from `X-Forwarded-Proto` and `X-Forwarded-Host` (see `TRUSTED_PROXIES`). If the provider reports a redirect URI error and the URL it shows starts with `http://` or names an internal host, fix the proxy headers or set `OAUTH2_REDIRECT_URI`.

## The mobile app

The app shows **Sign in with SSO** when the server's client manifest says it is set up (`oauth2_login`). The app link needs no setup on the server.

1. The app starts a hand-off on the API and keeps its secret.
2. It opens the sign-in in the in-app browser.
3. When the sign-in is done, the server sends the browser to the app link `com.invoiceshelf.app:/sso` with a one-time code.
4. The app trades the code and its secret for an access token named after the device, as a password sign-in does.

A sign-in address passed to someone else gets the sender nothing. The code goes to the device that opened the address, and the secret never leaves the device that started the hand-off.

`INVOICESHELF_CLIENT_APP_LINK` changes where the browser is sent back to. Only two cases need it:

- **An app built with another URL scheme.** Register that scheme in `mobile/android` and `mobile/ios`, and set it here.
- **The client bundle in a browser tab (`pnpm dev:client`).** Point it at `http://localhost:4173/sso-return.html`. The sign-in then opens in a popup, which hands the result back to the tab.

## Troubleshooting

| What you see | Likely cause |
| --- | --- |
| No **Sign in with SSO** button | One of the five settings is missing, or the configuration is cached. |
| The provider shows a redirect URI error | The callback URL doesn't match the provider's redirect URI exactly. See the reverse proxy note above. |
| "Signing in with SSO did not work" | The provider refused the code, or InvoiceShelf couldn't reach it. Check `storage/logs/laravel.log` for a wrong client secret or a connection error. The message also appears when a sign-in took too long or was opened in another browser. |
| "Your SSO account does not match a user here" | No user has that email address, or the user is linked to another account. The log says which. Fix the email address, or run `php artisan oauth2:unlink`. |
| **Sign in with SSO** signs in without asking for a password | You are still signed in at the identity provider in that browser. Signing out of InvoiceShelf doesn't sign you out there. |
