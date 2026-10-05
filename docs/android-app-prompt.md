# Prompt — ASSUR+ Android app (insured / "Assuré" app)

> Paste everything below the line into a new Claude Code session (or give it to an Android developer).
> Attach the 20 mobile mockups ("maquettes assur+") alongside it — they are the visual reference.
> This is the Android counterpart of `docs/ios-app-prompt.md`: same product, same API, same scope.

---

## Role and goal

You are a senior Android engineer. Build **ASSUR+**, the native Android app for insured people (assurés) of a health-insurance platform operating in Senegal and the CIMA zone. The app is **one interface of a larger insurance platform** (back-office web, provider portal, CRM, finance, and an iOS app with the same features). It must contain **no business logic that belongs to the server**: premiums, coverage, eligibility, claim amounts and statuses are always computed by the API and only displayed by the app.

The platform evolves from an existing production system, I'KARANGE (PHP/MySQL), which already manages companies, members (adhérents), dependants, providers, coverage approvals (prises en charge), reimbursements, benefit scales (barèmes), invoices and an Apple Wallet member card.

## Technical stack (required)

- Kotlin 2.x, **Jetpack Compose** with Material 3, single-activity app with Navigation Compose (type-safe routes). Android Studio (latest stable), Gradle Kotlin DSL with a version catalog (`libs.versions.toml`).
- **minSdk 26** (Android 8.0) — a large share of users have older / entry-level phones. targetSdk = the level currently required by Google Play.
- Architecture: multi-module (`:app`, `:core:designsystem`, `:core:network`, `:core:data`, `:core:database`, `:core:auth`, `:feature:*`), MVVM with `ViewModel` + `StateFlow` UI state, unidirectional data flow, Kotlin Coroutines/Flow. Dependency injection with **Hilt**; every service behind an interface so it can be faked.
- Networking: Retrofit + OkHttp + kotlinx.serialization, client generated from or matching the **OpenAPI** contract (see "API contract"). JSON with ISO‑8601 dates, amounts as integers in **XOF (FCFA, no decimals)** — use `Long`, never `Double`.
- Auth: access + refresh tokens (short-lived access token, rotation on refresh via an OkHttp `Authenticator`), stored encrypted with a key held in the **Android Keystore** (DataStore + Tink; do not use the deprecated `EncryptedSharedPreferences`). Optional fingerprint / face unlock with **BiometricPrompt** (`androidx.biometric`). Automatic logout on refresh failure.
- Persistence: **Room** cache for read-only offline use (dashboard, card, contract, dependants, claims list, provider list), exposed as `Flow` (offline-first repositories).
- Uploads: **WorkManager** jobs with network constraints, retry/backoff and **resumable uploads**; images resized (max ~1600 px long edge) and compressed to JPEG ~70 % before upload. Show progress from `WorkInfo`.
- Push: **Firebase Cloud Messaging**, notification channels per category, deep links into the relevant screen. Request `POST_NOTIFICATIONS` (Android 13+) at a meaningful moment, not at first launch.
- Localization: **French first** (`values/strings.xml` in French, `values-en` later, room for Wolof), per-app language setting (AppCompat locales API). Currency formatted as `20 000 FCFA`. Phone numbers in Senegalese format (+221).
- Accessibility: font scaling, TalkBack content descriptions, minimum 48 dp touch targets, sufficient contrast, light + dark theme (no dynamic color — keep the brand palette).
- Must run well on **low-end devices (2 GB RAM, Android Go) and slow, unstable networks**: skeleton loading states, retry buttons, no blocking spinners, minimal data usage, image caching (Coil), R8 shrinking, Baseline Profiles, small APK/AAB size.
- Tests: unit tests for ViewModels and repositories (JUnit, Turbine, fakes), API client tests with **MockWebServer**, Compose UI tests for the 3 acceptance scenarios below. A **`mock` product flavor** that runs the whole app on local JSON fixtures, with no backend.

## Brand and design

- Follow the attached mockups for layout and flow. Implement a small design system first in `:core:designsystem` (colors, typography, spacing, shapes, buttons, cards, status badges, empty/error states) as a custom `MaterialTheme` + tokens, then build screens with it.
- The mockups are drawn for iPhone: adapt to Android conventions (system back gesture, edge-to-edge with insets, Material components, top app bars) without changing the brand or the flow.
- If something is not in the mockups, keep it consistent with the design system rather than inventing a new style.

## Users

- **Assuré principal** (policyholder) — full access to their contract and dependants.
- **Ayant droit** (dependant, e.g. spouse, adult child) — can log in with restricted rights defined by the server (`permissions` array returned at login). The UI must hide features the user is not allowed to use.

## Features (V1 scope)

### 1. Account creation and login
- Sign up: personal info, contact details, phone number **verified by SMS OTP** (use the **SMS User Consent API** to read the code with the user's approval — no `READ_SMS` permission), password or OTP-only login, acceptance of terms (CGU) with version recorded.
- Login by phone + password or phone + OTP. Forgot password via OTP.

### 2. Onboarding / subscription — target: under 3 minutes
Steps, with a visible progress indicator and the ability to go back without losing data (state survives rotation and process death via `SavedStateHandle`):
1. Identification
2. Personal information
3. Health questionnaire (questions come from the API; answers can make the user ineligible or add a surcharge — the server decides)
4. Guarantee selection: product/formula (e.g. *Essentiel*, *Sérénité* — names and content come from the API), coverage rate, territoriality, add dependants
5. Premium calculation — **dynamic simulation**: every change calls `POST /quotes` (debounced, previous request cancelled) and shows the recalculated premium, surcharges and eligibility messages
6. Validation (summary + accept special conditions)
7. Payment
8. Confirmation (policy number, start date, card available)

### 3. Payment
- Channels: **Wave, Orange Money, bank card**, extensible list from the API (`GET /payment-methods`).
- Flow: the app calls `POST /payments` → receives a checkout URL or deep link → opens it (**Custom Tabs**, or an intent to the Wave / Orange Money app when installed) → returns to the app through a verified **App Link** → polls `GET /payments/{id}` until a final status. Never trust the client-side result; the server confirms through the provider's webhook. Handle the user coming back without finishing (process may have been killed).
- Show transaction reference, amount, date, channel, status and linked contract in a payment history.
- Insurance is a real-world service, so external payment is allowed under Google Play rules (no Play Billing).

### 4. Insured dashboard
Name, policy number, formula, start/end dates, contract status, **remaining limit**, amount consumed, amount reimbursed, dependants. Contract section: view the special conditions (Conditions Particulières) in-app (`PdfRenderer`) and **download/share them as PDF** (`FileProvider` + share sheet).

### 5. Digital tiers-payant card
- Shows identity, member number, policy number, photo, coverage rate and a **QR code**.
- The QR code must **not** contain plain personal data: it encodes a short-lived **signed token** from `GET /me/card/qr-token` (refreshed automatically, cached for offline display until expiry). Generate the QR image locally (ZXing core).
- Increase screen brightness while the card is shown (window `screenBrightness`), restore it on exit. One card per beneficiary (pager / switcher).
- **"Add to Google Wallet"** button using the official button asset and the save link returned by the API. The current system only produces Apple Wallet `.pkpass` files, so add `GET /me/card/google-wallet` (returns a signed "Save to Google Wallet" JWT/URL) to the contract with `TODO(backend)`; hide the button if Google Wallet is unavailable on the device.

### 6. Family management
List spouse, children and other dependants with identity, relation, date of birth, status, guarantees, consumption and remaining limit. Request to **add** or **remove** a dependant (creates a request that the back-office validates; show its status). Contract management: show renewal date, tacit renewal, termination allowed up to 2 months before the due date (date rules come from the API).

### 7. Encrypted health vault
Categories: prescriptions, lab results, X-rays, vaccines. Upload from camera, photos (**Photo Picker**, no storage permission) or files (Storage Access Framework); view; delete. Files are encrypted at rest on the server; on the device, cached files live in app-internal storage, encrypted, and are **excluded from backup** (`dataExtractionRules` / `fullBackupContent`).

### 8. Claim declaration (sinistre)
Exactly these steps:
1. Select the beneficiary
2. Select the type of service (consultation, pharmacy, lab, hospitalisation… list from the API)
3. Photograph the receipt — use the **ML Kit Document Scanner** (auto edge detection, multi-page, no camera permission needed); fall back to CameraX capture on devices without Google Play services
4. Upload (resumable, with progress, continues in the background)
5. OCR reading — performed by the **server**; the app shows progress (target < 15 s)
6. Auto-prefill of the form with extracted fields: invoice number, date, provider, patient, act, medicine, quantity, unit price, amount, total. **Display each field's confidence score**; highlight low-confidence fields
7. Review and correction by the insured
8. Submit
9. Claim number assigned and displayed
10. Status tracking

Claim statuses (show as a timeline with dates and messages): **Brouillon, Soumis, En analyse, Pièces complémentaires demandées, Pré-validé, Validé, Rejeté, Payé, Clôturé**. When documents are requested, let the user add them directly from the claim screen. Drafts are saved locally (Room) and on the server, so the user can finish later or offline.

Show the server's calculation once validated: billed amount, covered amount, reimbursement rate, deductible, amount paid by the insurer, **amount left to pay (reste à charge)**.

### 9. Care network (parcours de soins)
**Map (Google Maps for Compose) and list** of contracted providers: doctors, specialists, pharmacies, clinics, hospitals, labs. Filter by type and distance. Location permission requested only when needed (approximate first, precise only if useful), using the Fused Location Provider. Call (`ACTION_DIAL`) / directions (intent to Maps) buttons. **Download the network as PDF.** The list must work without the map (Maps API key restricted to the app's signing certificate).

### 10. Notifications
In-app notification center plus push for: subscription, payment, activation, claim received / validated / rejected, missing document, reimbursement, contract expiry. Tapping opens the related screen (deep link through the navigation graph with a correct back stack).

### 11. Profile and settings
Personal info, photo, password change, biometric unlock toggle, notification preferences (link to system channel settings), language, terms, privacy policy, support contact, logout, request account deletion (required by Google Play — also provide the web URL for the Play Console Data deletion form).

## Out of scope for V1 (do not build unless asked)

Teleconsultation, appointment booking, wellness / gamification, conversational assistant, marketing campaigns. Leave room in the navigation to add them later.

## Rules you must not invent

The specification explicitly says that some business rules are not yet defined. **Do not hard-code them in the app.** Everything below comes from the API and must be displayed as received: products, guarantees, limits, rates, exclusions, deductibles, waiting periods, surcharge rules, eligibility rules, required documents per claim type, claim rejection reasons, renewal/termination dates, payment-default suspension. If an endpoint is missing, add it to the OpenAPI contract with a clear `TODO(backend)` and use fixtures.

## API contract

Work against the shared OpenAPI 3.1 spec at `docs/api/openapi.yaml` (the iOS app uses the same file; create it if it does not exist yet, then keep the app and spec in sync). Resources, from the specification: `/auth`, `/users`, `/insureds`, `/dependents`, `/products`, `/quotes`, `/policies`, `/claims`, `/providers`, `/eligibility`, `/payments`, `/commissions`, `/notifications`, `/reports`. The app needs at least:

- `POST /auth/register`, `POST /auth/otp/send`, `POST /auth/otp/verify`, `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`
- `GET /me`, `PATCH /me`, `GET /me/dashboard`, `GET /me/card`, `GET /me/card/qr-token`, `GET /me/card/google-wallet` (new, `TODO(backend)`)
- `GET /products`, `GET /health-questionnaire`, `POST /quotes`, `POST /policies` (from quote), `GET /policies/{id}`, `GET /policies/{id}/conditions.pdf`
- `GET /dependents`, `POST /dependents/requests`, `DELETE /dependents/{id}` (creates a removal request)
- `GET /payment-methods`, `POST /payments`, `GET /payments/{id}`, `GET /payments`
- `GET /claim-types`, `POST /claims` (draft), `POST /claims/{id}/documents` (upload), `GET /claims/{id}/ocr`, `PATCH /claims/{id}`, `POST /claims/{id}/submit`, `GET /claims`, `GET /claims/{id}`
- `GET /vault/documents`, `POST /vault/documents`, `DELETE /vault/documents/{id}`
- `GET /providers?type=&lat=&lng=&radius=`, `GET /providers/network.pdf`
- `GET /notifications`, `POST /devices` (FCM token, with `platform: "android"`)

Errors use one format: `{ "error": { "code": "...", "message": "...", "fields": { ... } } }` with French messages shown to the user.

## Security

- HTTPS only: Network Security Config with `cleartextTrafficPermitted="false"`; certificate pinning optional behind a build flag.
- No personal or medical data in logs (strip `Log` calls in release), analytics, crash reports, URLs or push payloads (push carries only an ID and a generic title).
- `FLAG_SECURE` on screens showing health data and the card, so content is hidden in Recents and screenshots. Clear caches, Room tables and WorkManager jobs on logout.
- `android:allowBackup` limited by rules so tokens and health files are never backed up. Release builds obfuscated with R8.
- Health data is highly sensitive: only fetch what the screen needs.

## Acceptance scenarios (must pass as Compose UI tests on the `mock` flavor)

1. **Subscription:** create an account → choose a formula → get a quote → pay → receive the contract.
2. **Tiers-payant card:** open the card → QR code displayed and refreshed → add to Google Wallet (the save intent is launched).
3. **Claim:** photograph an invoice → submit → get a claim number → follow its status.

## How to work

1. Start by listing the screens from the mockups and mapping each one to a feature above. Point out anything in the mockups that contradicts this document, anything missing, and anything that needs adapting from iOS to Android conventions.
2. Create the Gradle project and modules, design system, API client, `mock` flavor with fixtures, and navigation shell (bottom navigation bar: Accueil, Carte, Sinistres, Réseau, Profil — adjust to the mockups).
3. Build features in this order: auth → dashboard + card → claims → subscription + payment → family → vault → network → notifications → profile.
4. After each feature: build, run on an emulator (include a low-end profile, e.g. API 26 with 2 GB RAM), take screenshots, run the tests, and commit.
5. Document setup and architecture in `android/README.md` (including Firebase, Maps API key and signing configuration, with secrets kept out of the repo).
