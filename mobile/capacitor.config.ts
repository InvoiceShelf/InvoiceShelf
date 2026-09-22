import type { CapacitorConfig } from '@capacitor/cli'

/**
 * The shell around the thin-client bundle.
 *
 * `server.hostname` is a contract, not a preference. Sanctum's default
 * stateful list holds `localhost` and `127.0.0.1`, and `bootstrap/app.php`
 * enables `statefulApi()`, so a request from `https://localhost` (Capacitor's
 * own Android default) picks up the session and CSRF middleware and every
 * bearer POST comes back 419. A hostname that is not local keeps the client
 * bearer-only, and the server's CORS defaults are built from these two
 * origins: `capacitor://app.invoiceshelf.internal` on iOS and
 * `https://app.invoiceshelf.internal` on Android.
 */
const config: CapacitorConfig = {
  appId: 'com.invoiceshelf.app',
  appName: 'InvoiceShelf',
  webDir: 'www',
  server: {
    hostname: 'app.invoiceshelf.internal',
    androidScheme: 'https',
  },
}

export default config
