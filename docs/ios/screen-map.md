# ASSUR+ iOS — screen map and gap analysis

Status: **built without the 20 "maquettes assur+"** (not provided yet). Screens below are derived from
`docs/ios-app-prompt.md` and the *Cahier des charges ASSUR+* (§5–§13, §23, §34). When the mockups arrive,
re-run this mapping and list the visual deltas per screen.

## Navigation shell

Tab bar: **Accueil · Carte · Sinistres · Réseau · Profil**. Secondary features are pushed from Accueil
(Famille, Contrat, Paiements, Coffre santé, Notifications) and Profil (settings). A future
"Services" entry (téléconsultation, RDV — out of scope V1) can be added as a 6th tab or an Accueil tile.

## Screens → features

| # | Screen | Feature (prompt §) | Spec (CDC §) |
|---|--------|--------------------|--------------|
| 1 | Welcome | 1 Auth | 5.1 |
| 2 | Login (phone + password / phone + OTP) | 1 Auth | 5.1 |
| 3 | OTP verification | 1 Auth | 5.1 |
| 4 | Sign-up (identity, contact, password, CGU) | 1 Auth | 5.1 |
| 5 | Forgot password | 1 Auth | 5.1 |
| 6 | Biometric lock | 1 Auth / 11 Profile | 27 |
| 7 | Accueil (dashboard) | 4 Dashboard | 8 |
| 8 | Contrat (Conditions Particulières, PDF, renewal/termination) | 4 + 6 | 8, 9 |
| 9 | Carte tiers-payant (QR, beneficiary switcher, Wallet) | 5 Card | 8, 17 |
| 10 | Souscription wizard (8 steps) | 2 Onboarding | 5.2, 6 |
| 11 | Paiement (method choice, waiting, result) | 3 Payment | 7 |
| 12 | Historique des paiements | 3 Payment | 7 |
| 13 | Famille (list + beneficiary detail) | 6 Family | 9 |
| 14 | Ajout / retrait d'un bénéficiaire (request form) | 6 Family | 9 |
| 15 | Coffre santé (categories, list, viewer, upload) | 7 Vault | 9 |
| 16 | Sinistres (list) | 8 Claims | 10 |
| 17 | Déclaration de sinistre (wizard: beneficiary → type → scan → upload → OCR → review → submit) | 8 Claims | 10, 11 |
| 18 | Détail sinistre (timeline, requested documents, settlement) | 8 Claims | 10, 12 |
| 19 | Réseau de soins (map / list, filters, provider detail, PDF) | 9 Network | 13 |
| 20 | Notifications | 10 Notifications | 23 |
| 21 | Profil & réglages (info, photo, password, Face ID, notifications, language, legal, support, logout, deletion) | 11 Profile | — |

## Contradictions / ambiguities found

1. **Acceptance scenario 2** differs: the CDC (§34) defines it from the *provider's* side (scan → identify →
   eligibility → rights). The prompt reframes it for the insured app (show card → QR refresh → Wallet).
   The provider-side scan belongs to the provider portal, not this app.
2. **Termination / renewal** (CDC §9): "résiliation 2 mois avant échéance", "renouvellement automatique… débit
   automatique". The app only displays dates and a `canTerminate` flag from the API; no termination endpoint
   is listed in the prompt → added `POST /policies/{id}/termination-requests` as `TODO(backend)`.
3. **Forgot password, CGU text, password change, notification preferences, account deletion, vault file
   download, notification read state, chunked uploads** have no endpoint in the prompt's list → added to
   `docs/api/openapi.yaml` with `TODO(backend)`.
4. **Upload contract**: the prompt lists `POST /claims/{id}/documents (upload)` but also requires resumable
   uploads. Resolved as a tus-like `/uploads` resource (create → PATCH chunks with `Upload-Offset` → HEAD to
   resume) and `POST /claims/{id}/documents` / `POST /vault/documents` referencing the `uploadId`.
5. **Apple Wallet**: the legacy system produces `.pkpass` files, but `legacy/ikarange/storage/wallet/certs` is
   empty — passes cannot be signed locally, so MockAPI simulates the "added" result.
6. **VisionKit document camera** is unavailable in the iOS Simulator; the claim flow falls back to the photo
   library / Files, and MockAPI offers a bundled sample invoice so the UI test can run.

## Missing from the spec (to arbitrate — CDC §40)

Products, guarantees, limits, rates, exclusions, deductibles, waiting periods, surcharge and eligibility
rules, mandatory documents per claim type, rejection reasons, suspension rules. All are rendered as received
from the API; fixtures contain clearly fake placeholder values.
