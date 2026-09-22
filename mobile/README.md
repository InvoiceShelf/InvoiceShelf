# InvoiceShelf mobile shell

The iOS and Android shell around the thin client. There is no app source here:
the whole UI is the same SPA the web serves, built by `vite.client.config.ts`
into `mobile/www`, and this directory is the native package that carries it.

A phone running this app talks to **a server the user runs**, chosen on the
connect screen. It holds no database, no business logic and no bundled
backend, and it is not a second product.

## Layout

| Path | What it is |
|---|---|
| `capacitor.config.ts` | app id, app name, and the hostname rule below |
| `package.json` | the Capacitor CLI, the platforms and the native halves of the plugins |
| `assets/` | the source artwork icons and splash screens are generated from |
| `android/`, `ios/` | the native projects, committed |
| `www/` | the built SPA. Generated, gitignored, never edited here |

The **JS halves** of the plugins are declared a second time in the repository
root `package.json`, at the same versions, because that is the manifest the
Vite build resolves `import { Camera } from '@capacitor/camera'` against.
Bump one and bump the other: a JS half newer than the native half calls
methods the native code does not have, and the failure arrives at runtime on
a device.

## Building

From the repository root:

```bash
pnpm build:client                  # builds the SPA into mobile/www
pnpm -C mobile install             # first time, or after a plugin change
pnpm -C mobile exec cap sync       # copies www into both projects, wires plugins
```

`cap sync` is not optional after a client build: the native projects hold
their own copy of `www`, so a rebuilt bundle that has not been synced is not
the one the app runs.

Then, Android:

```bash
cd mobile/android && ./gradlew assembleDebug     # app/build/outputs/apk/debug/app-debug.apk
```

or open `mobile/android` in Android Studio. Gradle needs `ANDROID_HOME` set,
or a `local.properties` with `sdk.dir=/path/to/sdk`; that file is per-machine
and gitignored.

And iOS, **on a Mac**:

```bash
sudo gem install cocoapods     # once
pnpm -C mobile exec cap sync ios
open mobile/ios/App/App.xcworkspace
```

`cap sync ios` runs on Linux and will copy the web assets, but it skips
`pod install` and the Xcode clean step with a warning, so the project it
leaves behind cannot be built. The Xcode workspace, not the `.xcodeproj`, is
what opens: the Pods project is the other half of it.

## The hostname is a contract

```ts
server: { hostname: 'app.invoiceshelf.internal', androidScheme: 'https' }
```

This is not a preference and it must never be changed to `localhost`.

Sanctum's default stateful list holds `localhost` and `127.0.0.1`, and the
server enables `statefulApi()`. A request arriving with `Origin:
https://localhost`, which is Capacitor's own Android default, is therefore
treated as first-party: it picks up the session and CSRF middleware, and
**every bearer POST comes back 419**. A hostname that is not local keeps the
client bearer-only.

It gives the app exactly two origins, and the server's CORS defaults are
built from them:

| Platform | Origin |
|---|---|
| iOS | `capacitor://app.invoiceshelf.internal` |
| Android | `https://app.invoiceshelf.internal` |

An operator whose server rejects the app is looking at
`CORS_ALLOWED_ORIGINS`, not at this file.

## Transport

The server belongs to the user, so the app has to reach servers that a
consumer app never would: a LAN name over plain http, or a certificate signed
by a private CA. Both platforms block those by default, and both defaults are
relaxed here rather than worked around:

- **Android** `app/src/main/res/xml/network_security_config.xml` permits
  cleartext and adds `user` to the trust anchors, so a CA the user installed
  on the device is honoured. Referenced from `AndroidManifest.xml`.
- **iOS** `App/App/Info.plist` sets `NSAppTransportSecurity`
  → `NSAllowsArbitraryLoads`. There is no domain exception list to write
  instead: the address is typed in at runtime. A private CA still needs its
  profile installed on the device, which is documented rather than bypassed.

Nothing here skips certificate validation, and the connect screen warns
before a plain-http server is used, so the choice stays the user's and stays
visible.

The app package itself is served from `https://app.invoiceshelf.internal` by
Capacitor's local scheme handler and never leaves the device.

## Permissions

There is **no `CAMERA` permission** in the manifest, deliberately.
`@capacitor/camera` captures through `ACTION_IMAGE_CAPTURE`, which hands the
job to the camera app and needs no permission of ours; declaring it would
invert that, because Android requires an app that declares `CAMERA` to also
hold it at runtime before the capture intent returns anything. The storage
permissions the plugin documents are for `saveToGallery`, which the receipt
capture does not use.

iOS still needs the usage strings, and `Info.plist` carries
`NSCameraUsageDescription` and `NSPhotoLibraryUsageDescription`.

## Icons and splash screens

`assets/` holds five SVGs, all re-cuts of `resources/static/img/logo-mark.svg`
with its rounded corners removed, because iOS and Android each apply their own
mask:

| File | Used for |
|---|---|
| `icon-only.svg` | the iOS app icon and the pre-adaptive Android launcher icon, plate included |
| `icon-foreground.svg`, `icon-background.svg` | the two layers of the Android adaptive icon |
| `splash.svg`, `splash-dark.svg` | the launch screens |

Regenerate every density into both native projects with:

```bash
pnpm -C mobile run assets
```

The brand plate is `#4a3dff`. The foreground leaves a wide margin on purpose:
a launcher may mask the adaptive icon to a circle and only the inner two
thirds survives that, so a mark sized to fill the layer loses its corners.
The generated PNGs are committed along with the sources.

## Versioning

**The app version is the server tag it is built from.** One tag is one tested
API and UI combination, which is the whole point of not splitting the
repository, and the client is never offered through the in-app updater.

`android/app/build.gradle` falls back to the Capacitor template's
`versionCode 1` / `versionName "1.0"`, so a local `assembleDebug` still needs no
arguments. That is not the shipped version: the release workflow passes
`-PversionCode` and `-PversionName`, both derived from the tag by
`scripts/version-code.mjs`, so there is one place to bump and not three. Do not
edit them by hand, and see **Releasing** below for how the numbers are built.

## Releasing

The apps are built by `.github/workflows/mobile.yaml` ("Mobile Apps"), which runs
on `release: published`, the same event `publish.yaml` uses, so pressing Publish
on a drafted release builds the apps, registers the release and builds the Docker
images in one action. `workflow_dispatch` with a `tag` input rebuilds for a
release that already exists, which is the path for a failed store upload or an
expired certificate.

Nothing runs until it is switched on. Every job is gated on a repository variable,
so a repository without the signing secrets has no permanently red workflow that
everyone learns to ignore:

| Variable | Gates | Set to `true` when |
|---|---|---|
| `MOBILE_RELEASES_ENABLED` | the whole workflow | the Android keystore secrets exist |
| `MOBILE_IOS_ENABLED` | the iOS job | the Apple certificate, profile and App Store Connect key exist |
| `MOBILE_PLAY_UPLOAD_ENABLED` | the Play upload step alone | the app entry exists in the Play Console |

They are **variables**, not secrets: Settings → Secrets and variables → Actions →
Variables.

### 0. Start the accounts first

Both stores gate on paperwork that takes weeks, and neither can be hurried at the
end:

- **Apple**, organisation enrolment needs a **D-U-N-S number** for the legal
  entity. Requesting one takes up to 5 business days on its own, enrolment review
  a further 1 to 4 weeks, and the whole thing has to finish before a distribution
  certificate can be issued. An individual account is faster but publishes under a
  personal name.
- **Google Play**, a **new personal developer account must run closed testing**
  with at least 12 testers for 14 continuous days before it may promote anything
  to production. Internal testing, which is what this workflow uploads to, is
  available immediately; production is not.

Open both accounts before the first release the apps should appear in, not with
it.

### 1. Back it up with sops before GitHub sees it

**Every secret below goes into the devenv `secrets/` directory first, then into
GitHub.** GitHub cannot return a stored Actions secret: once set, the only copy
that can be read back is the one kept elsewhere, and an Android upload key that
exists nowhere else is an app that cannot be updated.

The procedure is the one in the devenv repository's `AGENTS.md`, under "Secrets
Backup (sops)": from the devenv workspace root, with `SOPS_AGE_KEY_FILE` pointing
at the age key, encrypt a plain JSON file holding the credential to the age
recipient in `.sops.yaml`, write it to `secrets/<name>.sops.json`, `shred` the
plaintext, and add a row to the table in that file naming what it is and where
the live copy lives. The age private key is what actually needs backing up; the
encrypted files are useless without it.

### 2. Android signing key

One key, forever: every update to a published app has to be signed with the same
one. Generate it on a machine you trust, not in CI:

```bash
keytool -genkeypair -v \
  -keystore invoiceshelf-upload.keystore \
  -alias invoiceshelf-upload \
  -keyalg RSA -keysize 4096 -validity 10000 \
  -storepass '<one password>' -keypass '<the same password>' \
  -dname "CN=InvoiceShelf, O=InvoiceShelf, C=MK"
```

- **`-validity 10000`** (about 27 years) because Play refuses an upload key that
  expires before 2033, and a key that expires is an app that can no longer be
  updated.
- **The store password and the key password must be identical.** `keytool` now
  writes PKCS12 by default, which has no separate key password; give it two and
  the build fails at packaging time with `Given final block not properly padded`.
  The Gradle config still takes both, because the DSL requires both and a legacy
  JKS keystore can genuinely differ.
- Under Play App Signing this is the **upload** key, not the app signing key
  Google holds, so losing it can be recovered from by asking Google to reset it.
  Back it up anyway: the reset is a support request with a wait attached.

Then back it up (step 1) and set:

| Secret | What it is | How |
|---|---|---|
| `ANDROID_KEYSTORE_B64` | the keystore file itself | `base64 -w0 invoiceshelf-upload.keystore` (`base64 -i` on macOS) |
| `ANDROID_KEYSTORE_PASSWORD` | the `-storepass` above | as typed |
| `ANDROID_KEY_ALIAS` | `invoiceshelf-upload` | as typed |
| `ANDROID_KEY_PASSWORD` | the `-keypass` above, the same value | as typed |

Set `MOBILE_RELEASES_ENABLED` to `true`. The next published release builds a
signed AAB and APK, keeps both as workflow artifacts, and attaches the APK to the
GitHub release as `InvoiceShelf-<tag>-android.apk`.

### 3. Google Play upload (optional, later)

1. Create the app in the Play Console and **upload the first AAB by hand**. The
   API cannot create an app entry, so the first upload of a package is always
   manual.
2. In Google Cloud, create a service account, enable the **Google Play Android
   Developer API** for its project, and download a **JSON key**.
3. In the Play Console, under Users and permissions, invite that service account
   and give it **Release to testing tracks** on this app.

| Secret | What it is |
|---|---|
| `PLAY_SERVICE_ACCOUNT_JSON` | the whole JSON key file, pasted as-is (not base64) |

Set `MOBILE_PLAY_UPLOAD_ENABLED` to `true` and each release goes to the
**internal** track. Promotion to closed, open or production stays a human
decision in the console.

### 4. Apple

All of this needs the enrolment from step 0 to have completed.

1. **Distribution certificate.** Keychain Access → Certificate Assistant →
   Request a Certificate From a Certificate Authority, save the CSR to disk.
   Upload it at developer.apple.com → Certificates → **Apple Distribution**,
   download the `.cer`, open it to import into the login keychain, then select the
   certificate **with its private key** and export both as a `.p12` with a
   password.
2. **App id and profile.** Register the app id `com.invoiceshelf.app`, then create
   an **App Store** provisioning profile for it against that certificate. Name it
   exactly **`InvoiceShelf App Store`**: `mobile/ios/ExportOptions.plist` and the
   archive step match the profile by name, so a different name fails the export.
   Download the `.mobileprovision`.
3. **App Store Connect API key.** App Store Connect → Users and Access →
   Integrations → App Store Connect API → generate a key with the **App Manager**
   role. The `.p8` downloads exactly once. The issuer id is printed above the key
   list on the same page.
4. Create the app record in App Store Connect, or the TestFlight upload has
   nowhere to land.

| Secret | What it is | How |
|---|---|---|
| `IOS_CERTIFICATE_P12_B64` | the exported distribution certificate and its private key | `base64 -i dist.p12` |
| `IOS_CERTIFICATE_PASSWORD` | the password given at export | as typed |
| `IOS_PROVISIONING_PROFILE_B64` | the App Store profile | `base64 -i InvoiceShelf_App_Store.mobileprovision` |
| `APPLE_TEAM_ID` | the 10-character team id, from the Membership page | as printed |
| `APP_STORE_CONNECT_KEY_ID` | the key id shown next to the generated key | as printed |
| `APP_STORE_CONNECT_ISSUER_ID` | the issuer UUID on the same page | as printed |
| `APP_STORE_CONNECT_PRIVATE_KEY` | the contents of the `.p8`, `-----BEGIN PRIVATE KEY-----` and all | paste the file, not base64 |

Set `MOBILE_IOS_ENABLED` to `true`. Releases then archive on a macOS runner and
upload to **TestFlight**; submitting a build for review stays a human step in App
Store Connect.

The iOS job has never run. It is written from the documented behaviour of
`xcodebuild` and of the actions it uses, and the first real run on a Mac-side
runner should be expected to need signing fixes.

### What the version numbers are

`mobile/scripts/version-code.mjs` turns the tag into both fields, and the workflow
passes them to Gradle as `-PversionCode` and `-PversionName` and to the iOS build
through `Info.plist`:

```bash
node mobile/scripts/version-code.mjs 3.0.0-beta.1
versionCode=3000031
versionName=3.0.0-beta.1
```

`versionName` is the tag without a leading `v`. `versionCode` is
`major * 1000000 + minor * 10000 + patch * 100 + stage + n`, with the last two
digits reserved as a patch's pre-release runway: alpha `0..29`, beta `30..59`, rc
`60..89`, final `99`. So `3.0.0-alpha.4` < `3.0.0-beta.1` < `3.0.0-rc.1` <
`3.0.0` < `3.0.1` as integers, which is the only ordering Play understands. A
30th alpha, a fourth pre-release word, a minor above 99 or a tag it cannot parse
all fail the run rather than being rounded into something plausible: a
versionCode is spent the moment Play sees it.
