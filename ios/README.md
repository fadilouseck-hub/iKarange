# Assur Plus — iOS app (insured)

Native SwiftUI app for insured people and their dependants, built as a white-label client of the iKarangue
platform (MCE Group). Assur Plus is the first tenant. It is one client of the ASSUR+ platform:
premiums, eligibility, coverage, claim amounts and statuses are computed by the API and only displayed here.

- Spec: [`docs/ios-app-prompt.md`](../docs/ios-app-prompt.md), *Cahier des charges ASSUR+*
- API contract: [`docs/api/openapi.yaml`](../docs/api/openapi.yaml) (keep in sync with `Core/Networking/AssurAPI.swift`)
- Screen map and gap analysis: [`docs/ios/screen-map.md`](../docs/ios/screen-map.md)

## Requirements

Xcode 16+ (tested with Xcode 26.6), iOS 17+ deployment target, Swift 6 language mode. No third-party dependencies.

## Run

```bash
open ios/AssurPlus.xcodeproj
```

| Scheme | Configuration | Backend |
|---|---|---|
| `AssurPlus-Mock` | `MockAPI` (`MOCK_API` flag, bundle id `sn.assurplus.app.mock`) | in-process `MockServer` on JSON fixtures — no network |
| `AssurPlus` | `Debug` / `Release` | Tenant `apiBaseURL` — `https://ikarange.mcedge.sn/api/mobile/v1` (Debug only: `-APIBaseURL <url>` override) |

Command line:

```bash
xcodebuild test -project ios/AssurPlus.xcodeproj -scheme AssurPlus-Mock -destination 'platform=iOS Simulator,name=iPhone 16'
```

### MockAPI demo accounts (fake data)

| Account | Phone | Password | Notes |
|---|---|---|---|
| Principal | 77 000 00 01 | `assur1234` | Awa Diop, contract *Sérénité*, 3 dependants, claims in several statuses |
| Dependant | 77 000 00 02 | `assur1234` | Moussa Diop, restricted permissions (own card and claims only) |

The OTP code is always `123456`. Any other Senegalese number can register and go through the subscription flow.
On the Simulator, the claim and vault pickers offer *Utiliser une facture d'exemple* (VisionKit's camera is unavailable).

Launch arguments (any configuration with `-UseMockAPI`, or the MockAPI configuration):

| Argument | Effect |
|---|---|
| `-UseMockAPI` | Use `MockServer` without the MockAPI build configuration (UI tests) |
| `-ResetState` | In-memory tokens, cache and settings (fresh launch) |
| `-MockSignIn principal\|dependent` | Skip the login screen |
| `-MockLatency 0.05` | Simulated network latency in seconds (default 0.35) |
| `-MockQRLifetime 12` | QR token lifetime in seconds (default 60) |

## Tenant (white-label) configuration

Everything customer-specific is in [`AssurPlus/Tenant/Tenant.plist`](AssurPlus/Tenant/Tenant.plist), read by `Tenant`:
display name, wordmark, copyright holder, brand colours, API URL, login identifier (`username` or `phone`),
backend feature flags (self-registration, OTP login, password reset), support contacts, terms / privacy URLs,
default and supported languages. Shared features never hard-code the customer. MockAPI builds enable every feature
and phone login for demos. The App Store display name is set per configuration (`Assur Plus` / `Assur Plus Mock`).

## Languages

French is the source and fallback language, English is fully translated (`Resources/Localizable.xcstrings`,
`Resources/InfoPlist.xcstrings` for permission prompts). At first launch the app follows the device language when
it is supported, otherwise French; a choice in Profil › Langue persists and applies immediately (also sent to the
server as `preferredLanguage`, and as `Accept-Language` on every request). Use `String(localized: …, bundle: .appLanguage)`
for strings built in code; SwiftUI `Text` literals are handled automatically. Re-extract with
`xcodebuild -exportLocalizations -project ios/AssurPlus.xcodeproj -localizationPath /tmp/loc -exportLanguage en`.

## Backend

The production backend is the I'KARANGE PHP site. Its mobile API lives in
[`legacy/ikarange/src/api/mobile/index.php`](../legacy/ikarange/src/api/mobile/index.php) (hooked in `src/router.php`)
with new tables in [`database/008_mobile_api.sql`](../legacy/ikarange/database/008_mobile_api.sql); existing tables
are not altered. It needs a `MOBILE_SECRET` (≥ 32 random characters) in the server `.env` for vault encryption.
Members sign in with their portal username; SMS OTP, self-registration, online subscription/payments and OCR are not
available on this platform yet (the app hides or degrades those features through the tenant flags). Provider
positions are approximate (city level) until real coordinates exist.

Local run: `dev/serve.sh` (PHP built-in server on the local MySQL copy), then run the `AssurPlus` scheme with
`-APIBaseURL http://localhost:8080/api/mobile/v1`. `LiveBackendTests` drives the real app against it when
`TEST_RUNNER_LIVE_API_URL`, `TEST_RUNNER_LIVE_USER` and `TEST_RUNNER_LIVE_PASSWORD` are set (synthetic test member only).

## Architecture

```
AssurPlus/
  App/            entry point, AppEnvironment (DI), AuthSession, Router (tabs, deep links, push), root/tab views
  Core/
    Networking/   Endpoint, APIClient (bearer, single-flight refresh with rotation, error mapping), AssurAPI, JSON coding
    Models/       Codable DTOs mirroring the OpenAPI schemas
    Persistence/  SwiftData response cache, RemoteResource (stale-while-revalidate), ProtectedStorage
    Security/     Keychain token store, biometrics, optional certificate pinning
    Upload/       resumable chunked uploads (background URLSession), image compression
    Formatting/   XOF amounts (`20 000 FCFA`), +221 phone numbers, dates
  DesignSystem/   tokens (colours, spacing, radius, type), buttons, cards, badges, skeleton/empty/error states, document picker
  Features/       Auth, Home, Policy, Card, Claims, Subscription, Payment, Family, Vault, Network, Notifications, Profile
  Mock/           MockServer (fake backend), fixture loading, generated PDFs / sample invoice
  Resources/      assets, String Catalog, MockFixtures/*.json
```

- **Dependency injection**: `AppEnvironment` is placed in the SwiftUI environment. Services sit behind protocols
  (`HTTPTransport`, `TokenStore`, `Uploading`, `PaymentLaunching`, `WalletAdding`, `BiometricAuthenticating`,
  `ResponseCache`). `MockServer` replaces the transport, so mock runs exercise the real client code
  (auth headers, refresh, decoding, errors).
- **View models**: `@Observable`, `@MainActor`, `async/await`. Created once per screen with `WithModel`.
- **Permissions**: `Me.permissions` from the server drives what is shown (`AuthSession.can(_:)`); the server enforces them.
- **Offline**: dashboard, card (and the last QR token until it expires), contract, dependants, claims, providers,
  payments and notifications are cached in SwiftData and shown first; a banner offers a retry when a refresh fails.
  Claim declarations are saved as local drafts at each step.
- **Uploads**: `POST /uploads` → `PATCH` chunks with `Upload-Offset` → on a failure, `GET` the committed offset and
  resume (exponential backoff). Chunks go through a background `URLSession`, so a chunk in flight keeps going while
  the app is suspended. Images are converted to JPEG, at most 1600 px on the long edge, quality 0.7.
- **Payments**: `POST /payments` → Wave / Orange Money app link or hosted page (`ASWebAuthenticationSession`) →
  the app polls `GET /payments/{id}` until a final status. The client-side outcome is never trusted.
- **Navigation**: floating capsule menu (`FloatingTabBar`) over a `TabView` with the system tab bar hidden.
- **Appearance**: light, dark or automatic (Profil › Apparence); colours are dynamic tokens.
- **QR code**: generated locally with CoreImage from the short-lived signed token (`GET /me/card/qr-token`); renewed
  10 s before expiry; contains no personal data in clear.

## Security

- HTTPS only (ATS default). Opt-in public-key pinning: add base64 SPKI SHA-256 hashes (with a backup key) to
  `PINNED_PUBLIC_KEY_HASHES` in `Config/Info.plist`.
- Tokens are stored in the Keychain (`AfterFirstUnlockThisDeviceOnly`). A rejected refresh logs the user out.
- Optional Face ID / Touch ID lock at launch and after 60 s in the background.
- Content is hidden in the app switcher. No HTTP cache (`urlCache = nil`).
- Downloaded documents are stored with `NSFileProtectionComplete` and excluded from backups. Logout clears the
  tokens, the SwiftData cache and those files.
- The app logs no personal or medical data. Push payloads must contain only `{ "target": { "kind", "id" } }` and a
  generic title.

## Tests

- Unit tests (`AssurPlusTests`, Swift Testing): API client (refresh / rotation / concurrency / offline), formatting,
  auth, dashboard/card/QR, claims (OCR, drafts, resumable upload with failures), subscription (debounce, eligibility,
  full flow), family, vault, network, routing.
- UI tests (`AssurPlusUITests`, XCUITest on MockAPI): `LanguageTests` (English device, in-app switch, French
  fallback), the 3 acceptance scenarios —
  `SubscriptionAcceptanceTests`, `CardAcceptanceTests`, `ClaimAcceptanceTests` — plus `ScreensTourTests`, which
  walks every secondary screen, checks the dependant restrictions and attaches screenshots to the result bundle.

## Known gaps / to do

- **Mockups**: the 20 "maquettes assur+" were not available. Layout follows the spec and the I'KARANGE colours
  (mint `#2DD4A8`, teal `#1A3A3A`). Align the screens when the mockups arrive (see `docs/ios/screen-map.md`).
- **Backend**: endpoints marked `TODO(backend)` in the OpenAPI spec are needed (uploads, password reset, legal
  documents, notification read state and preferences, deletion request, termination request, vault file download).
- **Apple Wallet**: the server must sign `.pkpass` files (`legacy/ikarange/storage/wallet/certs` is empty). MockAPI
  simulates the "added" result.
- **Push**: set the team and APNs key in the signing settings; `aps-environment` is `development`.
- **Support contacts**: set `SUPPORT_PHONE` / `SUPPORT_EMAIL` in Info.plist (the entries are hidden until then).
- **Localization**: strings are French source strings in `Localizable.xcstrings`. Open the project in Xcode once to
  extract them, then add English and Wolof.
- **Pinning** applies to the main session. The background upload session uses system trust only.
- Business values in the fixtures (products, limits, rates, rules) are placeholders until the scoping meeting (CDC §40).
