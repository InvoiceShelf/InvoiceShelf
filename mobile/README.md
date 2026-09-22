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

`android/app/build.gradle` still carries the Capacitor template's
`versionCode 1` / `versionName "1.0"`. That is not the shipped version: the
release workflow sets both from the tag when it builds, so that there is one
place to bump and not three. Do not edit them by hand.
