# Assur Plus — Android app (insured)

Native Jetpack Compose port of the iOS app ([`ios/`](../ios/README.md)): same screens, same design tokens, same
strings (French source, English from the iOS String Catalog), same API contract and the same demo backend. It is one
client of the ASSUR+ platform: premiums, eligibility, coverage, claim amounts and statuses are computed by the API and
only displayed here.

- Spec: [`docs/android-app-prompt.md`](../docs/android-app-prompt.md), [`docs/ios-app-prompt.md`](../docs/ios-app-prompt.md)
- API contract: [`docs/api/openapi.yaml`](../docs/api/openapi.yaml) (keep in sync with `core/network/AssurApi.kt`)
- Porting conventions (SwiftUI → Compose mapping): [`PORTING.md`](PORTING.md)

## Requirements

JDK 17+ (tested with Homebrew OpenJDK 21), Android SDK with platform 37 and build-tools 37.0.0, AGP 9.4 / Gradle 9.7
(wrapper included). `minSdk` 26 (Android 8.0), `targetSdk` 36. Create `android/local.properties` with
`sdk.dir=/path/to/Android/sdk` (or open the folder in Android Studio).

## Run

| Flavor | App id | Backend |
|---|---|---|
| `mock` | `sn.assurplus.app.mock` ("Assur Plus Mock") | in-process `MockServer` on the shared JSON fixtures — no network |
| `live` | `sn.assurplus.app` | Tenant `apiBaseURL` — `https://ikarange.mcedge.sn/api/mobile/v1` (debug only: `APIBaseURL` extra) |

```bash
cd android && ./gradlew :app:installMockDebug
```

```bash
cd android && ./gradlew :app:testMockDebugUnitTest
```

```bash
cd android && ./gradlew :app:connectedMockDebugAndroidTest
```

Demo accounts (mock flavor, fake data) are the same as iOS: **77 000 00 01** / `assur1234` (Awa Diop, principal) and
**77 000 00 02** / `assur1234` (Moussa Diop, dependant with restricted rights). OTP code: `123456`. On the emulator,
the claim and vault pickers offer *Utiliser une facture d'exemple*.

Launch extras (debug / mock — the counterpart of the iOS launch arguments), e.g.
`adb shell am start -n sn.assurplus.app.mock/sn.assurplus.app.app.MainActivity --ez ResetState true --es MockSignIn principal`:

| Extra | Effect |
|---|---|
| `ResetState` (bool) | In-memory tokens, cache and settings (fresh launch) |
| `MockSignIn principal\|dependent` | Skip the login screen |
| `MockLatency 0.05` | Simulated network latency in seconds (default 0.35) |
| `MockQRLifetime 12` | QR token lifetime in seconds (default 60) |
| `AppLanguage fr\|en` | Force the UI language |
| `APIBaseURL <url>` | Live debug builds only: local backend, e.g. `http://10.0.2.2:8080/api/mobile/v1` |

## White-label tenant

`app/src/main/assets/tenant.json` is the iOS `Tenant.plist` converted to JSON (same keys): name, wordmark, brand
colours, API URL, login identifier, backend feature flags, support contacts, legal links, languages. Regenerate with
`plutil -convert json -r -o android/app/src/main/assets/tenant.json ios/AssurPlus/Tenant/Tenant.plist`.

## Languages

Strings are written in French in code, exactly as in Swift, and translated at runtime with `t("…")`. The English table
`assets/l10n/en.json` is exported from `ios/AssurPlus/Resources/Localizable.xcstrings` by `tools/export_strings.py`;
Android-only strings go in `tools/strings-overrides.json`. Same rules as iOS: follow the device language when supported,
otherwise French; a choice in Profil › Langue persists, applies immediately and is sent as `Accept-Language`.

## Architecture

```
app/src/main/java/sn/assurplus/app/
  app/            Application, MainActivity, AppEnvironment (DI), AuthSession, Router (tabs, stacks, deep links),
                  RootView (launch / auth / lock / tabs), floating tab bar, services (payments, wallet, documents)
  core/
    network/      Endpoint, APIClient (bearer, single-flight refresh with rotation, error mapping), AssurApi, APIError
    model/        @Serializable DTOs mirroring the OpenAPI schemas (same names as the Swift models)
    persist/      file-based response cache in noBackupFilesDir, RemoteResource (stale-while-revalidate), ProtectedStorage
    security/     Keystore-encrypted token store, BiometricPrompt
    upload/       resumable chunked uploads, image compression (JPEG, ≤1600 px, quality 70)
    format/       XOF amounts (`20 000 FCFA`), +221 phone numbers, dates
    l10n/         runtime translation tables, language settings
  designsystem/   iOS tokens (colours, spacing, radius, Dynamic Type sizes), buttons, cards, badges, grouped lists,
                  large-title screens with glass bar buttons, sheets, action sheets, state views, document picker,
                  SF Symbol → Material icon map
  features/       auth, home, card, claims, subscription, payment, family, policy, vault, network, notifications, profile
  mock/           MockServer (port of the iOS fake backend), generated PDFs / sample invoice
assets/           tenant.json, l10n/*.json, mock/*.json (same fixtures as iOS)
```

- Navigation mirrors iOS: a floating capsule tab bar over per-tab stacks (Home, Claims and Profile push routes;
  re-tapping a tab pops to its root), full flows (subscription, new claim) as page sheets, deep links
  `assurplus://claims/<id>`, `assurplus://card`, …
- Offline: dashboard, card (and the last QR token until it expires), contract, dependants, claims, providers,
  payments and notifications are cached and shown first, with a retry banner when a refresh fails.
- QR code: drawn locally (ZXing matrix, same dot / round-eye / centre-logo style as iOS) from the short-lived signed
  token `GET /me/card/qr-token`, renewed 10 s before expiry; screen brightness is raised while the card is shown.
- Document scanning: ML Kit Document Scanner (edge detection, multi-page, no camera permission); photos through the
  system Photo Picker; files through the Storage Access Framework. OCR runs on the server.
- Map: osmdroid with OpenStreetMap tiles (no API key). For production volumes switch to Google Maps (needs a key) or
  a commercial tile provider — OSM's public tile servers are not meant for heavy app traffic.

## Security

- HTTPS only (`network_security_config.xml`; cleartext allowed only to the emulator host for local debugging).
- Tokens encrypted with an AES-GCM key held in the Android Keystore. A rejected refresh logs the user out.
- Optional biometric lock at launch and after 60 s in the background.
- Content hidden in Recents (`setRecentsScreenshotEnabled(false)`, `FLAG_SECURE` on Android < 13 in live builds).
- No HTTP cache. Cached responses and documents live in `noBackupFilesDir`; backups and device transfer exclude all
  app data. Logout clears tokens, cache and files.

## Differences from iOS (platform)

| iOS | Android |
|---|---|
| Apple Wallet (`.pkpass`) | Google Wallet: `GET /me/card/google-wallet` returns a signed save link — **`TODO(backend)`** |
| APNs | FCM not wired yet: needs a Firebase project and `google-services.json`. Notification taps are already routed (`target_kind` / `target_id` extras) |
| VisionKit document camera | ML Kit Document Scanner (requires Google Play services; photo / file import otherwise) |
| MapKit | osmdroid (OpenStreetMap) |
| Face ID / Touch ID | BiometricPrompt (fingerprint / face, device credential fallback) |
| SF Symbols, SF Pro | Material Symbols (closest match per symbol), Roboto |
