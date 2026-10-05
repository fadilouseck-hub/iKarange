# Prompt — ASSUR+ iOS app (insured / "Assuré" app)

> Paste everything below the line into a new Claude Code session (or give it to an iOS developer).
> Attach the 20 mobile mockups ("maquettes assur+") alongside it — they are the visual reference.

---

## Role and goal

You are a senior iOS engineer. Build **ASSUR+**, the native iOS app for insured people (assurés) of a health-insurance platform operating in Senegal and the CIMA zone. The app is **one interface of a larger insurance platform** (back-office web, provider portal, CRM, finance). It must contain **no business logic that belongs to the server**: premiums, coverage, eligibility, claim amounts and statuses are always computed by the API and only displayed by the app.

The platform evolves from an existing production system, I'KARANGE (PHP/MySQL), which already manages companies, members (adhérents), dependants, providers, coverage approvals (prises en charge), reimbursements, benefit scales (barèmes), invoices and an Apple Wallet member card.

## Technical stack (required)

- Swift 6, SwiftUI, iOS 17+ minimum, Xcode 16+. No third-party UI frameworks.
- Architecture: feature modules (Swift Package per feature or folder-per-feature), `@Observable` view models, `async/await`, structured concurrency. Dependency injection via environment / protocols so every service can be mocked.
- Networking: a typed `APIClient` generated from or matching an **OpenAPI** contract (see "API contract"). JSON with ISO‑8601 dates, amounts as integers in **XOF (FCFA, no decimals)**.
- Auth: access + refresh tokens (short-lived access token, rotation on refresh), stored in the **Keychain**. Optional Face ID / Touch ID unlock (LocalAuthentication). Automatic logout on refresh failure.
- Persistence: **SwiftData** cache for read-only offline use (dashboard, card, contract, dependants, claims list, provider list).
- Uploads: background `URLSession` with **resumable / retryable uploads**, images compressed (HEIC→JPEG, max ~1600 px long edge, ~70 % quality) before upload.
- Push: APNs (UserNotifications), deep links into the relevant screen.
- Localization: **French first** (fr), strings in a String Catalog so English and Wolof can be added later. Currency formatted as `20 000 FCFA`. Phone numbers in Senegalese format (+221).
- Accessibility: Dynamic Type, VoiceOver labels, sufficient contrast, light + dark mode.
- Must run well on **older / low-end iPhones and slow, unstable networks**: skeleton loading states, retry buttons, no blocking spinners, minimal data usage.
- Tests: unit tests for view models and the API client (with mocked responses), UI tests for the 3 acceptance scenarios below. A `MockAPI` build configuration that runs the whole app on local JSON fixtures, with no backend.

## Brand and design

- Follow the attached mockups for layout and flow. Implement a small design system first (colors, typography, spacing, radius, buttons, cards, status badges, empty/error states) as design tokens, then build screens with it.
- If something is not in the mockups, keep it consistent with the design system rather than inventing a new style.

## Users

- **Assuré principal** (policyholder) — full access to their contract and dependants.
- **Ayant droit** (dependant, e.g. spouse, adult child) — can log in with restricted rights defined by the server (`permissions` array returned at login). The UI must hide features the user is not allowed to use.

## Features (V1 scope)

### 1. Account creation and login
- Sign up: personal info, contact details, phone number **verified by SMS OTP**, password or OTP-only login, acceptance of terms (CGU) with version recorded.
- Login by phone + password or phone + OTP. Forgot password via OTP.

### 2. Onboarding / subscription — target: under 3 minutes
Steps, with a visible progress indicator and the ability to go back without losing data:
1. Identification
2. Personal information
3. Health questionnaire (questions come from the API; answers can make the user ineligible or add a surcharge — the server decides)
4. Guarantee selection: product/formula (e.g. *Essentiel*, *Sérénité* — names and content come from the API), coverage rate, territoriality, add dependants
5. Premium calculation — **dynamic simulation**: every change calls `POST /quotes` (debounced) and shows the recalculated premium, surcharges and eligibility messages
6. Validation (summary + accept special conditions)
7. Payment
8. Confirmation (policy number, start date, card available)

### 3. Payment
- Channels: **Wave, Orange Money, bank card**, extensible list from the API (`GET /payment-methods`).
- Flow: the app calls `POST /payments` → receives a checkout URL or deep link → opens it (`ASWebAuthenticationSession` / universal link to the Wave or Orange Money app) → returns to the app → polls `GET /payments/{id}` until a final status. Never trust the client-side result; the server confirms through the provider's webhook.
- Show transaction reference, amount, date, channel, status and linked contract in a payment history.
- Insurance is a real-world service, so external payment is allowed under App Store rules (no In-App Purchase).

### 4. Insured dashboard
Name, policy number, formula, start/end dates, contract status, **remaining limit**, amount consumed, amount reimbursed, dependants. Contract section: view the special conditions (Conditions Particulières) and **download/share them as PDF** (QuickLook + ShareLink).

### 5. Digital tiers-payant card
- Shows identity, member number, policy number, photo, coverage rate and a **QR code**.
- The QR code must **not** contain plain personal data: it encodes a short-lived **signed token** from `GET /me/card/qr-token` (refreshed automatically, cached for offline display until expiry). Generate the QR image locally with CoreImage.
- Increase screen brightness while the card is shown. One card per beneficiary (switcher).
- "Add to Apple Wallet" button using the `.pkpass` file returned by the API (the current system already produces Apple Wallet passes).

### 6. Family management
List spouse, children and other dependants with identity, relation, date of birth, status, guarantees, consumption and remaining limit. Request to **add** or **remove** a dependant (creates a request that the back-office validates; show its status). Contract management: show renewal date, tacit renewal, termination allowed up to 2 months before the due date (date rules come from the API).

### 7. Encrypted health vault
Categories: prescriptions, lab results, X-rays, vaccines. Upload from camera, photos or files; view; delete. Files are encrypted at rest on the server; on the device, cached files are stored with `NSFileProtectionComplete` and excluded from backups.

### 8. Claim declaration (sinistre)
Exactly these steps:
1. Select the beneficiary
2. Select the type of service (consultation, pharmacy, lab, hospitalisation… list from the API)
3. Photograph the receipt — use **VisionKit `VNDocumentCameraViewController`** (auto edge detection, multi-page)
4. Upload (resumable, with progress)
5. OCR reading — performed by the **server**; the app shows progress (target < 15 s)
6. Auto-prefill of the form with extracted fields: invoice number, date, provider, patient, act, medicine, quantity, unit price, amount, total. **Display each field's confidence score**; highlight low-confidence fields
7. Review and correction by the insured
8. Submit
9. Claim number assigned and displayed
10. Status tracking

Claim statuses (show as a timeline with dates and messages): **Brouillon, Soumis, En analyse, Pièces complémentaires demandées, Pré-validé, Validé, Rejeté, Payé, Clôturé**. When documents are requested, let the user add them directly from the claim screen. Drafts are saved locally and on the server, so the user can finish later or offline.

Show the server's calculation once validated: billed amount, covered amount, reimbursement rate, deductible, amount paid by the insurer, **amount left to pay (reste à charge)**.

### 9. Care network (parcours de soins)
**Map (MapKit) and list** of contracted providers: doctors, specialists, pharmacies, clinics, hospitals, labs. Filter by type and distance, location permission requested only when needed, call / directions buttons. **Download the network as PDF.**

### 10. Notifications
In-app notification center plus push for: subscription, payment, activation, claim received / validated / rejected, missing document, reimbursement, contract expiry. Tapping opens the related screen.

### 11. Profile and settings
Personal info, photo, password change, Face ID toggle, notification preferences, language, terms, privacy policy, support contact, logout, request account deletion (required by the App Store).

## Out of scope for V1 (do not build unless asked)

Teleconsultation, appointment booking, wellness / gamification, conversational assistant, marketing campaigns. Leave room in the navigation to add them later.

## Rules you must not invent

The specification explicitly says that some business rules are not yet defined. **Do not hard-code them in the app.** Everything below comes from the API and must be displayed as received: products, guarantees, limits, rates, exclusions, deductibles, waiting periods, surcharge rules, eligibility rules, required documents per claim type, claim rejection reasons, renewal/termination dates, payment-default suspension. If an endpoint is missing, add it to the OpenAPI contract with a clear `TODO(backend)` and use fixtures.

## API contract

Work against an OpenAPI 3.1 spec at `docs/api/openapi.yaml` (create it if it does not exist yet, then keep the app and spec in sync). Resources, from the specification: `/auth`, `/users`, `/insureds`, `/dependents`, `/products`, `/quotes`, `/policies`, `/claims`, `/providers`, `/eligibility`, `/payments`, `/commissions`, `/notifications`, `/reports`. The app needs at least:

- `POST /auth/register`, `POST /auth/otp/send`, `POST /auth/otp/verify`, `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`
- `GET /me`, `PATCH /me`, `GET /me/dashboard`, `GET /me/card`, `GET /me/card/qr-token`, `GET /me/card/wallet-pass`
- `GET /products`, `GET /health-questionnaire`, `POST /quotes`, `POST /policies` (from quote), `GET /policies/{id}`, `GET /policies/{id}/conditions.pdf`
- `GET /dependents`, `POST /dependents/requests`, `DELETE /dependents/{id}` (creates a removal request)
- `GET /payment-methods`, `POST /payments`, `GET /payments/{id}`, `GET /payments`
- `GET /claim-types`, `POST /claims` (draft), `POST /claims/{id}/documents` (upload), `GET /claims/{id}/ocr`, `PATCH /claims/{id}`, `POST /claims/{id}/submit`, `GET /claims`, `GET /claims/{id}`
- `GET /vault/documents`, `POST /vault/documents`, `DELETE /vault/documents/{id}`
- `GET /providers?type=&lat=&lng=&radius=`, `GET /providers/network.pdf`
- `GET /notifications`, `POST /devices` (APNs token)

Errors use one format: `{ "error": { "code": "...", "message": "...", "fields": { ... } } }` with French messages shown to the user.

## Security

- HTTPS only (ATS on), certificate pinning optional behind a flag.
- No personal or medical data in logs, analytics, URLs or push payloads (push carries only an ID and a generic title).
- Blur the app content in the app switcher. Clear caches on logout.
- Health data is highly sensitive: only fetch what the screen needs.

## Acceptance scenarios (must pass as UI tests on MockAPI)

1. **Subscription:** create an account → choose a formula → get a quote → pay → receive the contract.
2. **Tiers-payant card:** open the card → QR code displayed and refreshed → add to Apple Wallet.
3. **Claim:** photograph an invoice → submit → get a claim number → follow its status.

## How to work

1. Start by listing the screens from the mockups and mapping each one to a feature above. Point out anything in the mockups that contradicts this document, and anything missing.
2. Create the Xcode project, design system, `APIClient`, `MockAPI` with fixtures, and navigation shell (tab bar: Accueil, Carte, Sinistres, Réseau, Profil — adjust to the mockups).
3. Build features in this order: auth → dashboard + card → claims → subscription + payment → family → vault → network → notifications → profile.
4. After each feature: build, run in the iOS Simulator, take screenshots, run the tests, and commit.
5. Document setup and architecture in `ios/README.md`.
